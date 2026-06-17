<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BracketGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => 'required|in:single_elimination,double_elimination,round_robin,group_stage,pool_play',
            'seeding_method' => 'required|in:random,manual,rating',
            'group_count' => 'nullable|integer|min:1|max:26',
            'advance_per_group' => 'nullable|integer|min:1|max:8',
            'double_round_robin' => 'boolean',
            'knockout' => 'boolean',
            'force' => 'boolean',
        ];
    }
}
