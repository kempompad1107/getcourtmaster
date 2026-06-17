@extends('layouts.super')
@section('title', 'New Plan')

@section('content')

<x-page-header title="Create Subscription Plan" :back="route('super.plans.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-success"></i>
                <div><h6 class="mb-0 fw-semibold">Plan details</h6>
                    <small class="text-muted">Pricing and per-tenant resource limits.</small></div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('super.plans.store') }}">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Plan name</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Slug</label>
                            <input type="text" name="slug" value="{{ old('slug') }}" required
                                   placeholder="starter"
                                   class="form-control font-monospace @error('slug') is-invalid @enderror">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Trial days</label>
                            <input type="number" name="trial_days" value="{{ old('trial_days', 14) }}" min="0"
                                   class="form-control @error('trial_days') is-invalid @enderror">
                            @error('trial_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Monthly price (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="price_monthly" value="{{ old('price_monthly') }}"
                                       min="0" step="0.01" required
                                       class="form-control @error('price_monthly') is-invalid @enderror">
                                @error('price_monthly')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Yearly price (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="price_yearly" value="{{ old('price_yearly') }}"
                                       min="0" step="0.01"
                                       class="form-control @error('price_yearly') is-invalid @enderror">
                                @error('price_yearly')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small fw-medium mb-2">Limits <span class="fw-normal">(leave blank for unlimited)</span></p>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Max branches</label>
                            <input type="number" name="max_branches" value="{{ old('max_branches') }}"
                                   min="1" placeholder="Unlimited"
                                   class="form-control @error('max_branches') is-invalid @enderror">
                            @error('max_branches')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Max courts</label>
                            <input type="number" name="max_courts" value="{{ old('max_courts') }}"
                                   min="1" placeholder="Unlimited"
                                   class="form-control @error('max_courts') is-invalid @enderror">
                            @error('max_courts')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Max staff</label>
                            <input type="number" name="max_staff" value="{{ old('max_staff') }}"
                                   min="1" placeholder="Unlimited"
                                   class="form-control @error('max_staff') is-invalid @enderror">
                            @error('max_staff')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Max customers</label>
                            <input type="number" name="max_customers" value="{{ old('max_customers') }}"
                                   min="1" placeholder="Unlimited"
                                   class="form-control @error('max_customers') is-invalid @enderror">
                            @error('max_customers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="is_active" checked
                               class="form-check-input">
                        <label for="is_active" class="form-check-label fw-medium">
                            Active <span class="text-muted fw-normal">(available for new sign-ups)</span>
                        </label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('super.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
