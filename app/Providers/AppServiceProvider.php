<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Webhook\Drivers\MonnifyWebhookDriver;
use App\Services\Webhook\Drivers\SettlementWebhookDriver;
use App\Services\Webhook\Webhook;
use App\Services\Webhook\WebhookManager;
use App\WalletModule\Money\Currency;
use App\WalletModule\Money\Money;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookManager::class);
    }

    public function boot(): void
    {
        Money::currencies(
            Currency::new('NGN', '₦', 'Nigerian Naira', 'Kobo', 100, 566),
            Currency::new('CNY', '¥', 'Chinese Yuan', 'Fen', 100, 156),
        );

        Webhook::driver('settlement', SettlementWebhookDriver::class);
        Webhook::driver('monify', MonnifyWebhookDriver::class);

        Relation::morphMap([
            'user' => User::class,
            'wallet' => Wallet::class,
        ]);
    }
}
