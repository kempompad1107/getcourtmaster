@extends('layouts.app')
@section('title', 'Create Promotion')

@section('content')

<x-page-header title="Create Promotion" :back="route('admin.promotions.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.promotions.store') }}" x-data="{ promoType: 'percentage' }">
    @csrf

    {{-- Promotion details --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Promotion Details</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">Promotion name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. Early Bird Special"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Promo code <span class="text-danger">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}" required
                           placeholder="EARLYBIRD20" style="text-transform:uppercase"
                           class="form-control font-monospace @error('code') is-invalid @enderror">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Applies to</label>
                    <select name="applies_to" class="form-select">
                        <option value="all">All (courts, memberships, POS)</option>
                        <option value="courts">Courts only</option>
                        <option value="memberships">Memberships only</option>
                        <option value="pos">POS only</option>
                    </select>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Discount type</label>
                    <select name="type" class="form-select" x-model="promoType">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed amount (₱)</option>
                    </select>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Discount value <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" x-text="promoType === 'percentage' ? '%' : '₱'">%</span>
                        <input type="number" name="value" value="{{ old('value') }}" min="0" step="0.01" required
                               class="form-control">
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Min. booking amount (₱) <span class="text-muted small fw-normal">— optional</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="min_booking_amount" value="{{ old('min_booking_amount') }}"
                               min="0" step="50" class="form-control">
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Max discount amount (₱) <span class="text-muted small fw-normal">— optional</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="max_discount_amount" value="{{ old('max_discount_amount') }}"
                               min="0" step="50" class="form-control">
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Max total uses <span class="text-muted small fw-normal">— optional</span></label>
                    <input type="number" name="max_uses" value="{{ old('max_uses') }}" min="1"
                           placeholder="Unlimited" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Max uses per customer <span class="text-muted small fw-normal">— optional</span></label>
                    <input type="number" name="max_uses_per_user" value="{{ old('max_uses_per_user') }}" min="1"
                           placeholder="Unlimited" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Start date</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at') }}" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Expiry date</label>
                    <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Description <span class="text-muted small fw-normal">— optional</span></label>
                    <textarea name="description" rows="2" class="form-control"
                              placeholder="Optional description for staff reference">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active_promo"
                   class="form-check-input" checked>
            <label class="form-check-label fw-medium" for="is_active_promo">Active (customers can use this code)</label>
        </div>
        <div class="d-flex gap-2 ms-auto">
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Create Promotion
            </button>
        </div>
    </div>

</form>

</div>
</div>

@endsection
