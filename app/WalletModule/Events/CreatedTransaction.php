<?php

namespace App\WalletModule\Events;
use Illuminate\Queue\SerializesModels;
use App\Models\Transaction;

class CreatedTransaction
{
    use SerializesModels;


    /**
     * The owner of the created wallet.
     *
     * @var \App\Models\Transaction
     */
    public $transaction;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Transaction  $transaction
     *
     * @return void
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

}