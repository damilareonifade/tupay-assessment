<?php

namespace App\Models;

use App\Models\Traits\WalletRelations;
use App\Models\Traits\WorkWithMeta;
use App\WalletModule\Contracts\WalletInterface;
use App\WalletModule\Exceptions\InvalidArgumentException;
use App\WalletModule\Facades\Mutator;
use App\WalletModule\Facades\Walletable;
use App\WalletModule\Internals\Actions\Action;
use App\WalletModule\Internals\Mutation\System\WalletBalanceMutation;
use App\WalletModule\Money\Currency;
use App\WalletModule\Money\Money;
use App\WalletModule\Traits\ConditionalID;
use App\WalletModule\Transaction\Confirmation;
use App\WalletModule\Transaction\CreditDebit;
use App\WalletModule\Transaction\Transfer;
use App\WalletModule\Transaction\UnconfirmedCreditDebit;
use App\WalletModule\WalletableManager;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Traits\Macroable;

/**
 * @property-read Money $balance
 * @property Money $amount
 * @property-read Currency $currency
 */
class Wallet extends Model implements WalletInterface
{
    use HasUlids;
    use WalletRelations;
    use WorkWithMeta;

    /**
     * Hold object for the wallet
     *
     * @var array
     */
    protected $instanceCache = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Get the real balance object of a wallet
     *
     * @return Money
     */
    public function getAmountAttribute($value)
    {
        return new Money(
            $value,
            $this->currency
        );
    }

    /**
     * Get the real balance object of a wallet
     */
    public function getBalanceAttribute(): Money
    {
        return Mutator::mutate(new WalletBalanceMutation(
            'wallet.balance',
            new Money(
                $this->attributes['amount'] ?? $this->getRawOriginal('amount') ?? 0,
                $this->currency
            ),
            [
                'wallet' => $this,
            ]
        ))->value();
    }

    /**
     * Get the currency object of the wallet
     *
     * @return Currency
     */
    public function getCurrencyAttribute()
    {
        return Money::currency($this->attributes['currency'] ?? $this->getRawOriginal('currency'));
    }

    /**
     * Check if this wallet is compactible with another wallet
     */
    public function compactible(self $wallet): bool
    {
        return Walletable::compactible($this, $wallet);
    }

    /**
     * Transfer money to another wallet
     *
     * @param  int|Money  $amount
     */
    public function transfer(self $wallet, $amount, ?string $remarks = null): Transfer
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException('Argument 2 must be of type ' . Money::class . ' or Integer');
        }

        if (is_int($amount)) {
            $amount = $this->money($amount);
        }

        return (new Transfer($this, $amount, $wallet, $remarks))->execute();
    }

    /**
     * Confirmation
     *
     * @param  int|Transaction  $amount
     */
    public function confirm(Transaction $transaction): Confirmation
    {
        if ($this->getKey() != $transaction->wallet_id) {
            throw new InvalidArgumentException('The transaction can only be confirmed from the same wallet.');
        }

        return (new Confirmation($this, $transaction))->execute();
    }

    /**
     * Unconfirmed Credit the wallet
     *
     * @param  int|Money  $amount
     */
    public function unconfirmedCredit($amount, ?string $title = null, ?string $remarks = null): UnconfirmedCreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException('Argument 1 must be of type ' . Money::class . ' or Integer');
        }

        if (is_int($amount)) {
            $amount = $this->money($amount);
        }

        return (new UnconfirmedCreditDebit('credit', $this, $amount, $title, $remarks))->execute();
    }

    /**
     * Unconfirmed Debit the wallet
     *
     * @param  int|Money  $amount
     */
    public function unconfirmedDebit($amount, ?string $title = null, ?string $remarks = null): UnconfirmedCreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException('Argument 1 must be of type ' . Money::class . ' or Integer');
        }

        if (is_int($amount)) {
            $amount = $this->money($amount);
        }

        return (new UnconfirmedCreditDebit('debit', $this, $amount, $title, $remarks))->execute();
    }

    /**
     * Credit the wallet
     *
     * @param  int|Money  $amount
     */
    public function credit($amount, ?string $title = null, ?string $remarks = null): CreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException('Argument 1 must be of type ' . Money::class . ' or Integer');
        }

        if (is_int($amount)) {
            $amount = $this->money($amount);
        }

        return (new CreditDebit('credit', $this, $amount, $title, $remarks))->execute();
    }

    /**
     * Debit the wallet
     *
     * @param  int|Money  $amount
     */
    public function debit($amount, ?string $title = null, ?string $remarks = null): CreditDebit
    {
        if (!is_int($amount) && !($amount instanceof Money)) {
            throw new InvalidArgumentException('Argument 1 must be of type ' . Money::class . ' or Integer');
        }

        if (is_int($amount)) {
            $amount = $this->money($amount);
        }

        return (new CreditDebit('debit', $this, $amount, $title, $remarks))->execute();
    }

    /**
     * Return money object of thesame currency
     *
     *
     * @return Money
     */
    public function money(int $amount)
    {
        return new Money(
            $amount,
            $this->currency
        );
    }

    /**
     * Create action for the wallet
     *
     * @param  string  $action  the name of the action
     */
    public function action(string $action): Action
    {
        if (isset($this->instanceCache['actions'][$action])) {
            return $this->instanceCache['actions'][$action];
        }

        return $this->instanceCache['actions'][$action] = new Action(
            $this,
            App::make(WalletableManager::class)
                ->action($action)
        );
    }

    /**
     * Handle dynamic calls into macros or pass missing methods to the parrent.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Handle dynamic static calls into macros or pass missing methods to the parrent.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return static::macroCallStatic($method, $parameters);
        }

        return parent::__callStatic($method, $parameters);
    }
}
