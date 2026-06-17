<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tournament;
use App\Models\TournamentTeamMember;

/**
 * Shapes tournament report data as ['rows' => [], 'headings' => [], 'totals' => []]
 * so one generic ReportExport class covers xlsx/csv and one PDF view covers print.
 */
class TournamentReportService
{
    /**
     * Mirrors ReportService::COLLECTED_STATUSES (private there): gross fee
     * collection must include refunded/partial rows — refunds never reduce
     * Payment.amount; they live in refund_amount.
     */
    public const COLLECTED_STATUSES = ['paid', 'refunded', 'partial'];

    public const TYPES = [
        'summary' => 'Tournament Summary',
        'participants' => 'Participant List',
        'teams' => 'Team List',
        'matches' => 'Match History',
        'winners' => 'Winners & Champions',
        'fees' => 'Entry Fee Collection',
    ];

    public function __construct(private readonly TournamentRankingService $rankings) {}

    public function build(string $type, Tournament $tournament, array $filters = []): array
    {
        return match ($type) {
            'summary' => $this->summary($tournament),
            'participants' => $this->participants($tournament, $filters['division_id'] ?? null),
            'teams' => $this->teams($tournament, $filters['division_id'] ?? null),
            'matches' => $this->matchHistory($tournament, $filters['division_id'] ?? null),
            'winners' => $this->winners($tournament),
            'fees' => $this->feeCollection($tournament),
            default => throw new \InvalidArgumentException("Unknown report type '{$type}'."),
        };
    }

