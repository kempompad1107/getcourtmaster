@extends('layouts.app')
@section('title', $typeLabel . ' — ' . $tournament->name)

@section('content')

<x-page-header :title="$typeLabel" :subtitle="$tournament->name" :back="route('admin.tournaments.reports.index')">
    <x-slot name="actions">
        @foreach([['pdf', 'bi-file-pdf', 'PDF'], ['xlsx', 'bi-file-excel', 'Excel'], ['csv', 'bi-file-text', 'CSV']] as [$format, $icon, $label])
        <a href="{{ route('admin.tournaments.reports.export', [$tournament, $type, 'format' => $format, 'division_id' => request('division_id')]) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi {{ $icon }} me-1"></i>{{ $label }}
        </a>
        @endforeach
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </x-slot>
</x-page-header>

@if(in_array($type, ['participants', 'teams', 'matches'], true) && $divisions->isNotEmpty())
<form method="GET" class="mb-3">
    <div class="d-flex gap-2 align-items-center">
        <select name="division_id" class="form-select form-select-sm" style="max-width:280px" onchange="this.form.submit()">
            <option value="">All divisions</option>
            @foreach($divisions as $d)
            <option value="{{ $d->id }}" @selected((int) request('division_id') === $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>
</form>
@endif

@if(!empty($report['totals']))
<div class="d-flex flex-wrap gap-3 mb-3">
    @foreach($report['totals'] as $label => $value)
        @if(is_array($value))
            @foreach($value as $sub => $subValue)
            <div class="card px-3 py-2">
                <small class="text-muted text-uppercase" style="font-size:.65rem;">{{ $label }} — {{ strtoupper($sub) }}</small>
                <span class="fw-bold">{{ $subValue }}</span>
            </div>
            @endforeach
        @else
        <div class="card px-3 py-2">
            <small class="text-muted text-uppercase" style="font-size:.65rem;">{{ $label }}</small>
            <span class="fw-bold">{{ $value }}</span>
        </div>
        @endif
    @endforeach
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @foreach($report['headings'] as $heading)
                    <th class="small">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                <tr>
                    @foreach($report['headings'] as $heading)
                    <td class="small">{{ $row[$heading] ?? '' }}</td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($report['headings']) }}">
                        <x-empty-state title="No data yet" description="This report fills in as the tournament progresses." icon="bi-clipboard-data"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
