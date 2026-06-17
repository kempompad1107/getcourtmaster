# Branch-Scoped Tournaments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an organizer mark each tournament as open to all branches (default) or exclusive to one branch, restricting admin visibility, court scheduling, and customer self-registration accordingly.

**Architecture:** Add `is_all_branches` + `branch_id` to `tournaments`. A new global `TournamentBranchScope` filters tournament queries (staff/owner keyed off `BranchContext`; customers keyed off `home_branch_id`; super-admins unfiltered). Court dropdowns and the match-court validation filter to the tournament's branch when exclusive. Customer portal self-registration is gated by `home_branch_id`; the existing staff desk path (`via = 'admin'`) bypasses that gate as the override.

**Tech Stack:** Laravel 11, Blade, MySQL, Spatie Activitylog. Spec: `docs/superpowers/specs/2026-06-12-branch-scoped-tournaments-design.md`.

**Verification note:** `php artisan test` is broken on this box (a MySQL-only `MODIFY ENUM` migration fails ~61 tests on sqlite — see `memory/test-suite-sqlite-broken.md`). This plan follows the tournament module's established verification pattern instead: a throwaway `storage/tmp-*.php` script that boots Laravel, exercises the change against sandboxed data (names prefixed `ZZ … Sandbox`), prints PASS/FAIL lines, and `forceDelete`s its fixtures. Each task's "verify" step gives the exact script and expected output. When rendering Blade outside HTTP, call `view()->share('errors', new \Illuminate\Support\ViewErrorBag)` first (per `memory/tournament-module.md`).

---

## File Structure

- **Create** `database/migrations/2026_06_13_000001_add_branch_scope_to_tournaments.php` — schema columns + index.
- **Create** `app/Models/Scopes/TournamentBranchScope.php` — visibility scope.
- **Modify** `app/Models/Tournament.php` — fillable, cast, `branch()` relation, register scope.
- **Modify** `app/Http/Requests/TournamentRequest.php` — validate `is_all_branches` + `branch_id`, normalize.
- **Modify** `app/Http/Controllers/Admin/TournamentController.php` — pass branch options to create/edit.
- **Modify** `resources/views/admin/tournaments/_form.blade.php` — branch toggle + dropdown.
- **Modify** `app/Http/Requests/TournamentMatchRequest.php` — court must match tournament branch when exclusive.
- **Modify** `resources/views/admin/tournaments/matches/_schedule-modal.blade.php` — filter court options by the match's tournament branch.
- **Modify** `app/Services/TournamentRegistrationService.php` — `via = 'portal'` home-branch gate.

---

## Task 1: Schema — add branch columns to `tournaments`

**Files:**
- Create: `database/migrations/2026_06_13_000001_add_branch_scope_to_tournaments.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Existing rows default to all-branches → no behavior change.
            $table->boolean('is_all_branches')->default(true)->after('tenant_id');
            $table->foreignId('branch_id')->nullable()->after('is_all_branches')
                ->constrained('branches')->nullOnDelete();
            $table->index(['tenant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['tenant_id', 'branch_id']);
            $table->dropColumn(['is_all_branches', 'branch_id']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `DONE` for `2026_06_13_000001_add_branch_scope_to_tournaments`.

- [ ] **Step 3: Verify columns exist and default correctly**

Run:
```bash
php artisan tinker --execute="echo Schema::hasColumn('tournaments','is_all_branches') ? 'has-flag' : 'MISSING'; echo PHP_EOL; echo Schema::hasColumn('tournaments','branch_id') ? 'has-branch' : 'MISSING';"
```
Expected:
```
has-flag
has-branch
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_13_000001_add_branch_scope_to_tournaments.php
git commit -m "feat(tournaments): add is_all_branches + branch_id columns"
```

---

## Task 2: Tournament model — fillable, cast, relation

**Files:**
- Modify: `app/Models/Tournament.php`

- [ ] **Step 1: Add the two columns to `$fillable`**

In `app/Models/Tournament.php`, change the `$fillable` array's first line from:

```php
        'tenant_id', 'name', 'slug', 'description', 'cover_image', 'logo',
```
to:
```php
        'tenant_id', 'is_all_branches', 'branch_id',
        'name', 'slug', 'description', 'cover_image', 'logo',
