@extends('layouts.app')
@section('title', $staff->name)

@section('content')

<x-page-header :title="$staff->name" subtitle="Staff Profile" :back="route('admin.staff.index')">
    <x-slot name="actions">
        <a href="{{ route('admin.staff.edit', $staff) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </x-slot>
</x-page-header>

{{-- Profile card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-start gap-4 flex-wrap">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold fs-4"
                 style="width:64px;height:64px;background:rgba(16,185,129,.12);color:#059669">
                {{ strtoupper(substr($staff->name, 0, 1)) }}
            </div>
            <div class="flex-grow-1 min-w-0">
                <h5 class="fw-semibold mb-1">{{ $staff->name }}</h5>
                <div class="d-flex flex-wrap gap-2 small text-muted mb-2">
                    <span><i class="bi bi-envelope me-1"></i>{{ $staff->email }}</span>
                    @if($staff->phone)
                    <span><i class="bi bi-phone me-1"></i>{{ $staff->phone }}</span>
                    @endif
                    <span><i class="bi bi-calendar me-1"></i>Joined {{ $staff->created_at->format('F Y') }}</span>
                </div>
                @foreach($staff->roles as $role)
                <span class="badge text-bg-secondary fw-normal me-1">
                    {{ str_replace('_', ' ', ucfirst($role->name)) }}
                </span>
                @endforeach
            </div>
            <div class="flex-shrink-0">
                <x-badge :status="$staff->is_active ? 'active' : 'expired'">{{ $staff->is_active ? 'Active' : 'Inactive' }}</x-badge>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-6 col-sm-3">
                <p class="text-muted small mb-0">Role</p>
                <p class="fw-semibold mb-0">{{ ucwords(str_replace('_', ' ', $staff->roles->first()?->name ?? 'Staff')) }}</p>
            </div>
            <div class="col-6 col-sm-3">
                <p class="text-muted small mb-0">Joined</p>
                <p class="fw-semibold mb-0">{{ $staff->created_at->format('M j, Y') }}</p>
            </div>
            <div class="col-6 col-sm-3">
                <p class="text-muted small mb-0">Total shifts</p>
                <p class="fw-semibold mb-0">{{ $attendanceSummary['total_shifts'] }}</p>
            </div>
            <div class="col-6 col-sm-3">
                <p class="text-muted small mb-0">Hours this month</p>
                <p class="fw-semibold mb-0">{{ number_format($attendanceSummary['hours_this_month'], 1) }}h</p>
            </div>
        </div>
    </div>
</div>

{{-- Currently clocked in banner --}}
@php $lastAttendance = $recentAttendance->first(); @endphp
@if($lastAttendance && $lastAttendance->clocked_in_at && !$lastAttendance->clocked_out_at)
<div class="alert alert-success d-flex align-items-center justify-content-between mb-4" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-circle-fill text-success" style="font-size:.6rem"></i>
        <div>
            <strong>Currently clocked in</strong>
            <span class="ms-2 small">Since {{ $lastAttendance->clocked_in_at->format('g:i A') }}</span>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.staff.clock-out') }}">
        @csrf
        <button class="btn btn-success btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>Clock Out
        </button>
    </form>
</div>
@endif

<div class="row g-4">
    {{-- Recent attendance --}}
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Recent Attendance</h6>
                <a href="{{ route('admin.staff.shifts') }}" class="btn btn-link btn-sm p-0">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentAttendance as $record)
                        <tr>
                            <td class="small">{{ $record->shift_date->format('M j, Y') }}</td>
                            <td class="small font-monospace">{{ $record->clocked_in_at?->format('g:i A') ?? '—' }}</td>
                            <td class="small font-monospace">{{ $record->clocked_out_at?->format('g:i A') ?? '—' }}</td>
                            <td class="small fw-medium">
                                @if($record->clocked_in_at && $record->clocked_out_at)
                                {{ number_format($record->duration_minutes / 60, 1) }}h
                                @elseif($record->clocked_in_at)
                                <span class="text-success">Active</span>
                                @else
                                <span class="text-muted">{{ ucfirst($record->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state title="No attendance records" icon="bi-calendar-x"/>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent bookings handled --}}
    <div class="col-12 col-lg-5">
        @if(isset($recentBookings) && $recentBookings->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Recent Bookings Handled</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Court</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentBookings as $booking)
                        <tr>
                            <td class="small">{{ $booking->booking_date->format('M j, Y') }}</td>
                            <td class="small">{{ $booking->customer->name ?? '—' }}</td>
                            <td class="small">{{ $booking->court->name ?? '—' }}</td>
                            <td><x-badge :status="$booking->status">{{ ucfirst($booking->status) }}</x-badge></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
