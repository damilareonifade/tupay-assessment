<?php

namespace App\Services\Webhook;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Webhook\Drivers\WebhookInterface driver(string $name, $driver = null)
 * @method static \Symfony\Component\HttpFoundation\Response processWebhook(string $name, \Illuminate\Http\Request $request)
 */
class Webhook extends Facade
{
    protected static function getFacadeAccessor()
    {
        return WebhookManager::class;
    }
}
