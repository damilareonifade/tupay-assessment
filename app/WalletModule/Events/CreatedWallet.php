<?php

namespace App\WalletModule\Events;

use Illuminate\Queue\SerializesModels;

class CreatedWallet
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
     * @var \App\WalletModule\Contracts\Walletable
     */
    public $walletable;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Wallet  $wallet
     * @param  \App\WalletModule\Contracts\Walletable  $walletable
     *
     * @return void
     */
    public function __construct($wallet, $walletable)
    {
        $this->wallet = $wallet;
        $this->walletable = $walletable;
    }
}
