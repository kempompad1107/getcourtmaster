<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            // For create, branch_id is injected by the controller from the
            // active topbar branch context, so it doesn't need to be in the
            // submitted payload. For update (moving a court between branches)
            // the picker is still in the edit form and submits an explicit id.
            'branch_id' => [
                'sometimes',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'type' => 'required|in:indoor,outdoor,covered',
            'description' => 'nullable|string',
            'amenities' => 'nullable|array',
            'capacity' => 'required|integer|min:1|max:20',
            'base_hourly_rate' => 'required|numeric|min:0',
            'peak_hourly_rate' => 'nullable|numeric|min:0',
            'weekend_hourly_rate' => 'nullable|numeric|min:0',
            'min_booking_minutes' => 'required|integer|min:30',
            'max_booking_minutes' => 'required|integer|min:30',
            'buffer_minutes' => 'required|integer|min:0',
            'operating_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpeg,png,webp|max:5120',
        ];
    }
}