```

- [ ] **Step 2: Cast the flag**

In the `$casts` array, add after `'entry_fee' => 'decimal:2',`:

```php
        'is_all_branches' => 'boolean',
```

- [ ] **Step 3: Add the `branch()` relation**

After the existing `tenant()` relation method, add:

```php
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
```

- [ ] **Step 4: Verify the model compiles and the relation resolves**

Run:
```bash
php artisan tinker --execute="\$t = new App\Models\Tournament; echo in_array('branch_id', \$t->getFillable()) ? 'fillable-ok' : 'FAIL'; echo PHP_EOL; echo method_exists(\$t,'branch') ? 'relation-ok' : 'FAIL';"
```
Expected:
```
fillable-ok
relation-ok
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/Tournament.php
git commit -m "feat(tournaments): branch relation, fillable, cast"
```

---

## Task 3: `TournamentBranchScope` — visibility filter

**Files:**
- Create: `app/Models/Scopes/TournamentBranchScope.php`
- Modify: `app/Models/Tournament.php`

- [ ] **Step 1: Create the scope**

Create `app/Models/Scopes/TournamentBranchScope.php`:

```php
<?php

namespace App\Models\Scopes;

use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Branch visibility for tournaments. Layers on top of TenantScope.
 *
 *  - No authenticated user (CLI, queues, public): no-op.
 *  - Super-admin: no-op (operates across the SaaS).
 *  - Customer: sees all-branches tournaments + exclusives matching their
 *    home_branch_id. A customer with no home branch sees only all-branches ones.
 *  - Staff / owner: keyed off the active BranchContext. A selected branch shows
 *    all-branches tournaments + that branch's exclusives. Owners viewing
 *    "All branches" (null context) see everything; staff get an assigned-branch
 *    ceiling as a safety net.
 *
 * All-branches tournaments (is_all_branches = true) are visible to everyone in
 * the tenant. Use withoutGlobalScope(TournamentBranchScope::class) for the rare
 * intentional cross-branch query (reports, super-admin tooling).
 */
class TournamentBranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $table = $model->getTable();
        $allBranches = "{$table}.is_all_branches";
        $branchCol = "{$table}.branch_id";

        if ($user->isCustomer()) {
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->when($user->home_branch_id, fn ($q) => $q->orWhere($branchCol, $user->home_branch_id)));
            return;
        }

        // Staff / owner.
        $context = app(BranchContext::class);
        $current = $context->current();

        if ($current !== null) {
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->orWhere($branchCol, $current));
            return;
        }

        // Null context: owners/super-admins see all; staff get a hard ceiling.
        if (! $context->canSeeAllBranches($user)) {
            $allowed = $context->allowedBranchIds($user);
            if (empty($allowed)) {
                $builder->where($allBranches, true);
                return;
            }
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->orWhereIn($branchCol, $allowed));
        }
    }
}
```

- [ ] **Step 2: Register the scope on the model**

In `app/Models/Tournament.php`, the `booted()` method currently only wires the slug. Add the global scope as its first statement:

```php
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\TournamentBranchScope());

        static::creating(function (Tournament $tournament) {
            if (blank($tournament->slug)) {
                $tournament->slug = static::uniqueSlug($tournament->name, (int) $tournament->tenant_id);
            }
        });
    }
