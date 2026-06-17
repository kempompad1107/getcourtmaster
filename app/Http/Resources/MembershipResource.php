<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'membership_number' => $this->membership_number,
            'status'            => $this->status,
            'remaining_credits' => (int) ($this->remaining_credits ?? 0),
            'starts_at'         => $this->starts_at?->toIso8601String(),
            'expires_at'        => $this->expires_at?->toIso8601String(),
            'plan'              => $this->whenLoaded('plan', fn () => [
                'id'   => $this->plan->id,
                'name' => $this->plan->name,
                'price'=> (float) $this->plan->price,
            ]),
        ];
    }
}
