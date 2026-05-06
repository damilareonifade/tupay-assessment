# Tupay

Tupay is a unified payments platform that streamlines everyday transactions by connecting multiple wallets, banks, and payment methods into one seamless experience. From bill payments to cross-border transfers, Tupay enables fast, secure, and transparent payments without the need to move funds between platforms.

This implementation provides a multi-currency wallet backend (NGN and CNY) built on Laravel 13, featuring TOTP-based two-factor authentication, atomic currency swaps, a Redis-backed exchange rate cache, distributed locking, HMAC-signed webhooks, and an optimistic-locking ledger system.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture](#architecture)
3. [Concurrency Strategy](#concurrency-strategy)
4. [Security Measures](#security-measures)
5. [Performance Optimization](#performance-optimization)
6. [Assumptions](#assumptions)
7. [API Reference](#api-reference)
8. [Database Schema](#database-schema)

---

## Quick Start

**Requirements**: PHP 8.3, Composer, Redis, SQLite (default) or PostgreSQL.

```bash
# 1. Install dependencies
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Configure Redis in .env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# 4. Migrate and seed
php artisan migrate
php artisan db:seed

# 5. Start server + queue worker
php artisan serve
php artisan queue:listen
```

**Test credentials (seeded)**:

- Email: `test@example.com` / Password: `password`
- 2FA is enabled — use the TOTP secret from the database or disable it in the seeder for local testing.
- NGN Wallet: 1,000,000 NGN (100,000,000 kobo)
- CNY Wallet: 500 CNY (50,000 fen)

---

## Architecture

### Folder Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthController.php          # Login, logout, profile, 2FA setup
│   │   │   └── TwoFactorController.php     # TOTP verify & enable
│   │   ├── WalletController.php            # List user wallets
│   │   ├── SwapController.php              # NGN ↔ CNY currency swap
│   │   ├── LedgerController.php            # Paginated transaction history
│   │   └── WebhookController.php           # Universal webhook dispatcher
│   ├── Middleware/
│   │   ├── RequiresTwoFactor.php           # Enforces 2FA token ability
│   │   └── VerifyWebhookSignature.php      # HMAC-SHA256 payload verification
│   └── Resources/
│       ├── UserResource.php
│       ├── WalletResource.php
│       └── TransactionResource.php
│
├── Models/
│   ├── User.php                            # Authenticatable + Walletable
│   ├── Wallet.php                          # WalletInterface, balance/money helpers
│   ├── Transaction.php                     # Ledger entry, Money value objects
│   └── Traits/
│       ├── TransactionRelations.php
│       ├── WalletRelations.php
│       └── WorkWithMeta.php
│
├── Services/
│   ├── ExchangeRateService.php             # Redis-cached NGN/CNY rate lookup
│   ├── SwapService.php                     # Atomic swap with distributed lock
│   └── Webhook/
│       ├── WebhookManager.php              # Driver registry & dispatcher
│       └── Drivers/
│           ├── SettlementWebhookDriver.php # HMAC-SHA256, idempotent settlement
│           ├── MonnifyWebhookDriver.php    # Monnify HMAC-SHA512
│           └── MockWebhookDriver.php
│
├── Jobs/
│   └── ProcessSettlementWebhook.php        # Queued: 3 retries, 60s backoff
│
├── Notifications/
│   └── SettlementConfirmed.php             # Mail + database notification
│
├── Providers/
│   ├── AppServiceProvider.php              # Currency registration, webhook drivers
│   └── ResponseMacroProvider.php          # Consistent JSON response envelope
│
└── WalletModule/                           # Core ledger engine
    ├── Money/                              # Money + Currency value objects
    ├── Transaction/                        # Credit, Debit, Transfer, Confirmation
    ├── Internals/
    │   ├── Lockers/OptimisticLocker.php    # CAS-based race condition prevention
    │   └── Mutation/                       # Balance mutation pipeline
    └── Traits/Walletable.php               # Polymorphic wallet ownership
```

### Design Patterns

**Value Objects (`Money`, `Currency`)**
All monetary amounts are represented as `Money` objects backed by BCMath arithmetic. Amounts are stored in subunits (kobo for NGN, fen for CNY) as unsigned integers to eliminate floating-point errors. The `Money` class is immutable — every arithmetic operation returns a new instance.

**Polymorphic Wallet Ownership**
Wallets are owned via a `walletable` morph relationship, so any model (User, Business, etc.) can hold wallets without schema changes. The `Walletable` trait wires up the `morphMany` and helper methods (`ngnWallet()`, `cnyWallet()`).

**Service Layer**
Business logic lives in `SwapService` and `ExchangeRateService`, not in controllers. Controllers are thin: validate, delegate, return a resource.

**Webhook Driver Pattern**
The `WebhookManager` resolves a named driver (e.g. `settlement`, `monify`) at runtime. Each driver implements `WebhookInterface` with `validate()` and `process()`, making it straightforward to add new payment providers without touching the controller.

**Consistent Response Envelope**
`ResponseMacroProvider` registers `response()->v1()` and `response()->error()` macros that wrap all API responses in a uniform shape (`has_error`, `requestTime`, `status_code`, `message`, `data`).

---

## Concurrency Strategy

### The Problem

Two simultaneous swap or credit/debit requests for the same wallet can both read the same `amount`, compute a new balance, and both write — the second write silently overwrites the first. The result is a balance that does not reflect all transactions (double-spend or silent fund loss).

### Layer 1 — Optimistic Locking (per-wallet balance integrity)

Every credit and debit goes through `OptimisticLocker` in `app/WalletModule/Internals/Lockers/OptimisticLocker.php`. It uses a **compare-and-swap (CAS)** loop:

```
1. Read current wallet amount.
2. Compute new balance (current ± delta).
3. UPDATE wallets SET amount = new_balance WHERE id = ? AND amount = current_amount
4. If 0 rows updated → another process changed the balance; go to step 1 and retry.
5. If 1 row updated → success.
```

Because the `WHERE` clause includes the old balance, the update atomically fails if any concurrent write has already changed it. The loop retries until it wins the race. No pessimistic lock is held between the read and the write, so throughput remains high under contention.

This protects every `credit()`, `debit()`, and `confirm()` call on the `Wallet` model.

### Layer 2 — Distributed Redis Lock (per-user swap serialisation)

Currency swaps involve two wallets (debit NGN, credit CNY) and a rate fetch. Even with optimistic locking on each individual wallet, a user could fire two concurrent swap requests: both read the same rate, both pass the balance check, and both execute — potentially draining the wallet.

`SwapService::swapNgnToCny()` acquires a Redis lock keyed to the user before entering the DB transaction:

```php
$lock = Cache::store('redis')->lock("swap:{$user->id}", self::LOCK_TTL);

if (!$lock->get()) {
    throw new \RuntimeException('A swap is already in progress...');
}

try {
    return DB::transaction(function () use (...) {
        // balance check → rate fetch → debit NGN → credit CNY
    });
} finally {
    $lock->release();
}
```

- Lock TTL is 30 seconds — long enough to cover a DB transaction, short enough to auto-release on a crash.
- `finally` guarantees the lock is released even when an exception is thrown inside the transaction.
- The balance check inside the transaction is a last-resort gate in case the wallet was changed between lock acquisition and the debit.

### Layer 3 — Webhook Idempotency

Settlement webhooks carry a `provider_reference` (unique ID from the payment provider). Before processing, the driver checks:

```php
if (Transaction::where('provider_reference', $reference)->exists()) {
    return; // already processed
}
```

This prevents replayed or retried webhook deliveries from crediting a wallet twice.

### Summary

| Threat                                   | Mitigation                             |
| ---------------------------------------- | -------------------------------------- |
| Concurrent balance updates on one wallet | OptimisticLocker CAS loop              |
| Concurrent swap requests from one user   | Redis distributed lock (30 s TTL)      |
| Replayed settlement webhooks             | `provider_reference` idempotency check |
| Swap race between rate fetch and debit   | DB transaction wrapping debit + credit |

---

## Security Measures

### Two-Factor Authentication (TOTP)

2FA is implemented with `pragmarx/google2fa` using the standard TOTP algorithm (RFC 6238), compatible with Google Authenticator, Authy, and any TOTP app.

**Setup flow:**

```
POST /api/2fa/setup   → { secret, qr_code_url }
                         User scans QR code in authenticator app.

POST /api/2fa/enable  → { code: "123456" }
                         Verifies the code once to confirm setup.
                         Sets two_factor_enabled = true on the user.
```

**Verification flow:**

```
POST /api/login       → Sanctum token with ability: ["basic"]

POST /api/2fa/verify  → { code: "123456" }
                         Verifies current TOTP window.
                         Returns new token with ability: ["basic", "2fa"]
```

**Enforcement:**

The `RequiresTwoFactor` middleware is applied to sensitive routes (e.g. `/api/swap`). It checks:

```php
if ($user->two_factor_enabled && !$request->user()->tokenCan('2fa')) {
    return response()->json(['message' => '2FA verification required.'], 403);
}
```

A token without the `2fa` ability cannot access protected endpoints, even if it is otherwise a valid Sanctum token.

**Secret storage:**

The TOTP secret is stored encrypted in `users.two_factor_secret` using Laravel's `encrypt()` / `decrypt()` helpers (AES-256-CBC backed by the application key).

**Brute-force protection:**

`POST /api/2fa/verify` is throttled to 5 requests per minute per IP (`throttle:5,1`), limiting TOTP code guessing attempts.

---

### Webhook Signature Verification

All incoming webhook deliveries must carry an `X-Webhook-Signature` header. The `VerifyWebhookSignature` middleware validates the signature before the request reaches any controller:

```php
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
$provided = $request->header('X-Webhook-Signature');

if (!hash_equals($expected, $provided)) {
    return response()->json(['message' => 'Invalid signature.'], 401);
}
```

Key points:

- `hash_equals()` performs a **timing-safe comparison**, preventing timing attacks that could leak the HMAC via response-time differences.
- The raw request body (not parsed JSON) is hashed — JSON parsers may reorder keys, which would invalidate the signature.
- The shared secret is read from `config('services.webhook_secret')` / `WEBHOOK_SECRET` env var, never hardcoded.
- Monnify uses HMAC-SHA512 with their own header (`monnify-signature`), handled inside `MonnifyWebhookDriver`.

---

## Performance Optimization

### Redis Exchange Rate Cache

Fetching a live exchange rate on every swap would add latency and create a hard dependency on an external API. `ExchangeRateService` caches the rate in Redis with a 5-minute TTL:

```php
return Cache::store('redis')
    ->remember("exchange_rate:{$from}:{$to}", 300, function () use ($from, $to) {
        return $this->fetchRate($from, $to);
    });
```

- `remember()` is atomic — only one process fetches the rate on a cache miss; all others read the cached value. This prevents a **thundering herd** when the TTL expires under high concurrency.
- Redis reads are sub-millisecond versus an HTTP round-trip to an external rate API.
- The 5-minute TTL balances freshness with performance and can be tuned based on how volatile the upstream rate source is.

### Redis Distributed Locking (Fail-Fast)

Beyond correctness (see Concurrency), the Redis lock in `SwapService` provides a performance benefit: it **fails fast** instead of queuing. If a user's swap is already in flight, the second request immediately receives an error rather than waiting, retrying, or hammering the database.

### Optimistic Locking (No Row Locks)

The `OptimisticLocker` avoids `SELECT ... FOR UPDATE` entirely. Holding a row lock for the duration of business logic (rate fetch, validation) would serialize all wallet operations globally. The CAS loop holds no lock between read and write, so concurrent operations on _different_ wallets proceed in parallel without blocking each other.

### Queue-Driven Webhook Processing

Settlement processing (transaction confirmation, notification dispatch) is offloaded to `ProcessSettlementWebhook`, a queued job with 3 retries and 60-second backoff. The webhook endpoint returns `200 OK` immediately after signature validation and job dispatch, keeping webhook response times fast and preventing payment providers from retrying due to timeouts.

### Subunit Integer Arithmetic

All monetary amounts are stored and computed as integers (kobo, fen). Integer arithmetic avoids floating-point rounding and is faster than `float` or `string` operations. BCMath is only introduced where required (e.g. rate multiplication in `SwapService::swapNgnToCny()`) to handle precision beyond native integer math.

---

## Assumptions

### Mock Exchange Rates

`ExchangeRateService::fetchRate()` returns hardcoded rates rather than calling a live API:

```
1 NGN = 0.004500 CNY
1 CNY = 222.222222 NGN
```

These are realistic approximations of the real NGN/CNY rate. In production, `fetchRate()` would call an external provider (e.g. Open Exchange Rates, Fixer.io) and the Redis cache would absorb most traffic.

**Swap rounding**: CNY subunits are computed with `bcmul($ngnSubunits, $rate, 0)`, which truncates (floor) to zero decimal places. Any fractional fen is discarded — the user never receives less than they should, and the platform never over-credits.

### Mock Settlement Webhook

`SettlementWebhookDriver` supports two processing paths:

1. **Session-based path** (normal): Matches `transaction_session` to an existing unconfirmed transaction and confirms it.
2. **Mock path**: If no session match is found, creates a new credit transaction directly on the wallet identified by `wallet_id` in the payload.

The mock path allows end-to-end testing of the webhook pipeline without requiring a full checkout flow — POST a signed payload and observe a wallet balance change immediately.

### Webhook Secret Default

The default `WEBHOOK_SECRET` is `tupay-webhook-secret-key` (in `config/services.php`) to allow local testing without configuration. In any real deployment this must be replaced with a cryptographically random value shared securely with the payment provider.

### Currency Scope

Only NGN and CNY wallets are provisioned. The wallet system is currency-agnostic — adding new currencies requires only registering them in `AppServiceProvider` and extending the seeder or onboarding flow.

### 2FA Always Required for Swap

`/api/swap` requires 2FA regardless of amount. In production, a risk-based policy (require 2FA only above a threshold, or only from new devices) would reduce friction for small swaps while maintaining security for high-value operations.

### Queue Driver

The default queue connection is `sync` for local development convenience. `ProcessSettlementWebhook` is designed for an async queue. For production, set `QUEUE_CONNECTION=redis` and run `php artisan queue:listen`.

---

## API Reference

All responses follow the envelope:

```json
{
  "has_error": false,
  "requestTime": "2026-05-06T10:00:00.000000Z",
  "status_code": 200,
  "message": "...",
  "data": { ... }
}
```

### Authentication

| Method | Endpoint      | Auth   | Description                  |
| ------ | ------------- | ------ | ---------------------------- |
| POST   | `/api/login`  | None   | Login, returns Sanctum token |
| POST   | `/api/logout` | Bearer | Revoke current token         |
| GET    | `/api/me`     | Bearer | Authenticated user profile   |

### Two-Factor Authentication

| Method | Endpoint          | Auth   | Description                                  |
| ------ | ----------------- | ------ | -------------------------------------------- |
| POST   | `/api/2fa/setup`  | Bearer | Generate TOTP secret + QR URL                |
| POST   | `/api/2fa/enable` | Bearer | Enable 2FA with first TOTP code              |
| POST   | `/api/2fa/verify` | Bearer | Verify TOTP; upgrades token to `2fa` ability |

### Wallets & Ledger

| Method | Endpoint               | Auth   | Description                     |
| ------ | ---------------------- | ------ | ------------------------------- |
| GET    | `/api/wallets`         | Bearer | List user wallets with balances |
| GET    | `/api/ledger/{wallet}` | Bearer | Paginated transaction history   |

### Swap

| Method | Endpoint    | Auth         | Description     |
| ------ | ----------- | ------------ | --------------- |
| POST   | `/api/swap` | Bearer + 2FA | Swap NGN to CNY |

```json
// Request
{ "amount": 100000 }

// amount is in kobo (100000 kobo = 1,000 NGN)
```

### Webhooks

| Method | Endpoint                   | Signature   | Description             |
| ------ | -------------------------- | ----------- | ----------------------- |
| POST   | `/api/webhooks/settlement` | HMAC-SHA256 | Settlement notification |
| POST   | `/api/webhooks/monify`     | HMAC-SHA512 | Monnify payment event   |

```json
// Settlement payload
{
    "provider_reference": "PAY-REF-001",
    "transaction_session": "uuid-of-session",
    "status": "success",
    "wallet_id": "01jt...",
    "amount": 50000
}
```

---

## Database Schema

### users

| Column                  | Type      | Notes                             |
| ----------------------- | --------- | --------------------------------- |
| id                      | ULID      | Primary key                       |
| name                    | string    |                                   |
| email                   | string    | Unique                            |
| password                | string    | Bcrypt hashed                     |
| two_factor_secret       | text      | AES-256-CBC encrypted TOTP secret |
| two_factor_enabled      | boolean   | Default false                     |
| created_at / updated_at | timestamp |                                   |

### wallets

| Column          | Type               | Notes                      |
| --------------- | ------------------ | -------------------------- |
| id              | ULID               | Primary key                |
| walletable_id   | string             | Polymorphic owner ID       |
| walletable_type | string             | e.g. `App\Models\User`     |
| label           | string             | Display name               |
| tag             | string             | Indexed; e.g. `ngn`, `cny` |
| amount          | unsignedBigInteger | Balance in subunits        |
| currency        | string             | ISO 4217 code              |
| status          | enum               | `active` / `blocked`       |
| meta            | jsonb              | Extensible metadata        |

### transactions

| Column                  | Type               | Notes                                          |
| ----------------------- | ------------------ | ---------------------------------------------- |
| id                      | ULID               | Primary key                                    |
| wallet_id               | ULID               | Foreign key → wallets                          |
| session                 | string             | Groups related transactions                    |
| provider_reference      | string             | Unique; idempotency key for webhooks           |
| type                    | enum               | `credit` / `debit`                             |
| amount                  | unsignedBigInteger | In subunits                                    |
| balance                 | bigInteger         | Running balance after this entry               |
| currency                | string             | ISO 4217 code                                  |
| action                  | string             | e.g. `credit_debit`, `transfer`                |
| method_id / method_type | string             | Polymorphic link to payment method             |
| remarks                 | string             | Human-readable note                            |
| confirmed               | boolean            | `false` = pending settlement                   |
| confirmed_at            | datetime           | When confirmation occurred                     |
| meta                    | jsonb              | Extensible metadata                            |
| created_at              | datetime           | No `updated_at` — ledger entries are immutable |
