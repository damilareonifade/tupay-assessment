<?php

namespace App\WalletModule\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\Wallet;
use App\Models\Transaction;

class CreatingTransaction
{
    use SerializesModels;

    /**
     * The created wallet.
     *
     * @var \App\Models\Wallet
     */
    public $wallet;

    /**
     * The owner of the created wallet.
     *
     * @var \App\Models\Transaction
     */
    public $transaction;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Wallet  $wallet
     * @param  \App\Models\Transaction  $transaction
     *
     * @return void
     */
    public function __construct(Wallet $wallet, Transaction $transaction)
    {
        $this->wallet = $wallet;
        $this->transaction = $transaction;
    }
}
