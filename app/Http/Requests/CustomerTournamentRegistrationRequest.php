<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerTournamentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'partner_user_id' => [
                'nullable', 'integer',
                Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->where('user_type', 'customer')
                    ->where('is_active', true)),
            ],
            'skill_level' => 'nullable|string|max:50',
            'rating' => 'nullable|numeric|min:0|max:10',
            'partner_skill_level' => 'nullable|string|max:50',
            'partner_rating' => 'nullable|numeric|min:0|max:10',
            'team_name' => 'nullable|string|max:150',
            'pay_with_wallet' => 'boolean',
            'waiver_accepted' => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_user_id.not_in' => 'You cannot pick yourself as your partner.',
            'partner_user_id.exists' => 'That partner is not an active member of this venue.',
            'waiver_accepted.accepted' => 'You must accept the waiver to register.',
        ];
    }
}
