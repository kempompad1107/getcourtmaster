@extends('layouts.app')

@section('title', 'Devices')

@section('content')
<div class="container" style="max-width:760px;">
    <h4 class="mb-3">Signed-in devices</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($sessions as $s)
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <strong>
                            {{ $s->device_label ?? 'Unknown device' }}
                            @if ($s->session_id === $currentSessionId)
                                <span class="badge bg-success-subtle text-success-emphasis ms-1">This device</span>
                            @endif
                        </strong>
                        <div class="small text-muted">{{ $s->ip }} · {{ $s->last_active_at?->diffForHumans() ?? '—' }}</div>
                        <div class="small text-muted text-truncate" style="max-width:480px;" title="{{ $s->user_agent }}">{{ $s->user_agent }}</div>
                    </div>
                    @if ($s->session_id !== $currentSessionId)
                        <form method="POST" action="{{ route('devices.destroy', $s) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Sign out</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">No active devices.</div>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('devices.destroy-others') }}" class="mt-3">
        @csrf @method('DELETE')
        <input type="password" name="password" class="form-control mb-2" placeholder="Confirm password" required>
        <button class="btn btn-outline-danger">Sign out all other devices</button>
    </form>
</div>
@endsection
