@extends('layouts.app')
@section('title', 'Membership Plans')

@push('styles')
<style>
    .plan-card {
        position: relative; overflow: hidden; height: 100%;
        --accent: #10b981; --accent-rgb: 16,185,129;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .plan-card.is-vip { --accent: #f59e0b; --accent-rgb: 245,158,11; border-color: rgba(245,158,11,.35); }
    .plan-card:hover {
        transform: translateY(-3px);
        border-color: rgba(var(--accent-rgb), .4);
        box-shadow: 0 12px 28px -10px rgba(0,0,0,.18);
    }
    .plan-accent { height: 4px; background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb),.2)); }
    .plan-price { font-size: 2rem; font-weight: 700; letter-spacing: -.02em; line-height: 1; }
    .plan-feature { display: flex; gap: .55rem; align-items: flex-start; font-size: .85rem; }
    .plan-feature i { color: var(--accent); margin-top: .15rem; flex-shrink: 0; }
    .plan-state-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
    .plan-sub-count {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
        color: var(--bs-secondary-color);
    }
    @media (max-width: 575.98px) {
        .plan-card-actions { flex-direction: column; }
        .plan-card-actions .btn { flex: 1; width: 100%; justify-content: center; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Membership Plans">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.memberships.index') }}"
               class="btn btn-outline-secondary">Members</a>
            <a href="{{ route('admin.memberships.plans') }}"
               class="btn btn-primary">Plans</a>
        </div>
        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#create-plan">
            <i class="bi bi-plus-lg"></i>New Plan
        </button>
    </x-slot>
</x-page-header>

{{-- Plans grid --}}
@if($plans->isEmpty())
<div class="card">
    <x-empty-state title="No membership plans yet" icon="bi-grid"
        description="Create your first plan to start assigning memberships."/>
</div>
@else
<div class="row g-4">
    @foreach($plans as $plan)
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card plan-card {{ $plan->is_vip ? 'is-vip' : '' }} overflow-hidden">
            <div class="plan-accent"></div>
            <div class="card-body d-flex flex-column gap-0">

                {{-- Header: name + badges --}}
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $plan->name }}</h6>
                        <small class="text-muted text-capitalize">{{ $plan->billing_cycle }}</small>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        @if(!$plan->is_active)
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                        @if($plan->is_vip)
                        <span class="badge rounded-pill bg-warning-subtle text-warning"><i class="bi bi-star-fill me-1"></i>VIP</span>
                        @endif
                    </div>
                </div>

                {{-- Price --}}
                <div class="mb-3">
                    <span class="plan-price">₱{{ number_format($plan->price) }}</span>
                    <span class="text-muted small">/{{ $plan->billing_cycle }}</span>
                </div>

                {{-- Features --}}
                <ul class="list-unstyled flex-grow-1 mb-3 d-flex flex-column gap-2">
                    <li class="plan-feature">
                        <i class="bi bi-check-circle-fill"></i><span>{{ $plan->court_hours }} hours of court time</span>
                    </li>
                    @if($plan->discount_percent > 0)
                    <li class="plan-feature">
                        <i class="bi bi-check-circle-fill"></i><span>{{ $plan->discount_percent }}% booking discount</span>
                    </li>
                    @endif
                    @foreach(($plan->features ?? []) as $feature)
                    <li class="plan-feature">
                        <i class="bi bi-check-circle-fill"></i><span>{{ $feature }}</span>
                    </li>
                    @endforeach
                </ul>

                {{-- Footer: subscriber count + actions --}}
                <div class="pt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="plan-sub-count">
                            <i class="bi bi-people"></i>
                            {{ $plan->active_memberships_count }} active
                        </span>
                        <span class="small fw-medium {{ $plan->is_active ? 'text-success' : 'text-muted' }}">
                            <span class="plan-state-dot me-1" style="background:{{ $plan->is_active ? '#22c55e' : '#94a3b8' }}"></span>
                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="d-flex gap-2 plan-card-actions">
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm flex-grow-1 edit-plan-btn"
                                data-bs-toggle="modal" data-bs-target="#edit-plan"
                                data-plan-id="{{ $plan->id }}"
                                data-name="{{ $plan->name }}"
                                data-billing-cycle="{{ $plan->billing_cycle }}"
                                data-price="{{ $plan->price }}"
                                data-court-hours="{{ $plan->court_hours }}"
                                data-discount-percent="{{ $plan->discount_percent }}"
                                data-is-vip="{{ $plan->is_vip ? '1' : '0' }}"
                                data-is-active="{{ $plan->is_active ? '1' : '0' }}">
                            <i class="bi bi-pencil"></i>Edit
                        </button>
                        <form method="POST" action="{{ route('admin.memberships.plans.destroy', $plan) }}"
                              onsubmit="return confirm('Delete this plan? Existing members will not be affected.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete plan">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Create Plan Modal --}}
