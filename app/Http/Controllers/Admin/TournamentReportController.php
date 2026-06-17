<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\TournamentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TournamentReportController extends Controller
{
    public function __construct(private readonly TournamentReportService $reports) {}

    public function index(Request $request)
    {
        $user = $this->authUser();
        abort_unless($user->isBusinessOwner() || $user->can('tournaments.reports'), 403);

        $tournaments = Tournament::withCount(['teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->orderByDesc('starts_at')
            ->get();

        return view('admin.tournaments.reports.index', [
            'tournaments' => $tournaments,
            'types' => TournamentReportService::TYPES,
        ]);
    }

    public function show(Request $request, Tournament $tournament, string $type)
    {
        $this->authorize('viewReports', $tournament);
        abort_unless(array_key_exists($type, TournamentReportService::TYPES), 404);

        $report = $this->reports->build($type, $tournament, [
            'division_id' => $request->integer('division_id') ?: null,
        ]);

        return view('admin.tournaments.reports.show', [
            'tournament' => $tournament,
            'type' => $type,
            'typeLabel' => TournamentReportService::TYPES[$type],
            'report' => $report,
            'divisions' => $tournament->divisions()->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, Tournament $tournament, string $type)
    {
        $this->authorize('viewReports', $tournament);
        abort_unless(array_key_exists($type, TournamentReportService::TYPES), 404);

        $format = $request->input('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 400);

        $report = $this->reports->build($type, $tournament, [
            'division_id' => $request->integer('division_id') ?: null,
        ]);

        $title = TournamentReportService::TYPES[$type];
        $filename = str(\Illuminate\Support\Str::slug("{$tournament->name}-{$type}"))->limit(80, '')->toString();

        if ($format === 'pdf') {
            return Pdf::loadView('admin.tournaments.reports.pdf', [
                'tournament' => $tournament,
                'typeLabel' => $title,
                'report' => $report,
                'generatedAt' => now(),
            ])->setPaper('a4', count($report['headings']) > 6 ? 'landscape' : 'portrait')
              ->download("{$filename}.pdf");
        }

        return Excel::download(
            new ReportExport($report['rows'], $report['headings'], $title),
            "{$filename}.{$format}"
        );
    }
}
