# Button Consistency Across All Portals — Design

**Date:** 2026-06-03
**Branch:** feature/mobile-ui-refactor
**Scope:** Normalize button usage across every page in all four portals (auth, owner/super, staff/admin, customer).

## Problem

Button *styling* is already centralized in `resources/scss/app.scss` (`.btn`, `.btn-primary`,
sizes, mobile touch targets) and cascades to every portal, so consistency is **not** a CSS
problem. The inconsistency lives in the **Blade markup**: 363 raw `btn btn-*` usages across
86 files, where the same *role* of action is rendered with different variants on different
pages.

Audited variant distribution (`resources/views`):

| Variant | Uses |
|---|---|
| `btn-outline-secondary` | 117 |
| `btn-primary` | 96 |
| `btn-secondary` | 53 |
| `btn-link` | 41 |
| `btn-outline-primary` | 30 |
| `btn-success` | 23 |
| `btn-outline-danger` | 23 |
| `btn-danger` | 10 |
| `btn-warning` | 6 |
| `btn-light` | 3 |
| `btn-info` | 1 |

Sizes: `btn-sm` = 255 uses, `btn-lg` = 7. **`btn-sm` is the de-facto default size.**

The conflicts:
- **Neutral actions** (Cancel/Back/Filter): solid `btn-secondary` (53) competes with
  `btn-outline-secondary` (117) for the same role.
- **Destructive actions**: solid `btn-danger` (10) competes with `btn-outline-danger` (23).
- **Strays**: `btn-warning` (6), `btn-light` (3), `btn-info` (1) used ad-hoc.

## Decision (chosen approach)

**Define a convention + normalize variants.** Keep raw Bootstrap classes (no migration to the
`<x-button>` component), rewrite only the buttons that deviate from the convention. Pure class
swaps — **no behavior change, no JS, no route/controller changes.** Lowest risk, highest
visible win.

(Rejected: full `<x-button>` migration — too much churn across 86 files for this pass.
Rejected: CSS-only — cannot fix wrong-variant-per-page choices.)

## The convention

| Role | Standard variant | Notes |
|---|---|---|
| **Primary action** (Save, Create, Submit, Confirm) | `btn-primary` | one per form/section |
| **Neutral action** (Cancel, Back, Close, Filter, secondary nav) | `btn-outline-secondary` | absorbs the 53 `btn-secondary` |
| **Secondary emphasis** (alt action, e.g. Export) | `btn-outline-primary` | keep as-is |
| **Destructive** (Delete, Cancel booking, Remove) | `btn-outline-danger` | absorbs stray `btn-danger`; solid `btn-danger` allowed **only** inside confirm modals/dialogs |
| **Positive / money confirm** (Approve refund, Mark paid, Checkout) | `btn-success` | kept as a distinct meaningful role |
| **Inline / text action** (table-row links) | `btn-link` | keep |
| **Size** | `btn-sm` default | full-size (no `btn-sm`) reserved for hero / primary CTA on public landing & auth pages |

### Decisions confirmed with user
1. Neutral buttons standardize on **outline** (`btn-outline-secondary`), the dominant choice.
2. `btn-success` is **kept** as its own role for confirm/money actions (not folded into primary).

## Normalization rules (mechanical)

1. `btn-secondary` → `btn-outline-secondary` (all 53; these are neutral actions).
2. `btn-danger` → `btn-outline-danger`, **except** when the button lives inside a confirm
   modal / destructive-confirmation dialog (there solid `btn-danger` stays).
3. `btn-warning` → map per context: payment/attention CTAs → `btn-success` if money-confirm,
   else `btn-outline-secondary`. Decide per occurrence (only 6).
4. `btn-light` → `btn-outline-secondary` (neutral) unless it's a known ghost/icon button where
   light is intentional — review the 3 occurrences individually.
5. `btn-info` → `btn-outline-primary` (single occurrence; secondary-emphasis).
6. Do **not** mass-change sizes. Leave `btn-sm` where present. Only flag a button for a size
   change if a neutral/primary pair on the same row visibly mismatch (e.g. one `btn-sm`, one
   full-size in the same action group).
7. Preserve all other classes on each element (`w-100`, `me-2`, `d-flex`, icon spans, etc.),
   `href`, `type`, `wire:`, `@click`, `form`, data attributes — swap only the variant token.

## Out of scope
- No migration to `<x-button>`.
- No changes to `app.scss` button rules (the CSS is fine).
- No new buttons, no copy/label rewrites, no icon changes.
- No controller/route/JS changes.

## Verification
- `grep -rohE "btn-(secondary|danger|warning|light|info)" resources/views` afterwards should
  show only the intentional exceptions (modal-confirm `btn-danger`, money-confirm `btn-success`,
  any reviewed `btn-light` ghost buttons).
- Spot-render representative pages in each portal (auth login, admin dashboard + a form +
  a table, super tenants index, customer bookings) to confirm no visual breakage.
- `php artisan view:clear` then load pages; no Blade errors.

## Files affected
~79 Blade files containing the competing variants (the `btn-secondary` / `btn-danger` /
stray sets). Exact list derived at plan time from the audit grep.
