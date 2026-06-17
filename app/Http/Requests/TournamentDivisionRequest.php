<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TournamentDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'skill_level' => 'nullable|string|max:50',
            'min_age' => 'nullable|integer|min:1|max:120',
            'max_age' => 'nullable|integer|min:1|max:120|gte:min_age',
            'gender' => 'required|in:men,women,mixed,open',
            'team_size' => 'required|integer|in:1,2',
            'max_entries' => 'nullable|integer|min:2|max:512',
            'entry_fee' => 'nullable|numeric|min:0|max:99999999',
            'bracket_format' => 'nullable|in:single_elimination,double_elimination,round_robin,group_stage,pool_play',
            'seeding_method' => 'required|in:random,manual,rating',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ];
    }

    public function messages(): array
    {
        return [
            'max_age.gte' => 'Maximum age must be greater than or equal to minimum age.',
            'team_size.in' => 'Team size must be 1 (singles) or 2 (doubles).',
        ];
    }
}
