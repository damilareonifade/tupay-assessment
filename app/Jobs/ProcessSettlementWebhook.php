<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\SettlementConfirmed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSettlementWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        $providerReference = $this->payload['provider_reference'];
        $session = $this->payload['transaction_session'];
        $status = $this->payload['status'];

        // Idempotency guard — handles duplicate job dispatches.
        if (Transaction::where('provider_reference', $providerReference)->exists()) {
            return;
        }

        $transaction = Transaction::with('wallet')->where('session', $session)->first();

        if ($transaction) {
            // Normal path: payout was previously initiated — the unconfirmed debit exists.
            $this->settleExistingTransaction($transaction, $providerReference, $status);
        } else {
            // Mock path: no prior session match — create a new ledger entry to represent
            // a settlement credit. Requires wallet_id and amount in the payload.
            $transaction = $this->createMockSettlementTransaction($providerReference, $status);
        }

        if ($transaction) {
            $this->notifyOwner($transaction);
        }
    }

    /**
     * Settle a transaction that was created when the payout was initiated.
     * If the debit was unconfirmed, confirm it atomically so the wallet balance is updated.
     */
    private function settleExistingTransaction(
        Transaction $transaction,
        string $providerReference,
        string $status
    ): void {
        DB::transaction(function () use ($transaction, $providerReference, $status) {
            // Confirm the unconfirmed debit (created via unconfirmedDebit() at payout initiation).
            if (! $transaction->confirmed && $status === 'success') {
                $transaction->wallet->confirm($transaction);
            }

            $transaction->forceFill([
                'provider_reference' => $providerReference,
                'status' => $status === 'success' ? 'completed' : 'failed',
            ])->save();
        });

        Log::info('Settlement webhook: existing transaction settled.', [
            'transaction_id' => $transaction->id,
            'provider_reference' => $providerReference,
            'status' => $transaction->status,
        ]);
    }

    /**
     * Mock path: no prior payout transaction found.
     * Creates a new credit entry on the specified wallet to record the settled amount.
     * The webhook payload must include wallet_id (ULID) and amount (subunits).
     */
    private function createMockSettlementTransaction(string $providerReference, string $status): ?Transaction
    {
        $walletId = $this->payload['wallet_id'] ?? null;
        $amountSubunits = isset($this->payload['amount']) ? (int) $this->payload['amount'] : null;

        if (! $walletId || ! $amountSubunits) {
            Log::warning('Settlement webhook: transaction not found and payload is missing wallet_id or amount.', [
                'provider_reference' => $providerReference,
            ]);

            return null;
        }

        $wallet = Wallet::find($walletId);

        if (! $wallet) {
            Log::warning('Settlement webhook: wallet not found for mock transaction.', [
                'wallet_id' => $walletId,
            ]);

            return null;
        }

        // Credit the wallet — represents the settled RMB amount being confirmed.
        $creditDebit = $wallet->credit(
            $amountSubunits,
            'RMB Settlement',
            "Settlement confirmed. Provider reference: {$providerReference}"
        );

        /** @var Transaction|null $transaction */
        $transaction = $creditDebit->getTransactions()->first();

        if ($transaction) {
            $transaction->forceFill([
                'provider_reference' => $providerReference,
                'status' => $status === 'success' ? 'completed' : 'failed',
            ])->save();

            Log::info('Settlement webhook: mock credit transaction created.', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $walletId,
                'amount_subunits' => $amountSubunits,
                'provider_reference' => $providerReference,
            ]);
        }

        return $transaction;
    }

    /**
     * Notify the wallet owner via email and in-app (database) notification.
     */
    private function notifyOwner(Transaction $transaction): void
    {
        $owner = $transaction->wallet?->walletable;

        if (! $owner instanceof User) {
            return;
        }

        $owner->notify(new SettlementConfirmed($transaction));
    }
}
