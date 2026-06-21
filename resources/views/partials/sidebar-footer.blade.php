{{--
    Shared sidebar footer: avatar + name + email + desktop collapse button.
    Used by admin, super, and customer layouts.
--}}
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
