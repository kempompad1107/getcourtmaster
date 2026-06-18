@extends(auth()->user()?->isCustomer() ? 'layouts.customer' : 'layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="container" style="max-width:780px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Notifications</h4>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary">Mark all read</button>
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $n)
                <a href="{{ $n->data['url'] ?? '#' }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex justify-content-between gap-3">
                        <span class="small" style="overflow-wrap:anywhere;">{{ $n->data['message'] ?? ($n->data['type'] ?? 'Notification') }}</span>
                        <span class="small text-muted text-nowrap flex-shrink-0">{{ $n->created_at?->diffForHumans() }}</span>
                    </div>
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-5">No notifications yet.</div>
            @endforelse
        </div>
        <div class="card-footer bg-white border-0">{{ $notifications->links() }}</div>
    </div>
</div>
@endsection
