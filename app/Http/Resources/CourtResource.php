<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'type'        => $this->type ?? null,
            'status'      => $this->status,
            'hourly_rate' => (float) ($this->base_hourly_rate ?? 0),
            'branch_id'   => $this->branch_id,
            'amenities'   => $this->amenities ?? null,
            'is_active'   => (bool) ($this->is_active ?? true),
        ];
    }
}
