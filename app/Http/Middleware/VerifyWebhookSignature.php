<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Webhook-Signature');

        if (! $signature) {
            return response()->error('Missing webhook signature.', 401);
        }

        $secret = config('services.webhook_secret');
        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->error('Invalid webhook signature.', 401);
        }

        return $next($request);
    }
}
