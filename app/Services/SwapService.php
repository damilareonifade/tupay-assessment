<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\WalletModule\Exceptions\InsufficientBalanceException;
use App\WalletModule\Money\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SwapService
{
    /** Redis lock TTL in seconds — long enough to cover one DB transaction. */
    private const LOCK_TTL = 30;

    public function __construct(private readonly ExchangeRateService $rates)
    {
    }

    /**
     * Atomically swap NGN for CNY for the given user.
     *
     * @param  int  $ngnSubunits  Amount in kobo (subunits of NGN)
     * @return array{from_amount: string, to_amount: string, rate: string, ngn_wallet: Wallet, cny_wallet: Wallet}
     *
     * @throws \RuntimeException When another swap is already in progress.
     * @throws InsufficientBalanceException When the user has insufficient NGN balance.
     */
    public function swapNgnToCny(User $user, int $ngnSubunits): array
    {
        $lock = Cache::store('redis')->lock("swap:{$user->id}", self::LOCK_TTL);

        if (!$lock->get()) {
            throw new \RuntimeException('A swap is already in progress for this account. Please try again shortly.');
        }

        try {
            return DB::transaction(function () use ($user, $ngnSubunits) {
                $ngnWallet = $user->ngnWallet();
                $cnyWallet = $user->cnyWallet();

                if (!$ngnWallet || !$cnyWallet) {
                    throw new \RuntimeException('User wallets not found.');
                }

                if ($ngnWallet->getRawOriginal('amount') < $ngnSubunits) {
                    throw new InsufficientBalanceException($ngnWallet, new Money($ngnSubunits, $ngnWallet->currency));
                }

                $rate = $this->rates->getRate('NGN', 'CNY');

                // BCMath: multiply kobo amount by rate, truncate to whole fen (floor).
                // This prevents floating-point imprecision when converting large amounts.
                $cnySubunits = (int) bcmul((string) $ngnSubunits, $rate, 0);

                if ($cnySubunits <= 0) {
                    throw new \InvalidArgumentException('Swap amount is too small to convert.');
                }

                $ngnWallet->debit($ngnSubunits, 'Currency Swap', "Swapped to CNY at rate {$rate}");
                $cnyWallet->credit($cnySubunits, 'Currency Swap', "Received from NGN swap at rate {$rate}");

                return [
                    'from_amount' => (string) $ngnSubunits,
                    'to_amount' => (string) $cnySubunits,
                    'rate' => $rate,
                    'ngn_wallet' => $ngnWallet->fresh(),
                    'cny_wallet' => $cnyWallet->fresh(),
                ];
            });
        } finally {
            $lock->release();
        }
    }
}