```

- [ ] **Step 3: Write the verification script**

Create `storage/tmp-branch-scope.php`:

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Tournament, Branch, User};
use App\Services\BranchContext;
use Illuminate\Support\Facades\Auth;

$tenantId = Branch::query()->value('tenant_id');
$branches = Branch::where('tenant_id', $tenantId)->orderBy('id')->take(2)->get();
[$brA, $brB] = [$branches[0], $branches[1]];

$all = Tournament::withoutGlobalScope(App\Models\Scopes\TournamentBranchScope::class)
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->create(['tenant_id'=>$tenantId,'is_all_branches'=>true,'name'=>'ZZ Sandbox All','entry_fee'=>0,'currency'=>'PHP','status'=>'draft','visibility'=>'private']);
$exA = Tournament::withoutGlobalScope(App\Models\Scopes\TournamentBranchScope::class)
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->create(['tenant_id'=>$tenantId,'is_all_branches'=>false,'branch_id'=>$brA->id,'name'=>'ZZ Sandbox ExA','entry_fee'=>0,'currency'=>'PHP','status'=>'draft','visibility'=>'private']);
$exB = Tournament::withoutGlobalScope(App\Models\Scopes\TournamentBranchScope::class)
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->create(['tenant_id'=>$tenantId,'is_all_branches'=>false,'branch_id'=>$brB->id,'name'=>'ZZ Sandbox ExB','entry_fee'=>0,'currency'=>'PHP','status'=>'draft','visibility'=>'private']);

function ids() { return Tournament::where('name','like','ZZ Sandbox%')->pluck('id')->sort()->values()->all(); }
function check($label,$cond){ echo ($cond?'PASS':'FAIL')." — $label".PHP_EOL; }

// Customer with home branch A
$cust = new User(['tenant_id'=>$tenantId,'home_branch_id'=>$brA->id,'user_type'=>'customer']);
Auth::setUser($cust);
$seen = ids();
check('customer(A) sees All + ExA', in_array($all->id,$seen) && in_array($exA->id,$seen));
check('customer(A) does NOT see ExB', !in_array($exB->id,$seen));

// Owner with active branch = B
$owner = new User(['tenant_id'=>$tenantId,'user_type'=>'business_owner']);
Auth::setUser($owner);
app(BranchContext::class)->set($brB->id);
$seen = ids();
check('owner@B sees All + ExB', in_array($all->id,$seen) && in_array($exB->id,$seen));
check('owner@B does NOT see ExA', !in_array($exA->id,$seen));

// Cleanup
foreach ([$all,$exA,$exB] as $t) { $t->forceDelete(); }
echo 'done'.PHP_EOL;
```

- [ ] **Step 4: Run the verification script**

Run: `php storage/tmp-branch-scope.php`
Expected:
```
PASS — customer(A) sees All + ExA
PASS — customer(A) does NOT see ExB
PASS — owner@B sees All + ExB
PASS — owner@B does NOT see ExA
done
```
If any line is FAIL, fix the scope before continuing. Then delete the script: `rm storage/tmp-branch-scope.php`.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scopes/TournamentBranchScope.php app/Models/Tournament.php
git commit -m "feat(tournaments): TournamentBranchScope branch visibility filter"
```

---

## Task 4: `TournamentRequest` — validate branch selection

**Files:**
- Modify: `app/Http/Requests/TournamentRequest.php`

- [ ] **Step 1: Normalize the flag before validation**

In `app/Http/Requests/TournamentRequest.php`, add this method above `rules()`:

```php
    protected function prepareForValidation(): void
    {
        // A checkbox is absent when unchecked; coerce to a real boolean and
        // clear any stale branch when the tournament is open to all branches.
        $allBranches = $this->boolean('is_all_branches');
        $this->merge([
            'is_all_branches' => $allBranches,
            'branch_id' => $allBranches ? null : $this->input('branch_id'),
        ]);
    }
```

- [ ] **Step 2: Add the validation rules**

Add a `use` for the BranchContext at the top of the file:

```php
use App\Services\BranchContext;
use Illuminate\Validation\Rule;
```

Then in the `rules()` array, add after `'visibility' => 'required|in:public,private',`:

```php
            'is_all_branches' => 'required|boolean',
            'branch_id' => [
                'nullable',
                'required_if:is_all_branches,false',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->whereNull('deleted_at')),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $allowed = app(BranchContext::class)->allowedBranchIds($this->user());
                    if (! in_array((int) $value, $allowed, true)) {
                        $fail('You can only assign a tournament to a branch you manage.');
                    }
                },
            ],
```

- [ ] **Step 3: Add the messages**

In the `messages()` array, add:

```php
            'branch_id.required_if' => 'Pick the branch this tournament is exclusive to.',
