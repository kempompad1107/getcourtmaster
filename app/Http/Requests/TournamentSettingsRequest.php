<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TournamentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points_to_win' => 'required|integer|min:1|max:99',
            'win_by_2' => 'boolean',
            'best_of' => 'required|integer|in:1,3,5',
            'default_match_duration' => 'required|integer|min:10|max:240',
            'court_count' => 'required|integer|min:1|max:50',
            'auto_generate_brackets' => 'boolean',
            'allow_late_registration' => 'boolean',
            'enable_public_registration' => 'boolean',
        ];
    }

    /** Normalized settings payload with checkboxes cast to booleans. */
    public function settings(): array
    {
        $data = $this->validated();
        foreach (['win_by_2', 'auto_generate_brackets', 'allow_late_registration', 'enable_public_registration'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }
        $data['points_to_win'] = (int) $data['points_to_win'];
        $data['best_of'] = (int) $data['best_of'];
        $data['default_match_duration'] = (int) $data['default_match_duration'];
        $data['court_count'] = (int) $data['court_count'];
        return $data;
    }
}
