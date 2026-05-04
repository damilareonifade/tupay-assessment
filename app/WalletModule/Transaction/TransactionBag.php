<?php

namespace App\WalletModule\Transaction;

use App\Models\Transaction;
use App\Models\Wallet;
use App\WalletModule\Events\CreatingTransaction;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\ForwardsCalls;

class TransactionBag
{
    use ForwardsCalls;

    /**
     * Transactions collection
     *
     * @var \Illuminate\Support\Collection
     */
    protected $transactions;

    public function __construct(?Transaction ...$transactions)
    {
        $this->transactions = \collect($transactions ?? []);
    }

    /**
     * Create new transaction
     *
     * @param \App\Models\Wallet $wallet
     * @param array $data
     *
     * @return \App\Models\Transaction
     */
    public function new(Wallet $wallet, array $data)
    {
        $trasanction = App::make(Config::get('walletable.models.transaction'))->forceFill([
            'wallet_id' => $wallet->getKey(),
            'currency' => $wallet->getRawOriginal('currency')
        ] + $data);
        App::make('events')->dispatch(new CreatingTransaction(
            $wallet,
            $trasanction
        ));
        $this->transactions->add($trasanction);
        return $trasanction;
    }

    /**
     * Add new transaction
     *
     * @param \App\Models\Transaction $transaction
     * @return self
     */
    public function add(Transaction $transaction)
    {
        $this->transactions->add($transaction);
        return $this;
    }

    /**
     * Save all transactions in this bag
     *
     * @return self
     */
    public function save()
    {
        $this->transactions->each(function (Transaction $trasanction) {
            $trasanction->save();
        });

        return $this;
    }

    /**
     * Map method calls to the collection
     *
     * @param string $method
     * @param array $parameters
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->transactions, $method, $parameters);
    }
}
