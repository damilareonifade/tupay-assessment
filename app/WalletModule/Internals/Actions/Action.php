<?php

namespace App\WalletModule\Internals\Actions;

use App\Models\Wallet;
use App\WalletModule\Money\Money;
use App\WalletModule\Transaction\CreditDebit;
use App\WalletModule\Transaction\UnconfirmedCreditDebit;
use InvalidArgumentException;

class Action
{
    /**
     * Wallet
     *
     * @var \App\Models\Wallet
     */
    protected $wallet;

    /**
     * The action
     *
     * @var \App\WalletModule\Internals\Actions\ActionInterface
     */
    protected $action;

    public function __construct(Wallet $wallet, ActionInterface $action)
    {
        $this->wallet = $wallet;
        $this->action = $action;
    }

    /**
     * Unconfirmed Credit the wallet
     *
     * @param int|\App\WalletModule\Money\Money $amount
     * @param \App\WalletModule\Internals\Actions\ActionData $data
     * @param string|null $remarks
     */
    public function unconfirmedCredit($amount, ActionData $data, string|null $remarks = null): UnconfirmedCreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException(sprintf('Argument 1 must be of type %s or Integer', Money::class));
        }

        if (is_int($amount)) {
            $amount = $this->wallet->money($amount);
        }

        return (new UnconfirmedCreditDebit('credit', $this->wallet, $amount, null, $remarks))
            ->setAction($this->action, $data)
            ->execute();
    }

    /**
     * Unconfirmed Debit the wallet
     *
     * @param int|\App\WalletModule\Money\Money $amount
     * @param \App\WalletModule\Internals\Actions\ActionData $data
     * @param string|null $remarks
     */
    public function unconfirmedDebit($amount, ActionData $data, string|null $remarks = null): UnconfirmedCreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException(sprintf('Argument 1 must be of type %s or Integer', Money::class));
        }

        if (is_int($amount)) {
            $amount = $this->wallet->money($amount);
        }

        return (new UnconfirmedCreditDebit('debit', $this->wallet, $amount, null, $remarks))
            ->setAction($this->action, $data)
            ->execute();
    }

    /**
     * Credit the wallet
     *
     * @param int|\App\WalletModule\Money\Money $amount
     * @param \App\WalletModule\Internals\Actions\ActionData $data
     * @param string|null $remarks
     */
    public function credit($amount, ActionData $data, string|null $remarks = null): CreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException(sprintf('Argument 1 must be of type %s or Integer', Money::class));
        }

        if (is_int($amount)) {
            $amount = $this->wallet->money($amount);
        }

        return (new CreditDebit('credit', $this->wallet, $amount, null, $remarks))
            ->setAction($this->action, $data)
            ->execute();
    }

    /**
     * Debit the wallet
     *
     * @param int|\App\WalletModule\Money\Money $amount
     * @param \App\WalletModule\Internals\Actions\ActionData $data
     * @param string|null $remarks
     */
    public function debit($amount, ActionData $data, string|null $remarks = null): CreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException(sprintf('Argument 1 must be of type %s or Integer', Money::class));
        }

        if (is_int($amount)) {
            $amount = $this->wallet->money($amount);
        }

        return (new CreditDebit('debit', $this->wallet, $amount, null, $remarks))
            ->setAction($this->action, $data)
            ->execute();
    }

    /**
     * Get the raw action object
     *
     * @return ActionInterface
     */
    public function getAction(): ActionInterface
    {
        return $this->action;
    }
}
