<?php

namespace App\WalletModule\Events;

use App\Models\Transaction;
use Illuminate\Queue\SerializesModels;

class CreatedTransaction
{
    use SerializesModels;

    /**
     * The owner of the created wallet.
     *
     * @var Transaction
     */
    public $transaction;

    /**
     * Create a new event instance.
     *
     *
     * @return void
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }
}
