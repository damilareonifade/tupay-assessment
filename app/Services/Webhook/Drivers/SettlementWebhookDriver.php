<?php

namespace App\Services\Webhook\Drivers;

use App\Jobs\ProcessSettlementWebhook;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettlementWebhookDriver implements WebhookInterface
{
    public function name(): string
    {
        return 'settlement';
    }

    /**
     * Verify the HMAC-SHA256 signature from the settlement partner.
     * Header format: X-Webhook-Signature: sha256=<hex>
     */
    public function validate(Request $request, array $data, string $raw): bool
    {
        $signature = $request->header('X-Webhook-Signature');

        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $raw, config('services.webhook_secret'));

        return hash_equals($expected, $signature);
    }

    public function process(Request $request, array $data, string $raw): Response
    {
        $providerReference = $data['provider_reference'] ?? null;
        $session = $data['transaction_session'] ?? null;
        $status = $data['status'] ?? null;

        if (! $providerReference || ! $session || ! $status) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Missing required fields.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Idempotency: skip dispatch if this reference was already settled.
        if (Transaction::where('provider_reference', $providerReference)->exists()) {
            return response()->json([
                'status' => 'received',
                'message' => 'Already processed.',
            ], JsonResponse::HTTP_OK);
        }

        ProcessSettlementWebhook::dispatch($data);

        return response()->json([
            'status' => 'queued',
            'message' => 'Webhook received and queued for processing.',
        ], JsonResponse::HTTP_OK);
    }
}
