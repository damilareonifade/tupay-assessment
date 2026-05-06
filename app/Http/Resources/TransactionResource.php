<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->getRawOriginal('type'),
            'amount' => $this->amount->whole(),
            'amount_subunits' => $this->getRawOriginal('amount'),
            'balance_after' => $this->balance->whole(),
            'currency' => $this->getRawOriginal('currency'),
            'action' => $this->getRawOriginal('action'),
            'remarks' => $this->remarks,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
