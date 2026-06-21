@extends(auth()->user()?->isCustomer() ? 'layouts.customer' : 'layouts.app')

@section('title', 'Notifications')

@push('styles')
<style>
    /* Filter tab bar */
    .ntf-tab-bar { display: flex; gap: .25rem; flex-wrap: wrap; }
    .ntf-tab {
        padding: .35rem .9rem; border-radius: .5rem; font-size: .82rem; font-weight: 600;
        border: 1px solid var(--bs-border-color); background: transparent;
        color: var(--bs-secondary-color); text-decoration: none; transition: all .15s;
    }
    .ntf-tab:hover { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
    .ntf-tab.active { background: #10b981; border-color: #10b981; color: #fff; }
    .ntf-tab .badge {
        font-size: .65rem; padding: .18rem .4rem;
        background: rgba(255,255,255,.35) !important; color: inherit !important;
        vertical-align: middle; margin-left: .25rem;
    }
    .ntf-tab:not(.active) .badge { background: var(--bs-danger) !important; color: #fff !important; }

    /* Notification icon badge */
    .ntf-ico {
        width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1rem;
    }

    /* Unread indicator dot */
    .ntf-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #10b981;
        flex-shrink: 0; margin-top: 6px;
    }

    /* Row: unread left border accent */
    .ntf-row.unread { border-left: 3px solid #10b981 !important; }
    .ntf-row.read   { border-left: 3px solid transparent !important; }
</style>
@endpush

@section('content')
<div style="max-width:780px; margin:0 auto;">

    {{-- Page header --}}
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0">Notifications</h4>
            <p class="text-muted small mb-0">Stay up to date with your bookings and account activity.</p>
        </div>
        @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i>Mark all read
            </button>
        </form>
        @endif
    </div>

    {{-- Filter tabs --}}
    <div class="ntf-tab-bar mb-3">
        <a href="{{ route('notifications.index', ['filter' => 'all']) }}"
           class="ntf-tab {{ $filter === 'all' ? 'active' : '' }}">All</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
           class="ntf-tab {{ $filter === 'unread' ? 'active' : '' }}">
            Unread
            @if($unreadCount > 0)
                <span class="badge rounded-pill">{{ $unreadCount }}</span>
            @endif
        </a>
    </div>

    {{-- Notifications list --}}
    <div class="card">
        @if($notifications->isEmpty())
            <x-empty-state
                title="{{ $filter === 'unread' ? 'All caught up!' : 'No notifications yet' }}"
                description="{{ $filter === 'unread' ? 'You have no unread notifications.' : 'Notifications about your bookings and account will appear here.' }}"
                icon="bi-bell"/>
        @else
        <div class="list-group list-group-flush">
            @foreach($notifications as $n)
            @php
                $type    = $n->data['type'] ?? 'info';
                $message = $n->data['message'] ?? 'Notification';
                $url     = $n->data['url'] ?? '#';
                $isUnread = !$n->read_at;

                [$ico, $icoClass] = match(true) {
                    str_contains($type, 'booking_confirm')  => ['bi-calendar-check',    'bg-success bg-opacity-10 text-success'],
                    str_contains($type, 'booking_cancel')   => ['bi-calendar-x',        'bg-danger bg-opacity-10 text-danger'],
                    str_contains($type, 'booking_denied')   => ['bi-x-circle',          'bg-danger bg-opacity-10 text-danger'],
                    str_contains($type, 'booking_approv')   => ['bi-shield-check',      'bg-warning bg-opacity-10 text-warning'],
                    str_contains($type, 'booking_paid')     => ['bi-credit-card',       'bg-success bg-opacity-10 text-success'],
                    str_contains($type, 'booking_remind')   => ['bi-clock',             'bg-primary bg-opacity-10 text-primary'],
                    str_contains($type, 'booking_reschedu') => ['bi-calendar-event',    'bg-info bg-opacity-10 text-info'],
                    str_contains($type, 'booking_starting') => ['bi-hourglass-split',   'bg-warning bg-opacity-10 text-warning'],
                    str_contains($type, 'booking')          => ['bi-calendar3',         'bg-primary bg-opacity-10 text-primary'],
                    str_contains($type, 'membership_expir') => ['bi-patch-exclamation', 'bg-danger bg-opacity-10 text-danger'],
                    str_contains($type, 'membership_renew') => ['bi-arrow-repeat',      'bg-success bg-opacity-10 text-success'],
                    str_contains($type, 'membership')       => ['bi-person-badge',      'bg-purple bg-opacity-10 text-purple'],
                    str_contains($type, 'low_stock')        => ['bi-box-seam',          'bg-warning bg-opacity-10 text-warning'],
                    str_contains($type, 'report')           => ['bi-file-earmark-bar-graph', 'bg-info bg-opacity-10 text-info'],
                    str_contains($type, 'wallet')           => ['bi-wallet2',           'bg-success bg-opacity-10 text-success'],
                    str_contains($type, 'tournament')       => ['bi-trophy',            'bg-warning bg-opacity-10 text-warning'],
                    default                                 => ['bi-bell',              'bg-secondary bg-opacity-10 text-secondary'],
                };
            @endphp
            <div class="list-group-item ntf-row {{ $isUnread ? 'unread' : 'read' }} px-4 py-3">
                <div class="d-flex align-items-start gap-3">
                    {{-- Icon --}}
                    <div class="ntf-ico {{ $icoClass }}">
                        <i class="bi {{ $ico }}"></i>
                    </div>

                    {{-- Content --}}
                    <div class="flex-grow-1 min-w-0">
                        <a href="{{ $url }}"
                           class="small fw-{{ $isUnread ? 'semibold' : 'normal' }} text-body text-decoration-none stretched-link"
                           @if($isUnread) @endif>
                            {{ $message }}
                        </a>
                        <div class="small text-muted mt-1">
                            {{ $n->created_at?->diffForHumans() }}
                        </div>
                    </div>

                    {{-- Unread dot + mark-read --}}
                    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                        @if($isUnread)
                            <div class="ntf-dot"></div>
                            <form method="POST" action="{{ route('notifications.read', $n->id) }}" class="position-relative" style="z-index:2">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-muted" style="font-size:.7rem; white-space:nowrap">
                                    Mark read
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($notifications->hasPages())
            <div class="px-4 py-3 border-top">{{ $notifications->links() }}</div>
        @endif
        @endif
    </div>

</div>
@endsection
