<?php

namespace App\WalletModule\Internals\Lockers;

use App\Models\Transaction;
use App\Models\Wallet;
use App\WalletModule\Money\Money;

interface LockerInterface
{
    /**
     * Increase the balance of wallet model using a lock mechanism
     *
     * @param \App\Models\Wallet $wallet
     * @param \App\WalletModule\Money\Money $amount
     * @param \App\Models\Transaction $transaction
     *
     * @return bool
     */
    public function creditLock(Wallet $wallet, Money $amount, Transaction $transaction);

    /**
     * Decrease the balance of wallet model using a lock machnism
     *
     * @param \App\Models\Wallet $wallet
     * @param \App\WalletModule\Money\Money $amount
     * @param \App\Models\Transaction $transaction
     *
     * @return bool
     */
    public function debitLock(Wallet $wallet, Money $amount, Transaction $transaction);

    /**
     * Determine if database transaction should be initiated
     *
     * @param \App\Models\Wallet $wallet
     * @param \App\WalletModule\Money\Money $amount
     * @param \App\Models\Transaction $transaction
     *
     * @return bool
     */
    public function shouldInitiateTransaction(Wallet $wallet, Money $amount, Transaction $transaction);
}
