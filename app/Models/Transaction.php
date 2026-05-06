<?php

namespace App\Models;

use App\Models\Traits\TransactionRelations;
use App\Models\Traits\WorkWithMeta;
use App\WalletModule\Internals\Actions\ActionManager;
use App\WalletModule\Money\Currency;
use App\WalletModule\Money\Money;
use App\WalletModule\Traits\ConditionalID;
use App\WalletModule\WalletableManager;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Traits\Macroable;

/**
 * @property-read Money $balance
 * @property-read Money $amount
 * @property-read Currency $currency
 * @property-read ActionManager $action
 * @property-read string $title
 * @property-read string $image
 * @property-read string $remarks
 */
class Transaction extends Model
{
    use HasUlids;
    use TransactionRelations;
    use WorkWithMeta;

    public $timestamps = false;

    protected $transactionCache = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getAmountAttribute(): Money
    {
        return new Money(
            $this->getRawOriginal('amount'),
            $this->currency
        );
    }

    public function getBalanceAttribute(): Money
    {
        return new Money(
            $this->getRawOriginal('balance'),
            $this->currency
        );
    }

    public function getActionAttribute(): ActionManager
    {
        if (isset($this->transactionCache['action'])) {
            return $this->transactionCache['action'];
        }

        return $this->transactionCache['action'] = new ActionManager(
            $this,
            App::make(WalletableManager::class)
                ->action($this->getRawOriginal('action'))
        );
    }

    public function getTitleAttribute(): ?string
    {
        return $this->action->title();
    }

    public function getImageAttribute(): ?string
    {
        return $this->action->image();
    }

    public function getCurrencyAttribute(): Currency
    {
        return Money::currency($this->getRawOriginal('currency'));
    }

    public function getMethodResource()
    {
        return $this->action->resource();
    }

}
