---
name: design-patterns
description: Established UI/CSS patterns used consistently across the CourtMaster admin
metadata: 
  node_type: memory
  type: project
  originSessionId: 67232c9d-9bb5-4a29-9565-6e3fbfb286d0
---

## Card with coloured header band
Used on: Branches index, Courts index.
Pattern: card has a `.xxx-card-header` div with `background: rgba(var(--crgb), .07)` and a `::before` pseudo-element with `height:3px; background: var(--c)` as the accent line. Status/type CSS custom properties `--c` (color) and `--crgb` (rgb triplet) are set per-card via modifier classes (e.g. `.s-available`, `.is-main`).

## Card footer actions
Primary action (Edit) → `btn btn-primary flex-grow-1`
Secondary icon actions (QR, Calendar, Delete) → `btn btn-outline-secondary` (icon only, no label)
More dropdown → `btn btn-outline-secondary` with `bi-three-dots-vertical`
**No `btn-sm` anywhere on cards.**

## Page header buttons
Always standard size — **never `btn-sm`** in `<x-page-header>` action slots.

## Page header with back link
Component: `resources/views/components/page-header.blade.php`
Back link and title are on the same row with a faint `|` divider. Always `d-flex` (never stacks to column) so actions stay right-aligned on mobile.
Usage: `<x-page-header title="Edit: X" :back="route('admin.xxx.index')" backLabel="Back" />`

## Filter bar
Component: `x-filter-bar`
Search field height `42px`, border-radius `.6rem`. Filters button uses `bi-sliders2` icon. Popover opens with Alpine `x-show`.

## Status colours (courts)
```
available   → --c:#22c55e  --crgb:34,197,94
occupied    → --c:#fb7185  --crgb:251,113,133
reserved    → --c:#fbbf24  --crgb:251,191,36
maintenance → --c:#fb923c  --crgb:251,146,60
closed      → --c:#94a3b8  --crgb:148,163,184
```

## Stat/KPI tiles
Used on dashboard hero and My Shift page. CSS grid: 2-col mobile, 4-col md+.
Frosted glass style: `background:rgba(255,255,255,.07)`, `border:1px solid rgba(255,255,255,.1)`, `border-radius:.75rem`.

## Operating hours rows (branch form)
Classes: `.hours-row` (flex, space-between) and `.hours-times` (flex, time inputs).
On ≤420px: stacks vertically, time inputs go full-width to prevent overflow.

## Tooltip on hover
Alpine pattern used on Google Maps label in branch form:
```html
<span x-data="{ tip: false }">
  <i @mouseenter="tip=true" @mouseleave="tip=false"></i>
  <div x-show="tip" x-cloak x-transition.opacity ...>tooltip text</div>
</span>
```

## Modal style
No gradient crown bar (removed `modal-content::before` from `app.scss`). Modals use `border-0 shadow-lg overflow-hidden`. Header is borderless with title + muted subtitle. Actions use `d-grid gap-2` for stacked full-width buttons.
