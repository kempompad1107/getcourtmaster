import './bootstrap';
import './echo';

// Alpine.js
import Alpine from 'alpinejs';
window.Alpine = Alpine;

// SortableJS — drag-and-drop lists (tournament seeding)
import Sortable from 'sortablejs';
window.Sortable = Sortable;

// ── Anti-flash: apply saved theme before first paint ─────────────────────────
(function () {
    const saved = localStorage.getItem('theme');
    const prefer = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', saved ?? prefer);
})();

// ── Theme store ──────────────────────────────────────────────────────────────
Alpine.store('theme', {
    dark: false,
    init() {
        this.dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    },
    toggle() {
        this.dark = !this.dark;
        const theme = this.dark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
    },
});

// ── Sidebar store ────────────────────────────────────────────────────────────
Alpine.store('sidebar', {
    collapsed:  localStorage.getItem('sidebarCollapsed') === 'true',
    mobileOpen: false,

    init() { this._sync(); },

    toggle() {
        this.collapsed = !this.collapsed;
        localStorage.setItem('sidebarCollapsed', this.collapsed);
        this._sync();
    },
    openMobile()  { this.mobileOpen = true;  this._sync(); },
    closeMobile() { this.mobileOpen = false; this._sync(); },

    _sync() {
        const sidebar = document.getElementById('sidebar');
        const topbar  = document.getElementById('topbar');
        const main    = document.getElementById('main-content');
        const overlay = document.getElementById('sidebar-overlay');
        if (!sidebar) return;

        sidebar.classList.toggle('collapsed',    this.collapsed && !this.mobileOpen);
        sidebar.classList.toggle('mobile-open',  this.mobileOpen);
        topbar?.classList.toggle('sidebar-collapsed',  this.collapsed);
        main?.classList.toggle('sidebar-collapsed',    this.collapsed);
        overlay?.classList.toggle('show', this.mobileOpen);
    },
});

// ── Modal store ──────────────────────────────────────────────────────────────
Alpine.store('modal', {
    active: null,
    data:   {},
    open(name, payload = {}) {
        this.active = name;
        this.data   = payload;
    },
    close() {
        this.active = null;
        this.data   = {};
    },
});

// ── Auto-init Bootstrap tooltips/popovers ────────────────────────────────────
// Bootstrap JS is loaded via CDN bundle (see layouts) — available as window.bootstrap
document.addEventListener('DOMContentLoaded', () => {
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
            .forEach(el => new window.bootstrap.Tooltip(el));
        document.querySelectorAll('[data-bs-toggle="popover"]')
            .forEach(el => new window.bootstrap.Popover(el));
    }

    Alpine.store('sidebar').init();
    Alpine.store('theme').init();

    // ── Preserve sidebar scroll position across full page reloads ────────────
    // Each navigation is a full reload, which would otherwise reset the nav
    // scroll to the top. Persist scrollTop per-tab and restore it on load.
    const nav = document.querySelector('.sidebar-nav');
    if (nav) {
        const KEY = 'sidebarNavScroll';
        const saved = sessionStorage.getItem(KEY);
        if (saved !== null) nav.scrollTop = parseInt(saved, 10) || 0;

        let raf;
        nav.addEventListener('scroll', () => {
            if (raf) return;
            raf = requestAnimationFrame(() => {
                sessionStorage.setItem(KEY, nav.scrollTop);
                raf = null;
            });
        }, { passive: true });
    }
});

Alpine.start();
