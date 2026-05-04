<?php

namespace App\WalletModule\Exceptions;

use AssertionError;
use App\Models\Wallet;
use App\WalletModule\Money\Money;

class InsufficientBalanceException extends AssertionError
{
    /**
     * Wallet model
     *
     * @var \App\Models\Wallet
     */
    protected $wallet;

    /**
     * Money object of amount that should be deducted
     *
     * @var \App\WalletModule\Money\Money
     */
    protected $amount;

    public function __construct(Wallet $wallet, Money $amount)
    {
        $this->wallet = $wallet;
        $this->amount = $amount;
        $this->message = 'Insufficient wallet balance, The wallet ballance is less than ' . $amount;
    }

    /**
     * Get wallet property
     *
     * @return string
     */
    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    /**
     * Get amount property
     *
     * @return string
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }
}
