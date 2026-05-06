<?php

namespace App\Http\Controllers;

use App\Services\Webhook\Webhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function webhook(Request $request, $driver): Response
    {
        return Webhook::processWebhook($driver, $request);
    }
}
