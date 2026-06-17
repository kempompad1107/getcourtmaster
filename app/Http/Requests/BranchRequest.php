<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        $slug = $this->input('slug') ?: Str::slug($name);

        $this->merge([
            'slug'      => $slug,
            'is_main'   => $this->boolean('is_main'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function rules(): array
    {
        $tenantId  = $this->user()->tenant_id;
        $branchId  = $this->route('branch')?->id;

        return [
            'name'    => 'required|string|max:120',
            'slug'    => [
                'required', 'string', 'max:140', 'alpha_dash',
                Rule::unique('branches', 'slug')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($branchId),
            ],
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:120',
            'phone'     => 'nullable|string|max:40',
            'email'     => 'nullable|email|max:160',
            'map_url'   => 'nullable|url|max:500',
            'is_main'   => 'boolean',
            'is_active' => 'boolean',

            'operating_hours'                   => 'nullable|array',
            'operating_hours.*.is_open'         => 'sometimes|boolean',
            'operating_hours.*.open'            => 'nullable|date_format:H:i',
            'operating_hours.*.close'           => 'nullable|date_format:H:i|after:operating_hours.*.open',
        ];
    }
}
