<?php

namespace App\WalletModule;

use App\WalletModule\Facades\Walletable;
use App\WalletModule\Internals\Lockers\OptimisticLocker;
use App\WalletModule\Internals\Mutation\MutatorManager;
use App\WalletModule\Money\Formatter\IntlMoneyFormatter;
use App\WalletModule\Money\Money;
use App\WalletModule\Transaction\CreditDebitAction;
use App\WalletModule\Transaction\TransferAction;
use Illuminate\Support\ServiceProvider;

class WalletableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(WalletableManager::class);
        $this->app->singleton(MutatorManager::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Money::formatter('intl', function () {
            return new IntlMoneyFormatter(
                new \NumberFormatter('en_US', \NumberFormatter::CURRENCY)
            );
        });

        Walletable::locker('optimistic', OptimisticLocker::class);

        Walletable::action('transfer', TransferAction::class);
        Walletable::action('credit_debit', CreditDebitAction::class);

        $this->addPublishes();
        $this->addCommands();
    }

    /**
     * Register Walletable's publishable files.
     *
     * @return void
     */
    public function addPublishes()
    {
        $this->publishes([
            __DIR__ . '/../config/walletable.php' => config_path('walletable.php')
        ], 'walletable.config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'walletable.migrations');

        $this->publishes([
            __DIR__ . '/../database/models' => app_path('Models'),
        ], 'walletable.models');
    }

    /**
     * Register Walletable's commands.
     *
     * @return void
     */
    protected function addCommands()
    {
        // No commands registered
    }
}