```

- [ ] **Step 4: Verify the rules behave**

Create `storage/tmp-tournament-request.php`:

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Branch, User};
use App\Services\BranchContext;
use Illuminate\Support\Facades\{Auth, Validator};

$tenantId = Branch::query()->value('tenant_id');
$branch = Branch::where('tenant_id', $tenantId)->first();
$owner = User::where('tenant_id',$tenantId)->where('user_type','business_owner')->first()
    ?? new User(['tenant_id'=>$tenantId,'user_type'=>'business_owner']);
Auth::setUser($owner);

$base = ['name'=>'X','entry_fee'=>0,'currency'=>'PHP','visibility'=>'private'];

function check($label,$cond){ echo ($cond?'PASS':'FAIL')." — $label".PHP_EOL; }

// Exclusive without a branch → fails
$r = (new App\Http\Requests\TournamentRequest());
$r->setUserResolver(fn()=>$owner);
$r->merge(array_merge($base, ['is_all_branches'=>false]));
$r->prepareForValidation();
$v = Validator::make($r->all(), $r->rules());
check('exclusive without branch fails', $v->fails() && $v->errors()->has('branch_id'));

// Exclusive with a valid branch → passes branch rule
$r2 = (new App\Http\Requests\TournamentRequest());
$r2->setUserResolver(fn()=>$owner);
$r2->merge(array_merge($base, ['is_all_branches'=>false,'branch_id'=>$branch->id]));
$r2->prepareForValidation();
$v2 = Validator::make($r2->all(), $r2->rules());
check('exclusive with valid branch passes', !$v2->errors()->has('branch_id'));

// All-branches → branch_id normalized to null
$r3 = (new App\Http\Requests\TournamentRequest());
$r3->setUserResolver(fn()=>$owner);
$r3->merge(array_merge($base, ['is_all_branches'=>true,'branch_id'=>$branch->id]));
$r3->prepareForValidation();
check('all-branches nulls branch_id', $r3->input('branch_id') === null);

echo 'done'.PHP_EOL;
```

Run: `php storage/tmp-tournament-request.php`
Expected:
```
PASS — exclusive without branch fails
PASS — exclusive with valid branch passes
PASS — all-branches nulls branch_id
done
```
Then: `rm storage/tmp-tournament-request.php`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/TournamentRequest.php
git commit -m "feat(tournaments): validate is_all_branches + branch_id"
```

---

## Task 5: Create/edit form — branch toggle + dropdown

**Files:**
- Modify: `app/Http/Controllers/Admin/TournamentController.php`
- Modify: `resources/views/admin/tournaments/_form.blade.php`

- [ ] **Step 1: Pass branch options to the create view**

In `app/Http/Controllers/Admin/TournamentController.php`, add a `use` near the top:

```php
use App\Services\BranchContext;
```

Replace the `create()` method body's `return` with:

```php
    public function create()
    {
        $this->authorize('create', Tournament::class);
        return view('admin.tournaments.create', [
            'defaultCurrency' => $this->authTenant()->currency ?: 'PHP',
            'branches' => app(BranchContext::class)->available(),
        ]);
    }
```

- [ ] **Step 2: Pass branch options to the edit view**

Replace the `edit()` method with:

```php
    public function edit(Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        return view('admin.tournaments.edit', [
            'tournament' => $tournament,
            'defaultCurrency' => $this->authTenant()->currency ?: 'PHP',
            'branches' => app(BranchContext::class)->available(),
        ]);
    }
```

- [ ] **Step 3: Add the branch card to the form**

In `resources/views/admin/tournaments/_form.blade.php`, add this block immediately after line 1 (`@php $tournament ??= null; @endphp`) — but before the first `<div class="card mb-4">`:

```blade
@php
    $branches ??= collect();
    $isAllBranches = old('is_all_branches', $tournament?->is_all_branches ?? true);
    $isAllBranches = filter_var($isAllBranches, FILTER_VALIDATE_BOOLEAN);
    $selectedBranch = old('branch_id', $tournament?->branch_id);
@endphp

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Branch Scope</h6></div>
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="is_all_branches" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_all_branches"
                   name="is_all_branches" value="1" {{ $isAllBranches ? 'checked' : '' }}
                   onchange="document.getElementById('branch-picker').classList.toggle('d-none', this.checked)">
            <label class="form-check-label fw-medium" for="is_all_branches">Open to all branches</label>
            <div class="form-text">Turn off to make this tournament exclusive to a single branch.</div>
        </div>
        <div id="branch-picker" class="{{ $isAllBranches ? 'd-none' : '' }}">
            <label class="form-label fw-medium">Branch <span class="text-danger">*</span></label>
            <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                <option value="">Select a branch…</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) $selectedBranch === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
