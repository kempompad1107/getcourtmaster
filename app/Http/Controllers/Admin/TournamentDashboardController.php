<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamMember;
use Illuminate\Support\Facades\DB;

class TournamentDashboardController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Tournament::class);
        $tenantId = $this->authTenant()->id;

        $stats = [
            'active' => Tournament::notArchived()->whereIn('status', ['registration_open', 'registration_closed', 'ongoing'])->count(),
            'registration_open' => Tournament::notArchived()->where('status', 'registration_open')->count(),
            'teams' => TournamentTeam::whereIn('status', ['pending', 'confirmed'])
                ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
                ->count(),
            'matches_today' => TournamentMatch::whereDate('scheduled_at', today())
                ->whereNotIn('status', ['bye', 'cancelled'])
                ->count(),
            'fees_month' => Payment::where('payable_type', TournamentTeamMember::class)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['paid', 'refunded', 'partial'])
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        $todayMatches = TournamentMatch::with(['tournament:id,name', 'division:id,name', 'team1:id,name', 'team2:id,name', 'court:id,name'])
            ->whereDate('scheduled_at', today())
            ->whereNotIn('status', ['bye', 'cancelled'])
            ->orderByRaw("FIELD(status, 'playing', 'called', 'scheduled', 'pending', 'finished', 'walkover')")
            ->orderBy('scheduled_at')
            ->limit(12)
            ->get();

        $upcoming = Tournament::notArchived()
            ->whereIn('status', ['draft', 'registration_open', 'registration_closed', 'ongoing'])
            ->withCount(['teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->orderByRaw('starts_at IS NULL, starts_at ASC')
            ->limit(6)
            ->get();

        // Registrations per day, last 14 days (for the trend chart).
        $registrationTrend = TournamentTeam::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $trendDays = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->format('Y-m-d'));
        $trend = [
            'labels' => $trendDays->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->values(),
            'data' => $trendDays->map(fn ($d) => (int) ($registrationTrend[$d] ?? 0))->values(),
        ];

        $recentActivity = DB::table('activity_log')
            ->whereIn('causer_id', \App\Models\User::where('tenant_id', $tenantId)->pluck('id'))
            ->where('log_name', 'like', 'tournament%')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('admin.tournaments.dashboard', compact('stats', 'todayMatches', 'upcoming', 'trend', 'recentActivity'));
    }
}