    public function summary(Tournament $tournament): array
    {
        $rows = [];
        foreach ($tournament->divisions()->withCount([
            'teams as active_teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed']),
        ])->get() as $division) {
            $matchesTotal = $division->matches()->whereNotIn('status', ['bye', 'cancelled'])->count();
            $matchesDone = $division->matches()->whereIn('status', ['finished', 'walkover'])->count();
            $fees = $this->feesQuery($tournament)->where('tournament_team_members.division_id', $division->id)->sum('payments.amount');

            $rows[] = [
                'Division' => $division->name,
                'Type' => ($division->isSingles() ? 'Singles' : 'Doubles') . ' · ' . ucfirst($division->gender),
                'Format' => $division->formatLabel(),
                'Teams' => $division->active_teams,
                'Matches Played' => "{$matchesDone} / {$matchesTotal}",
                'Fees Collected' => number_format((float) $fees, 2),
            ];
        }

        return [
            'rows' => $rows,
            'headings' => ['Division', 'Type', 'Format', 'Teams', 'Matches Played', 'Fees Collected'],
            'totals' => [
                'Teams' => array_sum(array_column($rows, 'Teams')),
            ],
        ];
    }

    public function participants(Tournament $tournament, ?int $divisionId = null): array
    {
        $members = TournamentTeamMember::with(['user:id,name,email,phone,referral_code', 'team:id,name,status', 'division:id,name'])
            ->where('tournament_id', $tournament->id)
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->whereHas('team', fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'disqualified']))
            ->get()
            ->sortBy([['division.name', 'asc'], ['team.name', 'asc']]);

        $rows = $members->map(fn (TournamentTeamMember $m) => [
            'Player' => $m->user->name,
            'Member ID' => $m->user->referral_code ?? '—',
            'Email' => $m->user->email,
            'Mobile' => $m->user->phone ?? '—',
            'Division' => $m->division->name,
            'Team' => $m->team->name,
            'Skill' => $m->skill_level ?? '—',
            'Rating' => $m->rating !== null ? number_format((float) $m->rating, 2) : '—',
            'Fee Paid' => $m->hasPaid() ? 'Yes' : 'No',
        ])->values()->all();

        return [
            'rows' => $rows,
            'headings' => ['Player', 'Member ID', 'Email', 'Mobile', 'Division', 'Team', 'Skill', 'Rating', 'Fee Paid'],
            'totals' => ['Players' => count($rows)],
        ];
    }

    public function teams(Tournament $tournament, ?int $divisionId = null): array
    {
        $teams = $tournament->teams()
            ->with(['division:id,name', 'members.user:id,name'])
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->orderBy('division_id')->orderBy('seed')
            ->get();

        $rows = $teams->map(fn ($team) => [
            'Team' => $team->name,
            'Division' => $team->division->name,
            'Players' => $team->members->map(fn ($m) => $m->user->name)->implode(' / '),
            'Seed' => $team->seed ?? '—',
            'Status' => ucfirst($team->status),
            'Registered' => $team->created_at->format('Y-m-d'),
            'Via' => ucfirst($team->registered_via),
        ])->values()->all();

        return [
            'rows' => $rows,
            'headings' => ['Team', 'Division', 'Players', 'Seed', 'Status', 'Registered', 'Via'],
            'totals' => ['Teams' => count($rows)],
        ];
    }

    public function matchHistory(Tournament $tournament, ?int $divisionId = null): array
    {
        $matches = $tournament->matches()
            ->with(['division:id,name', 'team1:id,name', 'team2:id,name', 'winner:id,name', 'court:id,name'])
            ->whereIn('status', ['finished', 'walkover'])
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->orderBy('division_id')->orderBy('match_number')
            ->get();

        $rows = $matches->map(fn ($match) => [
            '#' => $match->match_number,
            'Division' => $match->division->name,
            'Round' => $match->round_name ?? "Round {$match->round}",
            'Team 1' => $match->team1?->name ?? '—',
            'Team 2' => $match->team2?->name ?? '—',
            'Score' => $match->scoreSummary(),
            'Winner' => $match->winner?->name ?? '—',
            'Court' => $match->court?->name ?? '—',
            'Referee' => $match->referee_name ?: '—',
            'Finished' => $match->finished_at?->format('Y-m-d H:i') ?? '—',
        ])->values()->all();

        return [
            'rows' => $rows,
            'headings' => ['#', 'Division', 'Round', 'Team 1', 'Team 2', 'Score', 'Winner', 'Court', 'Referee', 'Finished'],
            'totals' => ['Matches' => count($rows)],
        ];
    }

    public function winners(Tournament $tournament): array
    {
        $rows = [];
        foreach ($this->rankings->champions($tournament) as $entry) {
            $rows[] = [
                'Division' => $entry['division']->name,
                'Format' => $entry['division']->formatLabel(),
                'Champion' => $entry['champion']?->name ?? 'Not decided yet',
                'Champion Players' => $entry['champion']?->members->map(fn ($m) => $m->user->name)->implode(' / ') ?? '—',
                'Runner-up' => $entry['runner_up']?->name ?? '—',
            ];
        }

        return [
            'rows' => $rows,
            'headings' => ['Division', 'Format', 'Champion', 'Champion Players', 'Runner-up'],
            'totals' => [],
        ];
    }

    public function feeCollection(Tournament $tournament): array
    {
        $payments = $this->feesQuery($tournament)
            ->with(['customer:id,name', 'processedBy:id,name', 'payable.division:id,name', 'payable.team:id,name'])
            ->orderBy('payments.paid_at')
            ->get();

        $rows = $payments->map(fn (Payment $p) => [
            'Receipt' => $p->payment_number,
            'Player' => $p->customer?->name ?? '—',
            'Division' => $p->payable?->division?->name ?? '—',
            'Team' => $p->payable?->team?->name ?? '—',
            'Method' => strtoupper($p->method),
            'Amount' => number_format((float) $p->amount, 2),
            'Refunded' => number_format((float) $p->refund_amount, 2),
            'Net' => number_format((float) $p->amount - (float) $p->refund_amount, 2),
            'Status' => ucfirst($p->status),
            'Paid At' => $p->paid_at?->format('Y-m-d H:i') ?? '—',
            'Collected By' => $p->processedBy?->name ?? '—',
        ])->values()->all();

        $gross = (float) $payments->sum('amount');
        $refunds = (float) $payments->sum('refund_amount');

        return [
            'rows' => $rows,
            'headings' => ['Receipt', 'Player', 'Division', 'Team', 'Method', 'Amount', 'Refunded', 'Net', 'Status', 'Paid At', 'Collected By'],
            'totals' => [
                'Gross' => number_format($gross, 2),
                'Refunds' => number_format($refunds, 2),
                'Net' => number_format($gross - $refunds, 2),
                'By Method' => $payments->groupBy('method')
                    ->map(fn ($g) => number_format((float) $g->sum('amount'), 2))
                    ->all(),
            ],
        ];
    }

    private function feesQuery(Tournament $tournament)
    {
        return Payment::query()
            ->where('payments.payable_type', TournamentTeamMember::class)
            ->whereIn('payments.status', self::COLLECTED_STATUSES)
            ->join('tournament_team_members', 'tournament_team_members.id', '=', 'payments.payable_id')
            ->where('tournament_team_members.tournament_id', $tournament->id)
            ->select('payments.*');
    }
}
