@extends('layouts.app')
@section('title', 'Roles & Permissions')

@push('styles')
<style>
    .role-ico {
        width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(16,185,129,.1); color: #10b981; border: 1px solid rgba(16,185,129,.2);
    }
    .role-table tbody tr { transition: background-color .15s; }
    @media (max-width: 575.98px) {
        .role-table thead { display: none; }
        .role-table, .role-table tbody, .role-table tr, .role-table td { display: block; width: 100%; }
        .role-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .role-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .role-table td + td { border-top: 1px solid var(--bs-border-color); }
        .role-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
    }
</style>
@endpush

@section('content')

<x-page-header title="Roles & Permissions" :back="route('admin.settings.index')">
    <x-slot name="actions">
        <a href="{{ route('admin.staff.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person-badge me-1"></i>Manage Staff
        </a>
    </x-slot>
</x-page-header>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">

        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            Permissions here control what each role can <strong>see and do</strong> across the admin sidebar.
            Changes apply immediately to every staff member with that role. The <strong>Business Owner</strong>
            role always has full access and cannot be edited.
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table role-table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th>Permissions granted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                        @php
                            $roleIcon = match($role->name) {
                                'manager'           => 'bi-person-gear',
                                'front_desk'        => 'bi-display',
                                'inventory_manager' => 'bi-box-seam',
                                default             => 'bi-person-badge',
                            };
                        @endphp
                        <tr>
                            <td data-label="Role">
                                <div class="d-flex align-items-center gap-2 justify-content-end justify-content-sm-start">
                                    <span class="role-ico"><i class="bi {{ $roleIcon }}"></i></span>
                                    <div>
                                        <p class="mb-0 fw-semibold">{{ str_replace('_', ' ', ucwords($role->name, '_')) }}</p>
                                        <small class="text-muted">
                                            @switch($role->name)
                                                @case('manager') Broad operational access across the club. @break
                                                @case('front_desk') Bookings, customers, POS at the counter. @break
                                                @case('inventory_manager') Stock, suppliers, purchase orders. @break
                                            @endswitch
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Permissions">
                                @if($role->permissions->isEmpty())
                                    <span class="text-muted small">No permissions granted</span>
                                @else
                                    <span class="badge rounded-pill bg-primary-subtle text-primary">
                                        {{ $role->permissions->count() }} permission{{ $role->permissions->count() === 1 ? '' : 's' }}
                                    </span>
                                @endif
                            </td>
                            <td data-label="" class="text-end">
                                <a href="{{ route('admin.roles.edit', $role) }}"
                                   class="btn btn-outline-primary btn-sm"><i class="bi bi-sliders me-1"></i>Edit permissions</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection
