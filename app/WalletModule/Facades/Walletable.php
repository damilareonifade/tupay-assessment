<?php

namespace App\WalletModule\Facades;

use App\WalletModule\WalletableManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\Wallet create(\App\WalletModule\Contracts\Walletable $walletable, string $label, string $tag, string $currency)
 * @method static bool compactible(\App\Models\Wallet $wallet, \App\Models\Wallet $against)
 * @method static mixed applyAction($action, object $transactions, \App\WalletModule\Internals\Actions\ActionData $data)
 * @method static void macro($name, $macro)
 * @method static void flushMacros()
 * @method static bool hasMacro($name)
 * @method static void mixin($mixin, $replace = true)
 * @method static \App\WalletModule\Internals\Lockers\LockerInterface|void locker(string $name, $locker = null)
 * @method static \App\WalletModule\Internals\Actions\ActionInterface|void action(string $name, $action = null)
 */
class Walletable extends Facade
{
    protected static function getFacadeAccessor()
    {
        return WalletableManager::class;
    }
}
