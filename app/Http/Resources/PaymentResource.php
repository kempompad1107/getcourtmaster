<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'amount'             => (float) $this->amount,
            'status'             => $this->status,
            'gateway'            => $this->gateway,
            'gateway_reference'  => $this->gateway_reference,
            'receipt_number'     => $this->receipt_number,
            'paid_at'            => $this->paid_at?->toIso8601String(),
            'refunded_at'        => $this->refunded_at?->toIso8601String(),
            'payable'            => [
                'type' => class_basename($this->payable_type),
                'id'   => $this->payable_id,
            ],
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
