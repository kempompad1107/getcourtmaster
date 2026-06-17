<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'nullable|string|max:150',
            'members' => 'required|array|min:1|max:2',
            'members.*.user_id' => [
                'required', 'distinct',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->where('user_type', 'customer')
                    ->where('is_active', true)),
            ],
            'members.*.skill_level' => 'nullable|string|max:50',
            'members.*.rating' => 'nullable|numeric|min:0|max:10',
            'collect_method' => 'nullable|in:cash,wallet',
        ];
    }

    public function messages(): array
    {
        return [
            'members.*.user_id.exists' => 'One of the selected members is not an active customer of this venue.',
            'members.*.user_id.distinct' => 'The same member cannot fill both team slots.',
        ];
    }
}
