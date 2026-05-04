<?php

namespace App\WalletModule\Transaction;

use App\Models\Transaction;
use App\Models\Wallet;
use App\WalletModule\Facades\Walletable;
use App\WalletModule\Internals\Actions\ActionData;
use App\WalletModule\Internals\Actions\ActionInterface;
use App\WalletModule\Money\Money;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UnconfirmedCreditDebit
{
    /**
     * Transaction type
     *
     * @var string
     */
    protected $type;

    /**
     * Sender wallet
     *
     * @var \App\Models\Wallet
     */
    protected $wallet;

    /**
     * Amount to transfer
     *
     * @var \App\WalletModule\Money\Money
     */
    protected $amount;

    /**
     * Trasanction bads
     *
     * @var \App\WalletModule\Transaction\TransactionBag|\Illuminate\Support\Collection
     */
    protected $bag;

    /**
     * Title of the
     *
     * @var string|null
     */
    protected $title;

    /**
     * Note added to the transfer
     *
     * @var string|null
     */
    protected $remarks;

    /**
     * The session id of the transfer
     *
     * @var bool
     */
    protected $session;

    /**
     * Action of the transaction
     *
     * @var \App\WalletModule\Internals\Actions\ActionInterface
     */
    protected $action;

    /**
     * Action of the transaction
     *
     * @var \App\WalletModule\Internals\Actions\ActionData
     */
    protected $actionData;

    public function __construct(
        string $type,
        Wallet $wallet,
        Money $amount,
        string|null $title = null,
        string|null $remarks = null,
    ) {
        if (!in_array($type, ['credit', 'debit'])) {
            throw new InvalidArgumentException('Argument 1 value can only be "credit" or "debit"');
        }

        $this->type = $type;
        $this->wallet = $wallet;
        $this->amount = $amount;
        $this->title = $title;
        $this->remarks = $remarks;
        $this->session = Str::uuid();
        $this->bag = new TransactionBag();
    }

    /**
     * Execute the transfer
     *
     * @return self
     */
    public function execute(): self
    {
        $transaction = $this->bag->new($this->wallet, [
            'type' => $this->type,
            'amount' => $this->amount->integer(),
            'balance' => $this->wallet->amount->integer(),
            'session' => $this->session,
            'remarks' => $this->remarks
        ]);

        $action = $this->action ?? Walletable::action('credit_debit');

        Walletable::applyAction(
            $action,
            $this->bag,
            $this->actionData ?? new ActionData(
                $this->wallet,
                $this->title
            )
        );

        $this->bag->each(function ($item) {
            $item->forceFill([
                'confirmed' => false,
                'created_at' => now()
            ])->save();
        });

        return $this;
    }

    /**
     * Get transaction
     *
     * @return \App\Models\Transaction
     */
    public function transaction(): Transaction
    {
        return $this->bag->first();
    }

    /**
     * Get transaction bag
     *
     * @return \App\WalletModule\Transaction\TransactionBag
     */
    public function getTransactions(): TransactionBag
    {
        return $this->bag;
    }

    /**
     * Get amount
     *
     * @return \App\WalletModule\Money\Money
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * Set the action for the transaction
     *
     * @param \App\WalletModule\Internals\Actions\ActionInterface|string $action
     * @param \App\WalletModule\Internals\Actions\ActionData $actionData
     *
     * @return self
     */
    public function setAction($action, ActionData $actionData): self
    {
        if (!is_string($action) && !($action instanceof ActionInterface)) {
            throw new InvalidArgumentException(
                sprintf('Argument 1 must be of type %s or String', ActionInterface::class)
            );
        }

        if (is_string($action)) {
            $action = Walletable::action($action);
        }

        $this->action = $action;
        $this->actionData = $actionData;

        return $this;
    }
}
