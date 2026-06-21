@extends('layouts.app')
@section('title', 'Roles & Permissions')

@push('styles')
<style>
    .role-ico {
        width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(16,185,129,.1); color: #10b981; border: 1px solid rgba(16,185,129,.2);
    }
    .perm-pill {
        display: inline-flex; align-items: center;
        font-size: .68rem; font-weight: 600; letter-spacing: .02em;
        padding: .2rem .55rem; border-radius: 99px;
        background: var(--bs-secondary-bg); color: var(--bs-secondary-color);
        border: 1px solid var(--bs-border-color);
        white-space: nowrap;
    }
    .perm-pills-wrap {
        display: flex; flex-wrap: wrap; gap: .3rem; align-items: center;
    }
</style>
@endpush

@section('content')

<x-page-header title="Roles & Permissions" :back="route('admin.settings.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">

        {{-- Subtle inline note replacing the heavy alert --}}
        <div class="d-flex align-items-start gap-2 mb-4 px-1">
            <i class="bi bi-info-circle text-muted flex-shrink-0 mt-1" style="font-size:.9rem"></i>
            <p class="small text-muted mb-0">
                These permissions control what each role can <strong>see and do</strong> in the admin panel.
                Changes apply immediately to every staff member with that role.
                The <strong>Business Owner</strong> role always has full access and cannot be edited.
            </p>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-stack table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Role</th>
                            <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Permissions granted</th>
                            <th class="cell-actions" style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)"></th>
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
                            $roleDesc = match($role->name) {
                                'manager'           => 'Broad operational access across the club.',
                                'front_desk'        => 'Bookings, customers, POS at the counter.',
                                'inventory_manager' => 'Stock, suppliers, purchase orders.',
                                default             => '',
                            };
                            $permCount = $role->permissions->count();
                        @endphp
                        <tr>
                            <td data-label="Role" class="cell-plain">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="role-ico"><i class="bi {{ $roleIcon }}"></i></span>
                                    <div>
                                        <div class="fw-semibold">{{ str_replace('_', ' ', ucwords($role->name, '_')) }}</div>
                                        @if($roleDesc)
                                            <div class="small text-muted">{{ $roleDesc }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Permissions">
                                @if($role->permissions->isEmpty())
                                    <span class="small text-muted">No permissions granted</span>
                                @else
                                    <div class="perm-pills-wrap">
                                        @foreach($role->permissions->take(3) as $perm)
                                            <span class="perm-pill">{{ ucwords(str_replace(['_', '.'], ' ', explode('.', $perm->name)[1] ?? $perm->name)) }}</span>
                                        @endforeach
                                        @if($permCount > 3)
                                            <span class="perm-pill">+{{ $permCount - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="cell-actions">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-sliders me-1"></i>Edit
                                </a>
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
