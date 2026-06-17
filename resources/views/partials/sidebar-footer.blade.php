{{--
    Shared sidebar footer: avatar + name + email + desktop collapse button.
    Used by admin, super, and customer layouts.
--}}
<div class="sidebar-footer">
    <div class="user-info">
        <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}">
        <div class="user-details">
            <span class="user-name">{{ auth()->user()->name }}</span>
            <span class="user-email">{{ auth()->user()->email }}</span>
        </div>
    </div>
    <button class="collapse-btn d-none d-lg-flex" @click="$store.sidebar.toggle()">
        <i class="bi bi-chevron-double-left collapse-icon"></i>
        <span>Collapse sidebar</span>
    </button>
</div>