```

- [ ] **Step 4: Verify the form renders both states**

Create `storage/tmp-form-render.php`:

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

view()->share('errors', new Illuminate\Support\ViewErrorBag);
use App\Models\Branch;

$branches = Branch::query()->take(3)->get(['id','name']);
$html = view('admin.tournaments._form', [
    'submitLabel' => 'Create',
    'branches' => $branches,
    'defaultCurrency' => 'PHP',
])->render();

function check($label,$cond){ echo ($cond?'PASS':'FAIL')." — $label".PHP_EOL; }
check('renders the toggle', str_contains($html, 'name="is_all_branches"'));
check('renders hidden fallback 0', str_contains($html, 'name="is_all_branches" value="0"'));
check('renders branch dropdown', str_contains($html, 'name="branch_id"'));
check('default checked (all-branches)', str_contains($html, "value=\"1\" checked"));
echo 'done'.PHP_EOL;
```

Run: `php storage/tmp-form-render.php`
Expected:
```
PASS — renders the toggle
PASS — renders hidden fallback 0
PASS — renders branch dropdown
PASS — default checked (all-branches)
done
```
Then: `rm storage/tmp-form-render.php`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/TournamentController.php resources/views/admin/tournaments/_form.blade.php
git commit -m "feat(tournaments): branch scope toggle + picker on create/edit form"
```

---

## Task 6: Court scheduling — validation + modal filter

**Files:**
- Modify: `app/Http/Requests/TournamentMatchRequest.php`
- Modify: `resources/views/admin/tournaments/matches/_schedule-modal.blade.php`

- [ ] **Step 1: Add branch-aware court validation**

In `app/Http/Requests/TournamentMatchRequest.php`, replace the `court_id` rule with one that also enforces the tournament's branch:

```php
            'court_id' => [
                'nullable',
                Rule::exists('courts', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $match = $this->route('match');
                    $tournament = $match?->tournament;
                    if ($tournament && ! $tournament->is_all_branches && $tournament->branch_id) {
                        $courtBranch = \App\Models\Court::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                            ->whereKey($value)->value('branch_id');
                        if ((int) $courtBranch !== (int) $tournament->branch_id) {
                            $fail('That court is not at this tournament\'s branch.');
                        }
                    }
                },
            ],
```

- [ ] **Step 2: Filter the modal's court options by the match's tournament**

In `resources/views/admin/tournaments/matches/_schedule-modal.blade.php`, replace the court `@foreach` loop (around line 21) so it only offers courts valid for that match's tournament:

```blade
                        @php
                            $branchCourts = ($match->tournament && ! $match->tournament->is_all_branches && $match->tournament->branch_id)
                                ? $courts->where('branch_id', $match->tournament->branch_id)
                                : $courts;
                        @endphp
                        @foreach($branchCourts as $court)
                        <option value="{{ $court->id }}" @selected($match->court_id === $court->id)>{{ $court->name }}</option>
                        @endforeach
