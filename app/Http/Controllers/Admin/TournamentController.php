<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Http\Requests\TournamentSettingsRequest;
use App\Models\Payment;
use App\Models\Tournament;
use App\Models\TournamentTeamMember;
use App\Services\BranchContext;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function __construct(private readonly FileStorageService $files) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $tournaments = Tournament::query()
            ->withCount(['divisions', 'teams'])
            ->when(!$request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->boolean('archived'), fn ($q) => $q->whereNotNull('archived_at'))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->visibility, fn ($q, $v) => $q->where('visibility', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('venue', 'like', "%{$v}%")
                  ->orWhere('organizer_name', 'like', "%{$v}%");
            }))
            ->latest('starts_at')->latest('id')
            ->paginate(15);

        return view('admin.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        $this->authorize('create', Tournament::class);
        return view('admin.tournaments.create', [
            'defaultCurrency' => $this->authTenant()->currency ?: 'PHP',
            'branches' => app(BranchContext::class)->available(),
        ]);
    }

    public function store(TournamentRequest $request)
    {
        $this->authorize('create', Tournament::class);
        $tenant = $this->authTenant();

        $data = collect($request->validated())->except(['cover_image', 'logo'])->all();
        $folder = FileStorageService::FOLDER_TOURNAMENTS . "/{$tenant->id}";

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->files->uploadFile($request->file('cover_image'), $folder);
        }
        if ($request->hasFile('logo')) {
            $data['logo'] = $this->files->uploadFile($request->file('logo'), $folder);
        }

        $tournament = Tournament::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'created_by' => $this->authUser()->id,
            'settings' => Tournament::DEFAULT_SETTINGS,
        ]));

        activity()->on($tournament)->log('Tournament created');

        return redirect()->route('admin.tournaments.show', $tournament)
            ->with('success', "Tournament '{$tournament->name}' created. Add divisions next.");
    }

    public function show(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $tournament->load([
            'divisions' => fn ($q) => $q->withCount(['teams', 'matches']),
        ]);

        $stats = [
            'divisions' => $tournament->divisions->count(),
            'teams' => $tournament->teams()->whereIn('status', ['pending', 'confirmed'])->count(),
            'players' => TournamentTeamMember::where('tournament_id', $tournament->id)->count(),
            'matches_done' => $tournament->matches()->whereIn('status', ['finished', 'walkover'])->count(),
            'matches_total' => $tournament->matches()->whereNotIn('status', ['bye', 'cancelled'])->count(),
            'fees_collected' => Payment::where('payable_type', TournamentTeamMember::class)
                ->whereIn('payable_id', TournamentTeamMember::where('tournament_id', $tournament->id)->pluck('id'))
                ->whereIn('status', ['paid', 'refunded', 'partial'])
                ->sum('amount'),
        ];

        $teams = $tournament->teams()
            ->with(['members.user', 'division'])
            ->whereIn('status', ['pending', 'confirmed', 'disqualified'])
            ->orderBy('division_id')->orderBy('name')
            ->get();

        return view('admin.tournaments.show', compact('tournament', 'stats', 'teams'));
    }

    public function edit(Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        return view('admin.tournaments.edit', [
            'tournament' => $tournament,
            'defaultCurrency' => $this->authTenant()->currency ?: 'PHP',
            'branches' => app(BranchContext::class)->available(),
        ]);
    }

    public function update(TournamentRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = collect($request->validated())->except(['cover_image', 'logo'])->all();
        $folder = FileStorageService::FOLDER_TOURNAMENTS . "/{$tournament->tenant_id}";

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->files->replaceFile($request->file('cover_image'), $tournament->cover_image, $folder);
        }
        if ($request->hasFile('logo')) {
            $data['logo'] = $this->files->replaceFile($request->file('logo'), $tournament->logo, $folder);
        }

        $tournament->update($data);

        return redirect()->route('admin.tournaments.show', $tournament)
            ->with('success', "Tournament '{$tournament->name}' updated.");
    }

    public function destroy(Tournament $tournament)
    {
        $this->authorize('delete', $tournament);

        if ($tournament->matches()->whereIn('status', ['finished', 'walkover'])->exists()) {
            return back()->with('error', 'This tournament has recorded results. Archive it instead of deleting.');
        }

        $tournament->delete();

        return redirect()->route('admin.tournaments.index')
            ->with('success', "Tournament '{$tournament->name}' deleted.");
    }

    public function duplicate(Tournament $tournament)
    {
        $this->authorize('duplicate', $tournament);

        $copy = $tournament->replicate([
            'slug', 'status', 'archived_at',
            'registration_opens_at', 'registration_closes_at', 'starts_at', 'ends_at',
        ]);
        $copy->name = "{$tournament->name} (Copy)";
        $copy->slug = Tournament::uniqueSlug($copy->name, $tournament->tenant_id);
        $copy->status = 'draft';
        $copy->created_by = $this->authUser()->id;
        $copy->save();

        foreach ($tournament->divisions as $division) {
            $divisionCopy = $division->replicate(['bracket_generated_at']);
            $divisionCopy->tournament_id = $copy->id;
            $divisionCopy->save();
        }

        activity()->on($copy)->log("Duplicated from tournament #{$tournament->id}");

        return redirect()->route('admin.tournaments.edit', $copy)
            ->with('success', "Duplicated as '{$copy->name}'. Review the dates before publishing.");
    }

    public function publish(Tournament $tournament)
    {
        $this->authorize('publish', $tournament);

        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Only draft tournaments can be published.');
        }
        if ($tournament->divisions()->count() === 0) {
            return back()->with('error', 'Add at least one division before publishing.');
        }

        $tournament->update(['status' => 'registration_open']);

        return back()->with('success', "'{$tournament->name}' is now open for registration.");
    }

    public function archive(Tournament $tournament)
    {
        $this->authorize('archive', $tournament);

        $tournament->update(['archived_at' => $tournament->archived_at ? null : now()]);

        return back()->with('success', $tournament->archived_at
            ? "'{$tournament->name}' archived."
            : "'{$tournament->name}' restored from archive.");
    }

    public function updateStatus(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $request->validate(['status' => 'required|in:' . implode(',', Tournament::STATUSES)]);

        if (!$tournament->canTransitionTo($request->status)) {
            return back()->with('error', "Cannot move from '{$tournament->status}' to '{$request->status}'.");
        }

        $tournament->update(['status' => $request->status]);

        return back()->with('success', 'Tournament status updated to ' . str_replace('_', ' ', $request->status) . '.');
    }

    public function updateSettings(TournamentSettingsRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $tournament->update([
            'settings' => array_merge($tournament->settings ?? [], $request->settings()),
        ]);

        return back()->with('success', 'Tournament settings saved.');
    }
}
