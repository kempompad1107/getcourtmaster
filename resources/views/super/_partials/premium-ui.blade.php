<style>
    /* ── Shared premium UI helpers for the super-admin portal ── */

    /* Auto-clean KPI grid (no orphan card at any breakpoint) */
    .kpi-grid { display: grid; gap: .75rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
    @media (min-width: 768px) { .kpi-grid { gap: 1rem; grid-template-columns: repeat(var(--kpi-cols, 4), minmax(0,1fr)); } }

    /* Section divider/eyebrow */
    .dash-section { display:flex; align-items:center; gap:.75rem; margin: 1.9rem 0 .85rem; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--bs-secondary-color); }
    .dash-section::after { content:''; flex:1; height:1px; background:var(--bs-border-color); }

    /* Hover-lift card */
    .lift-card { transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .lift-card:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.4); box-shadow: 0 16px 32px -22px rgba(0,0,0,.5); }

    /* Icon chip for card headers */
    .head-icon {
        width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(16,185,129,.12); color: #10b981;
        box-shadow: inset 0 0 0 1px rgba(16,185,129,.22);
    }

    /* Numbered step badge for multi-section forms */
    .step-head { display: flex; align-items: flex-start; gap: .7rem; }
    .form-step {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; flex-shrink: 0; border-radius: 9px;
        background: rgba(16,185,129,.12); color: #10b981;
        font-weight: 800; font-size: .82rem; font-variant-numeric: tabular-nums;
        box-shadow: inset 0 0 0 1px rgba(16,185,129,.25);
    }

    /* Keep the first column pinned while a wide, data-dense table scrolls sideways on small screens. */
    @media (max-width: 991.98px) {
        .sticky-first th:first-child,
        .sticky-first td:first-child {
            position: sticky; left: 0; z-index: 2;
            background: var(--bs-card-bg);
            box-shadow: 1px 0 0 var(--bs-border-color);
        }
        .sticky-first thead th:first-child { z-index: 3; }
    }

    /* Mobile table → stacked cards.
       Add class "pro-table" to <table> and "tcell" to the cells that should show on mobile;
       cells you want hidden on phones get "tcell-hide". The first cell gets emphasis automatically. */
    @media (max-width: 575.98px) {
        .pro-table thead { display: none; }
        .pro-table, .pro-table tbody, .pro-table tr, .pro-table td { display: block; width: 100%; }
        .pro-table tr { padding: .85rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
        .pro-table tr:last-child { border-bottom: 0; }
        .pro-table td { padding: .12rem 0 !important; border: 0 !important; text-align: left !important; }
        .pro-table td.tcell-hide { display: none !important; }
        .pro-table td[data-label]::before {
            content: attr(data-label); display: inline-block; min-width: 92px;
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            color: var(--bs-secondary-color);
        }
    }
</style>