```

- [ ] **Step 3: Make the court list carry `branch_id`**

The modal now filters on `$court->branch_id`, so the controller-loaded list must include that column. In `app/Http/Controllers/Admin/TournamentMatchController.php`, in the `index()` method, change:

```php
        $courts = Court::withoutGlobalScope(BranchScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
```
to:
```php
        $courts = Court::withoutGlobalScope(BranchScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);
```

Also eager-load the tournament's branch fields the modal reads — change the `with([...])` line for `'tournament:...'` from:

```php
                'tournament:id,tenant_id,name,settings,currency', 'division:id,name', 'group:id,name',
```
to:
```php
                'tournament:id,tenant_id,name,settings,currency,is_all_branches,branch_id', 'division:id,name', 'group:id,name',
```

- [ ] **Step 4: Verify validation rejects an out-of-branch court**

Create `storage/tmp-court-validation.php`:

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Court, Branch};
use Illuminate\Support\Facades\Validator;

$tenantId = Branch::query()->value('tenant_id');
$branches = Branch::where('tenant_id',$tenantId)->orderBy('id')->take(2)->get();
[$brA,$brB] = [$branches[0],$branches[1]];
$courtB = Court::withoutGlobalScope(App\Models\Scopes\BranchScope::class)
    ->where('tenant_id',$tenantId)->where('branch_id',$brB->id)->first();

if (!$courtB) { echo 'SKIP — no court at branch B to test with'.PHP_EOL; exit; }

// Fake an exclusive-to-A tournament + a match pointing at it.
$tournament = (object) ['is_all_branches'=>false, 'branch_id'=>$brA->id];

// Simulate the closure rule directly.
$fail = null;
$rule = function ($attribute,$value,$failFn) use ($tournament) {
    if ($value === null) return;
    if (!$tournament->is_all_branches && $tournament->branch_id) {
        $courtBranch = App\Models\Court::withoutGlobalScope(App\Models\Scopes\BranchScope::class)
            ->whereKey($value)->value('branch_id');
        if ((int)$courtBranch !== (int)$tournament->branch_id) $failFn('rejected');
    }
};
$rule('court_id', $courtB->id, function($m) use (&$fail){ $fail = $m; });

function check($label,$cond){ echo ($cond?'PASS':'FAIL')." — $label".PHP_EOL; }
check('court at branch B rejected for A-exclusive tournament', $fail === 'rejected');
echo 'done'.PHP_EOL;
```

Run: `php storage/tmp-court-validation.php`
Expected (when a branch-B court exists):
```
PASS — court at branch B rejected for A-exclusive tournament
done
```
Then: `rm storage/tmp-court-validation.php`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/TournamentMatchRequest.php resources/views/admin/tournaments/matches/_schedule-modal.blade.php app/Http/Controllers/Admin/TournamentMatchController.php
git commit -m "feat(tournaments): scope match courts to exclusive tournament branch"
```

---

## Task 7: Customer self-registration home-branch gate

**Files:**
- Modify: `app/Services/TournamentRegistrationService.php`

- [ ] **Step 1: Add the portal home-branch check**

In `app/Services/TournamentRegistrationService.php`, inside `register()`, the eligibility checks run just before fee calculation. Add the branch gate immediately after the existing `$this->assertDivisionEligibility(...)` call:

```php
            $this->assertDivisionEligibility($division, $users->values()->all(), $tournament);

            // Portal self-registration honors branch exclusivity by the member's
            // home branch. Staff desk registration (via = 'admin') is the override
            // and intentionally skips this check.
            if ($via === 'portal' && ! $tournament->is_all_branches && $tournament->branch_id) {
                foreach ($users as $user) {
                    if ((int) $user->home_branch_id !== (int) $tournament->branch_id) {
                        throw ValidationException::withMessages([
                            'members' => "{$user->name} is not a member of this tournament's branch — please ask the front desk to register you.",
                        ]);
                    }
                }
            }
```

- [ ] **Step 2: Write the verification script**

Create `storage/tmp-portal-branch-gate.php`:

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Tournament, TournamentDivision, Branch, User};
use App\Services\TournamentRegistrationService;
use App\Models\Scopes\{TournamentBranchScope, TenantScope};
use Illuminate\Validation\ValidationException;

$tenantId = Branch::query()->value('tenant_id');
$branches = Branch::where('tenant_id',$tenantId)->orderBy('id')->take(2)->get();
[$brA,$brB] = [$branches[0],$branches[1]];

$t = Tournament::withoutGlobalScope(TournamentBranchScope::class)->withoutGlobalScope(TenantScope::class)
    ->create(['tenant_id'=>$tenantId,'is_all_branches'=>false,'branch_id'=>$brA->id,'name'=>'ZZ Sandbox Branch Gate','entry_fee'=>0,'currency'=>'PHP','status'=>'registration_open','visibility'=>'public']);
$div = TournamentDivision::create(['tenant_id'=>$tenantId,'tournament_id'=>$t->id,'name'=>'ZZ Open Singles','gender'=>'open','team_size'=>1,'seeding_method'=>'random']);

// A member whose home branch is B (mismatch).
$memberB = User::where('tenant_id',$tenantId)->where('user_type','customer')->where('is_active',true)->first();
$origHome = $memberB->home_branch_id;
$memberB->home_branch_id = $brB->id; $memberB->save();

$svc = app(TournamentRegistrationService::class);
$staff = User::where('tenant_id',$tenantId)->whereIn('user_type',['business_owner','staff'])->first();

function check($label,$cond){ echo ($cond?'PASS':'FAIL')." — $label".PHP_EOL; }

// Portal path → blocked
$blocked = false;
try {
    $svc->register($div, [['user_id'=>$memberB->id,'is_captain'=>true]], via:'portal', registeredBy:$memberB);
} catch (ValidationException $e) { $blocked = true; }
check('portal registration blocked for mismatched home branch', $blocked);

// Desk path (via=admin) → allowed (override)
$team = null;
try {
    $team = $svc->register($div, [['user_id'=>$memberB->id,'is_captain'=>true]], via:'admin', registeredBy:$staff);
} catch (ValidationException $e) {}
check('desk registration overrides branch gate', $team !== null);

// Cleanup
$memberB->home_branch_id = $origHome; $memberB->save();
$t->divisions()->forceDelete();
foreach ($t->teams()->withTrashed()->get() as $tm) { $tm->members()->forceDelete(); $tm->forceDelete(); }
$t->forceDelete();
echo 'done'.PHP_EOL;
```

- [ ] **Step 3: Run the verification script**

Run: `php storage/tmp-portal-branch-gate.php`
Expected:
```
PASS — portal registration blocked for mismatched home branch
PASS — desk registration overrides branch gate
done
```
Then: `rm storage/tmp-portal-branch-gate.php`.

- [ ] **Step 4: Commit**

```bash
git add app/Services/TournamentRegistrationService.php
git commit -m "feat(tournaments): portal self-registration honors branch, desk overrides"
```

---

## Task 8: Final integration check + memory update

**Files:**
- Modify: `C:\Users\Kemp Ompad\.claude\projects\c--xampp-htdocs-courtmaster\memory\tournament-module.md` (append branch-scope note)
- Modify: `C:\Users\Kemp Ompad\.claude\projects\c--xampp-htdocs-courtmaster\memory\MEMORY.md` (pointer line)

- [ ] **Step 1: Smoke-test the full create→view flow in the browser**

Run the app (`/run` skill or existing launch flow). As an owner:
1. Create a tournament with "Open to all branches" OFF and a branch selected → saves; the show page reflects it.
2. Create one with the toggle ON → `branch_id` is null in the DB.
3. Switch the topbar branch and confirm exclusive tournaments for other branches drop out of the admin list.

Expected: all three behave per the spec. If anything fails, fix before the memory update.

- [ ] **Step 2: Append a branch-scope note to the tournament module memory**

Add to `memory/tournament-module.md` (under the existing invariants): a line noting tournaments are optionally branch-exclusive via `is_all_branches` + `branch_id`; visibility enforced by `TournamentBranchScope` (customers keyed off `home_branch_id`, staff/owner off `BranchContext`); match courts scoped to the branch in the schedule modal + `TournamentMatchRequest`; portal self-registration gated by home branch with the staff desk path (`via='admin'`) as the override. Link `[[admin-route-group-no-role-gate]]` (TenantScope sibling) and `[[tournament-module]]`.

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/plans/2026-06-13-branch-scoped-tournaments.md
git commit -m "docs: branch-scoped tournaments implementation plan"
```

---

## Notes on Edge Cases (already handled in tasks)

- **Branch deleted** → `nullOnDelete` clears `branch_id`; an exclusive tournament with a null branch then matches no specific-branch filter, and both the modal court filter and the match-court validation fall back to all tenant courts (their `&& $tournament->branch_id` guards). Acceptable degenerate state per spec.
- **Customer with no `home_branch_id`** → sees only all-branches tournaments (scope `when()` guard); cannot self-register exclusives (gate compares `(int) null !== branch_id`).
- **Staff assigning out-of-allowance branch** → blocked by `TournamentRequest` closure rule.
- **Cross-branch reporting** → use `withoutGlobalScope(TournamentBranchScope::class)`.
