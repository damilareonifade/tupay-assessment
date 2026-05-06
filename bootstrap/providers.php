<?php

use App\Providers\AppServiceProvider;
use App\Providers\ResponseMacroProvider;
use App\WalletModule\WalletableServiceProvider;

return [
    AppServiceProvider::class,
    ResponseMacroProvider::class,
    WalletableServiceProvider::class,
];
