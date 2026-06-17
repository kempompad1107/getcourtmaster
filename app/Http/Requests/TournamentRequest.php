<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\BranchContext;
use Illuminate\Validation\Rule;

class TournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policies enforce access; the form request validates shape.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A checkbox is absent when unchecked; coerce to a real boolean and
        // clear any stale branch when the tournament is open to all branches.
        $allBranches = $this->boolean('is_all_branches');
        $this->merge([
            'is_all_branches' => $allBranches,
            'branch_id' => $allBranches ? null : $this->input('branch_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'cover_image' => 'nullable|image|mimes:jpeg,png,webp|max:5120',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:5120',
            'venue' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'google_maps_url' => 'nullable|url|max:255',
            'organizer_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email|max:255',
            'registration_opens_at' => 'nullable|date',
            'registration_closes_at' => 'nullable|date|after_or_equal:registration_opens_at',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'max_participants' => 'nullable|integer|min:2|max:10000',
            'rules' => 'nullable|string|max:65000',
            'waiver' => 'nullable|string|max:65000',
            'entry_fee' => 'required|numeric|min:0|max:99999999',
            'currency' => 'required|string|size:3',
            'visibility' => 'required|in:public,private',
            'is_all_branches' => 'required|boolean',
            'branch_id' => [
                'nullable',
                'required_if:is_all_branches,false',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->whereNull('deleted_at')),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $allowed = app(BranchContext::class)->allowedBranchIds($this->user());
                    if (! in_array((int) $value, $allowed, true)) {
                        $fail('You can only assign a tournament to a branch you manage.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_closes_at.after_or_equal' => 'Registration must close on or after it opens.',
            'ends_at.after_or_equal' => 'The tournament must end on or after it starts.',
            'branch_id.required_if' => 'Pick the branch this tournament is exclusive to.',
        ];
    }
}
