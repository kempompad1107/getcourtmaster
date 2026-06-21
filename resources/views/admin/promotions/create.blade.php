@extends('layouts.app')
@section('title', 'Create Promotion')

@section('content')

<x-page-header title="Create Promotion"
    :back="route('admin.promotions.index')" backLabel="Promotions"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-7">

<form method="POST" action="{{ route('admin.promotions.store') }}" x-data="{ promoType: 'percentage' }">
    @csrf

    {{-- ① Basics --}}
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Basics</p>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Promotion name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. Early Bird Special"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Promo code <span class="text-danger">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}" required
                           placeholder="EARLYBIRD20" style="text-transform:uppercase"
                           class="form-control font-monospace @error('code') is-invalid @enderror">
                    <div class="form-text">Customers enter this at checkout.</div>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Applies to</label>
                    <select name="applies_to" class="form-select">
                        <option value="all">All (courts, memberships, POS)</option>
                        <option value="courts">Courts only</option>
                        <option value="memberships">Memberships only</option>
                        <option value="pos">POS only</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ② Discount --}}
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Discount</p>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" x-model="promoType">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed amount (₱)</option>
                    </select>
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Value <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" x-text="promoType === 'percentage' ? '%' : '₱'">%</span>
                        <input type="number" name="value" value="{{ old('value') }}" min="0" step="0.01" required
                               class="form-control @error('value') is-invalid @enderror">
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Min. booking amount <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="min_booking_amount" value="{{ old('min_booking_amount') }}"
                               min="0" step="50" placeholder="No minimum" class="form-control">
                    </div>
                </div>

                <div class="col-sm-6" x-show="promoType === 'percentage'">
                    <label class="form-label">Max discount cap <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="max_discount_amount" value="{{ old('max_discount_amount') }}"
                               min="0" step="50" placeholder="No cap" class="form-control">
                    </div>
                    <div class="form-text">Limits how much % discounts can save.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ③ Limits & Validity --}}
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Limits &amp; Validity</p>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Start date</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at') }}" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Expiry date</label>
                    <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Max total uses <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="number" name="max_uses" value="{{ old('max_uses') }}" min="1"
                           placeholder="Unlimited" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Max uses per customer <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="number" name="max_uses_per_user" value="{{ old('max_uses_per_user') }}" min="1"
                           placeholder="Unlimited" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label">Staff note <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="description" rows="2" class="form-control"
                              placeholder="Internal note — not shown to customers">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-4 mb-4">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active_promo"
                   class="form-check-input" checked>
            <label class="form-check-label" for="is_active_promo">
                Active — customers can use this code immediately
            </label>
        </div>
        <div class="d-flex gap-2 ms-auto">
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>Create Promotion
            </button>
        </div>
    </div>

</form>

</div>
</div>

@endsection