<x-modal name="create-plan" title="Create New Plan">
    <form method="POST" action="{{ route('admin.memberships.plans.store') }}" id="create-plan-form">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Plan name</label>
                <input type="text" name="name" required class="form-control" placeholder="e.g. Premium Monthly">
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Billing cycle</label>
                <select name="billing_cycle" class="form-select">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                    <option value="lifetime">Lifetime</option>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Price (₱)</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="price" min="0" step="100" required class="form-control">
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Hours of court time</label>
                <div class="input-group">
                    <input type="number" name="court_hours" min="0" value="10" class="form-control">
                    <span class="input-group-text">hrs</span>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Booking discount (%)</label>
                <div class="input-group">
                    <input type="number" name="discount_percent" min="0" max="100" value="0" class="form-control">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="is_vip" value="1" id="is_vip" class="form-check-input">
                    <label class="form-check-label" for="is_vip">VIP plan</label>
                </div>
            </div>
        </div>
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="create-plan-form" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Create Plan
        </button>
    </x-slot>
</x-modal>

{{-- Edit Plan Modal --}}
<x-modal name="edit-plan" title="Edit Plan">
    <form method="POST" action="" id="edit-plan-form">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Plan name</label>
                <input type="text" name="name" required class="form-control" data-field="name">
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Billing cycle</label>
                <select name="billing_cycle" class="form-select" data-field="billing_cycle">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                    <option value="lifetime">Lifetime</option>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Price (₱)</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" name="price" min="0" step="100" required class="form-control" data-field="price">
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Hours of court time</label>
                <div class="input-group">
                    <input type="number" name="court_hours" min="0" class="form-control" data-field="court_hours">
                    <span class="input-group-text">hrs</span>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label">Booking discount (%)</label>
                <div class="input-group">
                    <input type="number" name="discount_percent" min="0" max="100" class="form-control" data-field="discount_percent">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="form-check">
                    <input type="hidden" name="is_vip" value="0">
                    <input type="checkbox" name="is_vip" value="1" id="edit_is_vip" class="form-check-input" data-field="is_vip">
                    <label class="form-check-label" for="edit_is_vip">VIP plan</label>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="edit_is_active" class="form-check-input" data-field="is_active">
                    <label class="form-check-label" for="edit_is_active">Active</label>
                </div>
            </div>
        </div>
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="edit-plan-form" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>Save Changes
        </button>
    </x-slot>
</x-modal>

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('edit-plan');
    if (!modal) return;
    const form = document.getElementById('edit-plan-form');
    // Build once; swap the trailing ID at click time.
    const updateUrlTemplate = @js(route('admin.memberships.plans.update', ['plan' => '__ID__']));

    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;

        form.action = updateUrlTemplate.replace('__ID__', btn.dataset.planId);

        form.querySelectorAll('[data-field]').forEach(el => {
            const value = btn.dataset[
                el.dataset.field.replace(/_([a-z])/g, (_, c) => c.toUpperCase())
            ];
            if (el.type === 'checkbox') {
                el.checked = value === '1';
            } else {
                el.value = value ?? '';
            }
        });
    });
})();
</script>
@endpush

@endsection
