<?php

namespace App\WalletModule\Exceptions;

use AssertionError;
use App\Models\Wallet;

class IncompactibleWalletsException extends AssertionError
{
    /**
     * Wallet model
     *
     * @var \App\Models\Wallet
     */
    protected $wallet;

    /**
     * The wallet you are checking
     *
     * @var \App\Models\Wallet
     */
    protected $against;

    public function __construct(Wallet $wallet, Wallet $against)
    {
        $this->wallet = $wallet;
        $this->against = $against;
        $this->message = 'Can`t perform any operations between two incompactible wallets';
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
     * Get against property
     *
     * @return string
     */
    public function getAgainst(): Wallet
    {
        return $this->against;
    }
}
