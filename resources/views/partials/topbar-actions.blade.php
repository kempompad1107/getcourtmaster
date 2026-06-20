{{--
    Shared topbar action cluster: theme toggle + live notifications bell + profile menu.
    @param $profileRoute    string|null  Named route for the "Profile" link (default: null = hidden)
    @param $settingsRoute   string|null  Named route for "Settings" link (default: null = hidden)
    @param $twoFactorRoute  string|null  Named route for "Two-factor auth" link
    @param $devicesRoute    string|null  Named route for "Devices" link
--}}
@php
    $profileRoute   = $profileRoute   ?? null;
    $settingsRoute  = $settingsRoute  ?? null;
    $twoFactorRoute = $twoFactorRoute ?? null;
    $devicesRoute   = $devicesRoute   ?? null;
@endphp

<div class="topbar-actions">

    {{-- Branch switcher (only when more than one accessible branch) --}}
    @isset($availableBranches)
        @php $isAllBranches = ($canSeeAllBranches ?? false) && $activeBranchId === null; @endphp
        @if($availableBranches->count() > 1 || ($canSeeAllBranches ?? false))
        <div class="dropdown">
            <button class="topbar-btn d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown" aria-label="Switch branch"
                    @if($isAllBranches) title="Read-only — select a branch to make changes" @endif>
                <i class="bi {{ $isAllBranches ? 'bi-house' : 'bi-shop' }}"></i>
                <span class="d-none d-sm-inline text-truncate" style="max-width:140px">
                    {{ $activeBranch?->name ?? 'All branches' }}
                </span>
                @if($isAllBranches)
                    <span class="badge text-bg-warning d-none d-sm-inline" style="font-size:.55rem">read-only</span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:220px">
                @if($canSeeAllBranches ?? false)
                    <li>
                        <form method="POST" action="{{ route('admin.branch-context.update') }}">
                            @csrf
                            <button type="submit" class="dropdown-item @if($activeBranchId === null) active @endif">
                                <i class="bi bi-grid-3x3-gap me-2"></i>All branches
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                @endif
                @foreach($availableBranches as $b)
                    <li>
                        <form method="POST" action="{{ route('admin.branch-context.update') }}">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $b->id }}">
                            <button type="submit" class="dropdown-item @if($activeBranchId === $b->id) active @endif">
                                <i class="bi bi-shop me-2"></i>{{ $b->name }}
                                @if($b->is_main)
                                    <span class="badge text-bg-primary ms-2" style="font-size:.6rem">Main</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    @endisset

    {{-- Theme toggle --}}
    <button class="topbar-btn" @click="$store.theme.toggle()" aria-label="Toggle theme">
        <i class="bi" :class="$store.theme.dark ? 'bi-sun' : 'bi-moon'"></i>
    </button>

    {{-- Live notifications dropdown --}}
    <div
        class="dropdown"
        x-data="topbarNotifications()"
        x-init="load(); setInterval(() => load(), 30000)"
    >
        <button class="topbar-btn position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span
                x-show="count > 0"
                x-text="count > 99 ? '99+' : count"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                style="font-size:.6rem; padding:.2rem .35rem;"
            ></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width:320px; max-width:380px;">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong class="small">Notifications</strong>
                <form method="POST" :action="readAllUrl" @submit="setTimeout(load, 200)">
                    @csrf
                    <button class="btn btn-link btn-sm p-0 text-decoration-none text-success" type="submit">
                        Mark all read
                    </button>
                </form>
            </div>
            <template x-if="items.length === 0">
                <div class="px-3 py-4 text-center text-muted small">You're all caught up.</div>
            </template>
            <template x-for="item in items" :key="item.id">
                <a class="dropdown-item d-flex flex-column py-2" :href="item.url || '#'"
                   style="white-space:normal;">
                    <span class="small fw-medium" x-text="item.message"
                          style="overflow-wrap:anywhere;"></span>
                    <span class="small text-muted" x-text="item.created"></span>
                </a>
            </template>
            <div class="border-top">
                <a class="dropdown-item small text-center text-decoration-none py-2"
                   href="{{ route('notifications.index') }}">
                    View all
                </a>
            </div>
        </div>
    </div>

    {{-- Profile dropdown --}}
    <div class="dropdown">
        <button class="d-flex align-items-center gap-2 btn btn-link text-decoration-none p-1 rounded-2"
                data-bs-toggle="dropdown" aria-label="Profile menu">
            <img src="{{ auth()->user()->avatar_url }}" alt=""
                 class="rounded-circle" style="width:30px;height:30px;object-fit:cover">
            <span class="d-none d-sm-inline small fw-medium text-body"
                  style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ auth()->user()->name }}
            </span>
            <i class="bi bi-chevron-down d-none d-sm-inline"
               style="font-size:.65rem;color:var(--bs-secondary-color)"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg">
            <li class="px-3 py-2 border-bottom">
                <div class="fw-semibold small">{{ auth()->user()->name }}</div>
                <div class="text-muted" style="font-size:.75rem">{{ auth()->user()->email }}</div>
            </li>

            @if ($profileRoute)
                <li>
                    <a class="dropdown-item" href="{{ route($profileRoute) }}">
                        <i class="bi bi-person me-2"></i> Profile
                    </a>
                </li>
            @endif

            @if ($settingsRoute)
                <li>
                    <a class="dropdown-item" href="{{ route($settingsRoute) }}">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>
                </li>
            @endif

            @if ($twoFactorRoute)
                <li>
                    <a class="dropdown-item" href="{{ route($twoFactorRoute) }}">
                        <i class="bi bi-shield-lock me-2"></i> Two-factor auth
                    </a>
                </li>
            @endif

            @if ($devicesRoute)
                <li>
                    <a class="dropdown-item" href="{{ route($devicesRoute) }}">
                        <i class="bi bi-laptop me-2"></i> Devices
                    </a>
                </li>
            @endif

            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-box-arrow-right me-2"></i> Sign out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function topbarNotifications() {
            return {
                count: 0,
                items: [],
                readAllUrl: @json(route('notifications.read-all')),
                async load() {
                    try {
                        const r = await fetch(@json(route('notifications.dropdown')), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!r.ok) return;
                        const j = await r.json();
                        this.count = j.unread_count || 0;
                        this.items = j.items || [];
                    } catch (e) { /* silent */ }
                },
            };
        }
    </script>
    @endpush
@endonce
