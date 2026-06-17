<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'court_id' => [
                'nullable',
                Rule::exists('courts', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $match = $this->route('match');
                    $tournament = $match?->tournament;
                    if ($tournament && ! $tournament->is_all_branches && $tournament->branch_id) {
                        $courtBranch = \App\Models\Court::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                            ->whereKey($value)->value('branch_id');
                        if ((int) $courtBranch !== (int) $tournament->branch_id) {
                            $fail('That court is not at this tournament\'s branch.');
                        }
                    }
                },
            ],
            'referee_name' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
