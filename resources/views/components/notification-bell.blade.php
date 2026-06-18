@auth
<li
    class="nav-item dropdown"
    x-data="notificationBell()"
    x-init="load(); setInterval(() => load(), 30000)"
>
    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
        <i class="bi bi-bell fs-5"></i>
        <span
            x-show="count > 0"
            x-text="count > 99 ? '99+' : count"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style="font-size:.65rem;"
        ></span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:320px; max-width:380px;">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong class="small">Notifications</strong>
            <form method="POST" :action="readAllUrl" @submit="setTimeout(load, 200)">
                @csrf
                <button class="btn btn-link btn-sm p-0 text-decoration-none" type="submit">Mark all read</button>
            </form>
        </div>
        <template x-if="items.length === 0">
            <div class="px-3 py-4 text-center text-muted small">No notifications.</div>
        </template>
        <template x-for="item in items" :key="item.id">
            <a class="dropdown-item d-flex flex-column" :href="item.url || '#'"
               style="white-space:normal;">
                <span class="small fw-medium" x-text="item.message"
                      style="overflow-wrap:anywhere;"></span>
                <span class="small text-muted" x-text="item.created"></span>
            </a>
        </template>
        <div class="border-top">
            <a class="dropdown-item small text-center text-decoration-none" href="{{ route('notifications.index') }}">
                View all
            </a>
        </div>
    </div>
</li>

@push('scripts')
<script>
    function notificationBell() {
        return {
            count: 0,
            items: [],
            readAllUrl: @json(route('notifications.read-all')),
            async load() {
                try {
                    const r = await fetch(@json(route('notifications.dropdown')), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const j = await r.json();
                    this.count = j.unread_count || 0;
                    this.items = j.items || [];
                } catch (e) { /* silent */ }
            },
        };
    }
</script>
@endpush
@endauth
