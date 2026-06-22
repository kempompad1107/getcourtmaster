{{--
    Shared sidebar footer: avatar + name + email + desktop collapse button.
    Used by admin, super, and customer layouts.
--}}
{{-- PWA install button — shown only when the browser fires beforeinstallprompt --}}
<div id="pwa-install-wrap" style="display:none;padding:.5rem .75rem;">
    <button id="pwa-install-btn"
            class="btn btn-sm w-100 d-flex align-items-center gap-2"
            style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);border-radius:.6rem;font-size:.8rem;font-weight:600;">
        <i class="bi bi-download"></i>
        <span>Install App</span>
    </button>
</div>

<div class="sidebar-footer">
    @php
        $accountUrl = auth()->user()->isCustomer()
            ? route('customer.profile.edit')
            : (auth()->user()->isSuperAdmin() ? '#' : route('admin.account'));
    @endphp
    <a href="{{ $accountUrl }}" class="user-info text-decoration-none" @if($accountUrl === '#') aria-disabled="true" @endif>
        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}">
        <div class="user-details">
            <span class="user-name">{{ auth()->user()->name }}</span>
            <span class="user-email">{{ auth()->user()->email }}</span>
        </div>
    </a>
    <button class="collapse-btn d-none d-lg-flex" @click="$store.sidebar.toggle()">
        <i class="bi bi-chevron-double-left collapse-icon"></i>
        <span>Collapse sidebar</span>
    </button>
</div>
