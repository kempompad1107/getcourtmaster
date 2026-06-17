@extends('layouts.super')
@section('title', 'Edit ' . $tenant->name)

@section('content')

<x-page-header :title="'Edit: ' . $tenant->name" :back="route('super.tenants.show', $tenant)"
               subtitle="Edit venue, branch, subscription, and owner — all in one place."/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('super.tenants.update', $tenant) }}">
    @csrf @method('PUT')

    {{-- Venue --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Venue (Tenant)</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-7">
                    <label class="form-label fw-medium">Business name</label>
                    <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-medium">URL slug</label>
                    <div class="input-group">
                        <span class="input-group-text small text-muted">/t/</span>
                        <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required
                               pattern="[a-z0-9\-]+"
                               class="form-control text-lowercase @error('slug') is-invalid @enderror">
                    </div>
                    @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text text-warning small">
                        Changing this breaks existing QR codes and any links the tenant has shared.
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Contact email</label>
                    <input type="email" name="email" value="{{ old('email', $tenant->email) }}" required
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Address</label>
                    <input type="text" name="address" value="{{ old('address', $tenant->address) }}" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">City</label>
                    <input type="text" name="city" value="{{ old('city', $tenant->city) }}" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Country</label>
                    <input type="text" name="country" value="{{ old('country', $tenant->country) }}" maxlength="5"
                           class="form-control text-uppercase">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Currency</label>
                    <select name="currency" class="form-select">
                        @foreach(['PHP' => 'PHP (₱)', 'USD' => 'USD ($)', 'SGD' => 'SGD ($)'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('currency', $tenant->currency) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-8">
                    <label class="form-label fw-medium">Timezone</label>
                    <select name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $tenant->timezone) === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $tenant->status) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Main branch --}}
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold">Main Branch</h6>
            <small class="text-muted">Editing renames the tenant's main branch. Add more branches from inside the tenant's admin.</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Branch name</label>
                    <input type="text" name="branch_name" value="{{ old('branch_name', $mainBranch?->name ?? 'Main Branch') }}" required
                           class="form-control @error('branch_name') is-invalid @enderror">
                    @error('branch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Branch city</label>
                    <input type="text" name="branch_city" value="{{ old('branch_city', $mainBranch?->city) }}" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Branch address</label>
                    <input type="text" name="branch_address" value="{{ old('branch_address', $mainBranch?->address) }}" class="form-control">
                </div>
            </div>
        </div>
    </div>

    {{-- Subscription plan --}}
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold">Subscription Plan</h6>
            <small class="text-muted">
                @if($subscription)
                    Currently on <strong>{{ $subscription->plan?->name ?? '—' }}</strong> ({{ $subscription->billing_cycle }}, renews {{ $subscription->renews_at?->format('M j, Y') ?? '—' }}).
                @else
                    No active subscription. Pick a plan to attach one.
                @endif
            </small>
        </div>
        <div class="card-body">
            @if($plans->isEmpty())
                <div class="alert alert-warning small mb-0">
                    No active subscription plans found. Create one at
                    <a href="{{ route('super.plans.index') }}">Subscription Plans</a> first, or save without changes here.
                </div>
            @else
            <div class="row g-3">
                <div class="col-sm-7">
                    <label class="form-label fw-medium">Plan</label>
                    <select name="plan_id" class="form-select @error('plan_id') is-invalid @enderror">
                        <option value="">— No change —</option>
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}" @selected(old('plan_id', $subscription?->plan_id) == $p->id)>
                                {{ $p->name }}
                                @if($p->price_monthly) · ₱{{ number_format($p->price_monthly, 0) }}/mo @endif
                                @if($p->price_yearly)  · ₱{{ number_format($p->price_yearly, 0) }}/yr  @endif
                            </option>
                        @endforeach
                    </select>
                    @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-medium">Billing cycle</label>
                    <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror">
                        <option value="monthly" @selected(old('billing_cycle', $subscription?->billing_cycle ?? 'monthly') === 'monthly')>Monthly</option>
                        <option value="yearly"  @selected(old('billing_cycle', $subscription?->billing_cycle) === 'yearly')>Yearly</option>
                    </select>
                    @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-text small">
                        Changing the plan updates <code>renews_at</code> to start a new cycle from today and syncs <code>tenants.plan</code>.
                        No invoice is generated automatically — use "Generate Invoice" on the tenant page if needed.
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Business owner --}}
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold">Business Owner Account</h6>
            <small class="text-muted">
                @if($owner)
                    The person who logs into the admin panel for this tenant. Leave password blank to keep the current one.
                @else
                    No owner account exists for this tenant yet — fields below will create one.
                @endif
            </small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Full name</label>
                    <input type="text" name="owner_name" value="{{ old('owner_name', $owner?->name) }}" required
                           class="form-control @error('owner_name') is-invalid @enderror">
                    @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="owner_phone" value="{{ old('owner_phone', $owner?->phone) }}" class="form-control">
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Email (login)</label>
                    <input type="email" name="owner_email" value="{{ old('owner_email', $owner?->email) }}" required
                           class="form-control @error('owner_email') is-invalid @enderror">
                    @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">
                        {{ $owner ? 'New password' : 'Initial password' }}
                        @unless($owner) <span class="text-danger">*</span>@endunless
                    </label>
                    <input type="text" name="owner_password" value="{{ old('owner_password') }}" minlength="8"
                           @unless($owner) required @endunless
                           placeholder="{{ $owner ? 'Leave blank to keep current' : 'min 8 characters' }}"
                           class="form-control font-monospace @error('owner_password') is-invalid @enderror">
                    @error('owner_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($owner)
                        <div class="form-text">Only updates if you type something. Use the <strong>Reset PW</strong> button on the <a href="{{ route('super.tenants.users', $tenant) }}">users page</a> to generate one instead.</div>
                    @else
                        <div class="form-text">Share this with the owner securely. They can change it after first login.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Feature flags --}}
    <div class="card mb-4" id="features">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Feature Flags</h6></div>
        <div class="card-body">
            <p class="small text-muted">Override which features this tenant can access, independent of their plan. Plan-default features still apply if no overrides are set.</p>
            @php
                $features = [
                    'bookings'         => 'Bookings',
                    'pos'              => 'POS',
                    'memberships'      => 'Memberships',
                    'inventory'        => 'Inventory',
                    'promotions'       => 'Promotions',
                    'basic_reports'    => 'Basic Reports',
                    'advanced_reports' => 'Advanced Reports',
                    'api_access'       => 'API Access',
                    'multi_branch'     => 'Multi-Branch',
                    'white_label'      => 'White Label',
                    'priority_support' => 'Priority Support',
                ];
                $current = old('features', $tenant->features ?? []);
            @endphp
            <div class="row g-2">
                @foreach($features as $key => $label)
                    <div class="col-6 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="features[]" value="{{ $key }}"
                                   id="feat-{{ $key }}" @checked(in_array($key, $current))>
                            <label class="form-check-label small" for="feat-{{ $key }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('super.tenants.show', $tenant) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Changes
        </button>
    </div>
</form>

</div>
</div>

@endsection
