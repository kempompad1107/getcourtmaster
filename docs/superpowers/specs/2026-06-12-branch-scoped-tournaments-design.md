# Branch-Scoped Tournaments — Design

**Date:** 2026-06-12
**Status:** Approved (pending spec review)
**Module:** Tournament Management (see `memory/tournament-module.md`)

## Problem

Tournaments are currently scoped to `tenant_id` only — every tournament implicitly
belongs to the whole organization. Organizers want to choose, per tournament,
whether an event is:

- **Open to all branches** (the default, current behavior), or
- **Exclusive to a single branch** (managed by that branch, played on that
  branch's courts).

The organizer makes this choice at creation time.

## Decisions & Constraints

These were settled during brainstorming and override any contrary assumption:

1. **Branch-exclusive tournaments restrict customers by `home_branch_id`, with a
   staff override.** For a branch-exclusive tournament:
   - A customer sees it on the portal and may **self-register** only if their
     `home_branch_id` matches the tournament's `branch_id`.
   - **Staff/owners override this** by registering a team at the desk (the
     existing admin registration path, `registered_via = 'admin'`) — they can add
     **any** customer regardless of home branch. The override is simply "a staffer
     did it"; no invite table, no new schema.
   - All-branches tournaments remain visible and self-registrable to every tenant
     customer, as today.

   Note: `home_branch_id` is the branch where a customer signed up and is the only
   branch signal customers have (they otherwise shop tenant-wide). It is an
   imperfect proxy for "where they play," which is exactly why the staff desk
   override exists as the escape hatch.

2. **Explicit flag, not a nullable-only convention.** Store both an
   `is_all_branches` boolean and a nullable `branch_id`. Clearer in queries and
   validation than overloading `branch_id IS NULL`.

3. **All-branches tournaments use every branch's courts** for match scheduling;
   exclusive tournaments are limited to their branch's courts.

4. **`BranchScope` (the existing topbar scope) cannot be reused as-is** for
   tournament visibility — it does `WHERE branch_id = <active>`, which would hide
   all-branches tournaments whenever a specific branch is selected. A dedicated
   scope with an `is_all_branches OR branch_id = <active>` clause is required.

## Architecture

### 1. Schema

New migration adding two columns to `tournaments`:

| Column            | Type                        | Notes                                            |
|-------------------|-----------------------------|--------------------------------------------------|
| `is_all_branches` | `boolean`, default `true`   | `true` = open to all branches                    |
| `branch_id`       | nullable FK → `branches.id` | `nullOnDelete`; only meaningful when flag false  |

Index: `(tenant_id, branch_id)`.

**Existing-data safety:** every existing tournament defaults to
`is_all_branches = true`, `branch_id = NULL` → no behavior change for current data.

Add `is_all_branches` and `branch_id` to `Tournament::$fillable`, cast
`is_all_branches` to `boolean`, and add a `branch()` BelongsTo relation.

### 2. `TournamentBranchScope` — visibility filter

New global scope `app/Models/Scopes/TournamentBranchScope.php`, registered in
`Tournament::booted()` (alongside the `TenantScope` added by the
`BelongsToTenant` trait). Staff/owners are keyed off `BranchContext`; **customers
are keyed off `home_branch_id`**.

Logic in `apply()`:

```text
user = Auth::user()
if no user                         → no-op   (public/CLI; TenantScope + publicVisible handle the rest)
if user.isSuperAdmin()             → no-op
if user.isCustomer():
    WHERE (is_all_branches = true OR branch_id = user.home_branch_id)
    // a customer with no home_branch_id sees only all-branches tournaments
else (staff / owner):
    context = app(BranchContext::class)
    current = context.current()
    if current === null:
        if context.canSeeAllBranches(user)   → no-op   (owner "All branches" view)
        else (staff safety net):
            allowed = context.allowedBranchIds(user)
            WHERE (is_all_branches = true OR branch_id IN allowed)   // or 1=0 if allowed empty
    else:
        WHERE (is_all_branches = true OR branch_id = current)
```

Because this is a global scope, it applies to **route-model-binding loads too**:
a Branch-A staffer (or a customer whose home branch isn't B) hitting a
Branch-B-exclusive tournament URL gets a clean 404 with no extra policy code.
All-branches tournaments stay visible to everyone.

**Override note:** the customer branch filter governs *self-service* visibility and
route binding. Staff desk registration runs in the admin context (not as the
customer), so it is unaffected — see section 4a.

**Cross-scope queries** (reports, super-admin tooling) that intentionally need
every tournament must use
`Tournament::withoutGlobalScope(TournamentBranchScope::class)`.

### 3. Court scheduling

When building the court dropdown / validating a court for a tournament match,
bypass the topbar `BranchScope` (per the existing tournament-module gotcha) and
filter by the tournament instead:

```text
query = Court::withoutGlobalScope(BranchScope::class)
              ->where('tenant_id', tournament.tenant_id)
if not tournament.is_all_branches:
    query->where('branch_id', tournament.branch_id)
```

This lives wherever match courts are currently resolved (`TournamentMatchController`
/ `TournamentMatchService` / the match-edit blade). All-branches tournaments list
every tenant court; exclusive tournaments list only their branch's courts. Court
validation on match save must reject a court that fails this filter.

### 4. Create/edit form + validation

- **Form:** a "Open to all branches" toggle plus a branch `<select>` enabled only
  when the toggle is off. Branch options come from `BranchContext::available()`
  (already role-clamped: owners/super-admins see all active branches, staff see
  their assigned ones).
- **`TournamentRequest`** new rules:
  - `is_all_branches` → `required|boolean`
  - `branch_id` → `nullable|required_if:is_all_branches,false|integer|exists:branches,id`
    plus a closure/`Rule` check that the branch belongs to the current tenant and
    is within `BranchContext::allowedBranchIds()` (prevents a staffer assigning a
    tournament to a branch they don't control).
  - When `is_all_branches = true`, normalize `branch_id` to `null` before save
    (prepareForValidation or controller), so residual values don't linger.

### 4a. Registration eligibility (portal vs. desk)

The home-branch restriction is enforced on the **portal self-registration path
only**, distinguished by the existing `via` argument to
`TournamentRegistrationService::register()`:

- `via = 'portal'` (customer self-registers): if the tournament is branch-exclusive
  (`is_all_branches = false`) and the registering customer's `home_branch_id` does
  not match `tournament.branch_id`, reject with a friendly message
  (`'This tournament is limited to <branch> members — please ask the front desk to register you.'`).
  This guards `Customer\TournamentController::register()` (defense in depth — the
  global scope already 404s the page, but the service is the authoritative gate).
- `via = 'admin'` (staff desk registration): **no home-branch check** — this is the
  override. Staff still cannot bypass tenant/division eligibility (gender, age,
  duplicate-slot), only the branch restriction.

Partner eligibility (doubles) follows the same rule: when a customer self-registers
a doubles team for a branch-exclusive tournament, the partner must also match the
branch; desk registration may pair anyone.

### 5. Customer portal

The customer index/show already run under `publicVisible()` + `TenantScope`; the
new `TournamentBranchScope` automatically narrows them by `home_branch_id`, so no
query changes are needed there. The only additive change is the eligibility guard
in section 4a and a matching message in the registration flow.

## Error Handling

- Branch deleted while referenced → `nullOnDelete` sets `branch_id = NULL`. A
  tournament with `is_all_branches = false` and `branch_id = NULL` is degenerate;
  treat it as "all branches" at read time (the scope's `branch_id = current`
  clause simply won't match, and court filtering should fall back to all-tenant
  courts). Optional follow-up: a guard that flips the flag back to `true` on
  branch deletion, deferred unless it proves a problem.
- Staff assigning a court outside the tournament's branch → rejected by court
  validation on match save (section 3).
- Staff picking a branch outside their allowance in the form → rejected by
  `TournamentRequest` (section 4).

## Testing

Follow the tournament-module verification pattern (throwaway
`storage/tmp-*.php` scripts under a "ZZ … Sandbox" tournament, `forceDelete`
after; `view()->share('errors', new ViewErrorBag)` when rendering views outside
HTTP). Cover:

1. **Scope — staff:** Branch-A staff sees all-branches + Branch-A tournaments,
   never Branch-B-exclusive ones (list query + direct route-binding 404).
2. **Scope — owner:** "All branches" view sees everything; selecting Branch A
   narrows to all-branches + Branch-A.
3. **Scope — customer:** sees all-branches tournaments + exclusives matching their
   `home_branch_id`; never sees an exclusive for a different branch (list query +
   direct route-binding 404). A customer with null `home_branch_id` sees only
   all-branches tournaments.
4. **Scope — super-admin:** unfiltered.
5. **Registration override:** customer with matching `home_branch_id` can
   self-register an exclusive; customer with a mismatched home branch is rejected
   on the portal path (`via = 'portal'`) but **can** be registered by staff at the
   desk (`via = 'admin'`). Doubles partner follows the same rule on the portal path.
6. **Courts:** all-branches tournament lists every tenant court; exclusive lists
   only its branch's courts; saving a match with an out-of-branch court is
   rejected.
7. **Validation:** `branch_id` required when flag false; out-of-allowance branch
   rejected; `branch_id` nulled when flag true.
8. **Existing data:** a pre-migration tournament reads as all-branches and behaves
   exactly as before.

Assert on rendered button/dropdown markup, not just HTTP 200 (per the
eager-load-column gotcha in `tournament-module.md`).

## Out of Scope (YAGNI)

- Multi-branch (subset) tournaments — it's all-or-one, not "branches A and C".
- Moving a tournament between branches after creation beyond a normal edit.
- A per-tournament customer invite/allowlist table (the staff desk path is the
  override instead).
- Any customer branch signal other than `home_branch_id` (e.g. "branches I've
  played at").
- Per-branch tournament permissions beyond what the scope + existing policies give.
