<?php

namespace Backpack\Profile\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletLedgerResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->type_label,
            'amount' => [
                'value' => $this->amount,
                'formatted' => number_format($this->amount, 2),
                'currency' => $this->currency,
            ],
            'reference' => [
                'type' => $this->reference_type,
                'id' => $this->reference_id,
            ],
            'operation_details' => $this->when(
                isset($this->operation_details),
                $this->operation_details
            ),
            'meta' => $this->meta,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}