---
name: ui-improvements-log
description: "Complete log of every admin page and component improved, with file paths"
metadata: 
  node_type: memory
  type: project
  originSessionId: 67232c9d-9bb5-4a29-9565-6e3fbfb286d0
---

All changes are committed and pushed to `main` on GitHub as of 2026-06-21.

---

## Dashboard — `resources/views/admin/dashboard.blade.php`
- Hero section: greeting left + action buttons right on desktop; stat tiles full-width below in 2-col (mobile) / 4-col (sm+) grid
- Hero stat tiles: frosted glass style
- Hero buttons: standard `btn-sm` size
- KPI cards: 4-column grid with sub-context lines
- Charts row: col-xl-6 revenue, col-xl-3 court status, col-xl-3 revenue donut
- Court Status card: availability summary bar + segmented progress bar
- Revenue by Method: ApexCharts donut; module-level `_isDark`, `_gridC`, `_labelC` vars (not inside DOMContentLoaded)
- Quick Actions: always-visible horizontal tiles
- Bookings per day chart: `tickAmount:6`, `rotate:-40`, `rotateAlways:true`

## Topbar — `resources/views/partials/topbar-actions.blade.php`
- Branch switcher visible on mobile (was hidden); icon-only on mobile, name shown on sm+
- Icon: `bi-house` for all-branches, `bi-shop` for specific branch
- Trigger uses `.topbar-btn` class for consistent sizing with other icons

## My Shift — `resources/views/admin/staff/my-shift.blade.php`
- KPI stats row: 2-col mobile, 4-col md+; on-time rate colour-coded (green ≥80%, amber below)
- Recent Attendance: redesigned as CSS grid cards on mobile with status-coloured left border
- "View all →" link to history page in section header

## Shift History — `resources/views/admin/staff/shift-history.blade.php` (NEW)
- Desktop: standard table; mobile: flat card grid
- Filters: status/month/year selects, mobile filters in 2-col grid
- Pagination: `$history->links()`
- Route: `admin.staff.my-shift.history` in `routes/web.php`
- Controller method: `myShiftHistory()` in `StaffController`

## Branches index — `resources/views/admin/branches/index.blade.php`
- Cards redesigned: coloured header band (green tint for main branch), monogram avatar, branch name + slug + badge
- Stats grid: Courts + Staff in 2-col tiles with large bold numbers
- Footer: Edit (primary fill) + QR (icon outline) + Delete (icon danger) — all standard size
- QR modal: redesigned; gradient crown bar removed from all modals globally

## Branch edit/create form — `resources/views/admin/branches/_form.blade.php`
- Card headers: plain title text only (no icon badges)
- Branch Information card: input-group icons for Phone, Email, Maps URL; placeholders; slug marked optional
- Google Maps field: hover tooltip (`ⓘ`) instead of always-visible helper text; "open in tab" button when URL is saved
- Operating Hours card: `p-0` body, rows separated by `border-bottom`, `.hours-row`/`.hours-times` CSS for mobile overflow fix
- Settings card: `is_main` + `is_active` in own card with sub-labels
- Submit row: right-aligned, `bi-check-lg` icon on button

## Courts index — `resources/views/admin/courts/index.blade.php`
- Removed photo/placeholder media area
- Added coloured top-band header per status with 3px accent line (same pattern as branches)
- Status: glowing dot + coloured text in header; type: coloured pill badge; branch name with shop icon
- Price chips: 2-col grid
- Capacity shown as amenity pill with people icon
- Footer: Edit (primary fill, flex-grow) + Availability (icon outline) + ⋮ dropdown — all standard size
- Header buttons: `btn-sm` removed

## Page header component — `resources/views/components/page-header.blade.php`
- Back link and title on same row with faint `|` divider
- Always `d-flex` row (no mobile column stacking) — actions always right-aligned

## Filter bar component — `resources/views/components/filter-bar.blade.php`
- Search field: `height:42px`, `border-radius:.6rem`
- Filters button: `bi-sliders2` icon, `height:42px`, no `btn-sm`

## Global SCSS — `resources/scss/app.scss`
- `.search-field`: height, radius, icon colour, focus ring
- Removed `modal-content::before` gradient crown bar
