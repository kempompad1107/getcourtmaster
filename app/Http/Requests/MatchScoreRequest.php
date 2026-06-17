<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sets' => 'required|array|min:1|max:5',
            'sets.*.team1' => 'required|integer|min:0|max:99',
            'sets.*.team2' => 'required|integer|min:0|max:99',
            'winner_team_id' => 'required|integer',
            'override' => 'boolean',
        ];
    }
}
