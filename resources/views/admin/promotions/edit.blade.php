@extends('layouts.app')
@section('title', 'Edit ' . $promotion->code)

@section('content')

<x-page-header :title="'Edit: ' . $promotion->code" :back="route('admin.promotions.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Promotion name</label>
                            <input type="text" name="name" value="{{ old('name', $promotion->name) }}" required
                                   class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Code</label>
                            <input type="text" value="{{ $promotion->code }}" disabled
                                   class="form-control font-monospace">
                            <div class="form-text">Promo code cannot be changed.</div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Applies to</label>
                            <select name="applies_to" class="form-select">
                                @foreach(['all' => 'All', 'courts' => 'Courts only', 'memberships' => 'Memberships only', 'pos' => 'POS only'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('applies_to', $promotion->applies_to) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Discount type</label>
                            <select name="type" class="form-select">
                                <option value="percentage" @selected(old('type', $promotion->type) === 'percentage')>Percentage (%)</option>
                                <option value="fixed" @selected(old('type', $promotion->type) === 'fixed')>Fixed amount (₱)</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Discount value</label>
                            <input type="number" name="value" value="{{ old('value', $promotion->value) }}"
                                   min="0" step="0.01" required class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Min. booking amount (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="min_booking_amount"
                                       value="{{ old('min_booking_amount', $promotion->min_booking_amount) }}"
                                       min="0" step="50" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Max discount (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="max_discount_amount"
                                       value="{{ old('max_discount_amount', $promotion->max_discount_amount) }}"
                                       min="0" step="50" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Max total uses</label>
                            <input type="number" name="max_uses"
                                   value="{{ old('max_uses', $promotion->max_uses) }}" min="1"
                                   class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Max per customer</label>
                            <input type="number" name="max_uses_per_user"
                                   value="{{ old('max_uses_per_user', $promotion->max_uses_per_user) }}" min="1"
                                   class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Start date</label>
                            <input type="date" name="starts_at"
                                   value="{{ old('starts_at', $promotion->starts_at?->toDateString()) }}"
                                   class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Expiry date</label>
                            <input type="date" name="expires_at"
                                   value="{{ old('expires_at', $promotion->expires_at?->toDateString()) }}"
                                   class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="2"
                                      class="form-control">{{ old('description', $promotion->description) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" id="is_active_edit"
                                       class="form-check-input" @checked(old('is_active', $promotion->is_active))>
                                <label class="form-check-label" for="is_active_edit">Active</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
