@extends('layouts.super')
@section('title', 'Add Tenant')

@push('styles')
@include('super._partials.premium-ui')
@endpush

@section('content')

<x-page-header title="Onboard New Tenant" :back="route('super.tenants.index')"
               subtitle="Create the venue, its main branch, and the business owner account in one go."/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('super.tenants.store') }}" x-data="{ slug: @js(old('slug', '')) }">
    @csrf

    {{-- Venue --}}
    <div class="card mb-4">
        <div class="card-header step-head">
            <span class="form-step">1</span>
            <div><h6 class="mb-0 fw-semibold">Venue (Tenant)</h6>
                <small class="text-muted">The club’s identity, contact, and locale.</small></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-7">
                    <label class="form-label fw-medium">Business name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="form-control @error('name') is-invalid @enderror"
                           @input="if (!slug) document.querySelector('[name=slug]').value = $event.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-5">
                    <label class="form-label fw-medium">URL slug</label>
                    <div class="input-group">
                        <span class="input-group-text small text-muted">/t/</span>
                        <input type="text" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9\-]+"
                               class="form-control text-lowercase @error('slug') is-invalid @enderror">
                    </div>
                    @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">Used in the signup URL. Lowercase letters, digits, and dashes only.</div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Contact email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Address</label>
                    <input type="text" name="address" value="{{ old('address') }}" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">City</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Country</label>
                    <input type="text" name="country" value="{{ old('country', 'PH') }}" maxlength="5"
                           class="form-control text-uppercase">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Currency</label>
                    <select name="currency" class="form-select">
                        @foreach(['PHP' => 'PHP (₱)', 'USD' => 'USD ($)', 'SGD' => 'SGD ($)'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('currency', 'PHP') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-8">
                    <label class="form-label fw-medium">Timezone</label>
                    <select name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', 'Asia/Manila') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status') === 'active')>Active</option>
                        <option value="trial"  @selected(old('status', 'trial') === 'trial')>14-day trial</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Main branch --}}
    <div class="card mb-4">
        <div class="card-header step-head">
            <span class="form-step">2</span>
            <div><h6 class="mb-0 fw-semibold">Main Branch</h6>
                <small class="text-muted">Every tenant needs at least one branch for customer signup & bookings to work.</small></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Branch name</label>
                    <input type="text" name="branch_name" value="{{ old('branch_name', 'Main Branch') }}" required
                           class="form-control @error('branch_name') is-invalid @enderror">
                    @error('branch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Branch city</label>
                    <input type="text" name="branch_city" value="{{ old('branch_city') }}" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Branch address</label>
                    <input type="text" name="branch_address" value="{{ old('branch_address') }}" class="form-control">
                </div>
            </div>
        </div>
    </div>

    {{-- Subscription plan --}}
    <div class="card mb-4">
        <div class="card-header step-head">
            <span class="form-step">3</span>
            <div><h6 class="mb-0 fw-semibold">Subscription Plan</h6>
                <small class="text-muted">Optional. Skip if you'll attach a plan later via the subscription tools.</small></div>
        </div>
        <div class="card-body">
            @if($plans->isEmpty())
                <div class="alert alert-warning small mb-0">
                    No active subscription plans found. Create one at
                    <a href="{{ route('super.plans.index') }}">Subscription Plans</a> first, or proceed without a plan.
                </div>
            @else
            <div class="row g-3">
                <div class="col-sm-7">
                    <label class="form-label fw-medium">Plan</label>
                    <select name="plan_id" class="form-select @error('plan_id') is-invalid @enderror">
                        <option value="">— None (skip) —</option>
                        @foreach($plans as $p)
                            <option value="{{ $p->id }}" @selected(old('plan_id') == $p->id)>
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
                        <option value="monthly" @selected(old('billing_cycle', 'monthly') === 'monthly')>Monthly</option>
                        <option value="yearly"  @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                    </select>
                    @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-text small">
                        If the tenant status above is <strong>14-day trial</strong>, the subscription starts in
                        <code>trialing</code> state with the trial end date carried over.
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Business owner --}}
    <div class="card mb-4">
        <div class="card-header step-head">
            <span class="form-step">4</span>
            <div><h6 class="mb-0 fw-semibold">Business Owner Account</h6>
                <small class="text-muted">The person who'll log into the admin panel for this tenant.</small></div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Full name</label>
                    <input type="text" name="owner_name" value="{{ old('owner_name') }}" required
                           class="form-control @error('owner_name') is-invalid @enderror">
                    @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="owner_phone" value="{{ old('owner_phone') }}" class="form-control">
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Email (login)</label>
                    <input type="email" name="owner_email" value="{{ old('owner_email') }}" required
                           class="form-control @error('owner_email') is-invalid @enderror">
                    @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Initial password</label>
                    <div class="input-group">
                        <input type="password" name="owner_password" value="{{ old('owner_password') }}" required minlength="8"
                               id="owner_password"
                               class="form-control font-monospace @error('owner_password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" id="toggleOwnerPassword"
                                tabindex="-1" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                        @error('owner_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Share this with the owner securely. They can change it after first login.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <a href="{{ route('super.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-building-add me-1"></i>Create Tenant
        </button>
    </div>
</form>

</div>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('toggleOwnerPassword')?.addEventListener('click', function () {
        const input = document.getElementById('owner_password');
        const icon = this.querySelector('i');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
</script>
@endpush
