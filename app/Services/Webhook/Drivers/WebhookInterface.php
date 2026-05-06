<?php

namespace App\Services\Webhook\Drivers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface WebhookInterface
{
    /**
     * Get driver name
     */
    public function name(): string;

    /**
     * Validete webhook
     */
    public function validate(Request $request, array $data, string $raw): bool;

    /**
     * Process webhook
     */
    public function process(Request $request, array $data, string $raw): Response;
}
