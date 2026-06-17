<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'booking_number' => $this->booking_number,
            'type'           => $this->type,
            'status'         => $this->status,
            'date'           => optional($this->booking_date)->toDateString(),
            'start_time'     => $this->start_time,
            'end_time'       => $this->end_time,
            'duration'       => $this->duration_minutes,
            'amounts'        => [
                'base'     => (float) $this->base_amount,
                'addon'    => (float) $this->addon_amount,
                'discount' => (float) $this->discount_amount,
                'tax'      => (float) $this->tax_amount,
                'total'    => (float) $this->total_amount,
                'paid'     => (float) $this->paid_amount,
            ],
            'court'          => CourtResource::make($this->whenLoaded('court')),
            'customer'       => $this->whenLoaded('customer', fn () => [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'timer'          => $this->whenLoaded('timer'),
            'qr_code_url'    => $this->qr_code,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
