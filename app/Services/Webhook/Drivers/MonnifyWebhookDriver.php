<?php

namespace App\Services\Webhook\Drivers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MonnifyWebhookDriver implements WebhookInterface
{
    public const SUCCESS_TRANSACTION = 'SUCCESSFUL_TRANSACTION';

    public function name(): string
    {
        return 'monify';
    }

    public function validate(Request $request, array $data, string $raw): bool
    {
        $clientSecret = config('services.monify.clientSecret');
        $monnifySignature = $request->header('monnify-signature');
        $rawPayload = $request->getContent();

        $computedSignature = hash_hmac('sha512', $rawPayload, $clientSecret);

        return hash_equals($monnifySignature, $computedSignature);
    }

    public function process(Request $request, array $data, string $raw): Response
    {
        $payload = $request->all();

        return match ($payload['event'] ?? $payload['eventType']) {
            static::SUCCESS_TRANSACTION => $this->processTransaction($request, $data, ''),
            default => $this->processDefault(),
        };

        return response()->json([]);
    }

    public function processTransaction(Request $request, array $data, string $raw): Response
    {
        $payload = $request->all();
        $data = $payload['eventData'];
        Log::info('monnify transaction webhook', [$data['paymentMethod']]);

        return response()->json([], JsonResponse::HTTP_OK);
    }

    public function processDefault()
    {
        return response()->json([], JsonResponse::HTTP_OK);
    }

    public function processChargeCompleted($reference, $ourReference)
    {
        $transaction = Http::monify()->get('v2/transactions/'.$reference);
        Log::info('monnify payment verification', [$transaction->json()]);

        if (! $transaction->successful()) {
            return response()->json([]);
        }
        Log::info('monnify payment verification', [$transaction->json()]);
    }
}
