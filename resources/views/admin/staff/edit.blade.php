@extends('layouts.app')
@section('title', 'Edit ' . $staff->name)

@section('content')

@php
    $profile = $staff->staffProfile;
    $assigned = old('assigned_branches', $profile?->assigned_branches ?? []);
    $primaryBranchId = old('branch_id', $profile?->branch_id);
@endphp

<x-page-header :title="'Edit: ' . $staff->name" :back="route('admin.staff.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.staff.update', $staff) }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full name</label>
                            <input type="text" name="name" value="{{ old('name', $staff->name) }}" required
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Email address</label>
                            <input type="email" value="{{ $staff->email }}" disabled class="form-control">
                            <div class="form-text">Email cannot be changed after creation.</div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Phone number</label>
                            <input type="tel" name="phone" value="{{ old('phone', $staff->phone) }}"
                                   class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                @foreach(['front_desk' => 'Front Desk', 'inventory_manager' => 'Inventory Manager', 'manager' => 'Manager'] as $val => $label)
                                <option value="{{ $val }}" @selected($staff->hasRole($val))>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($branches->isEmpty())
                            <div class="col-12">
                                <div class="alert alert-warning small mb-0">
                                    No active branches yet. <a href="{{ route('admin.branches.create') }}">Add a branch</a> before assigning this staff.
                                </div>
                            </div>
                        @else
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Primary branch</label>
                                <select name="branch_id" required class="form-select @error('branch_id') is-invalid @enderror">
                                    <option value="">— Select branch —</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) $primaryBranchId === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @if($branches->count() > 1)
                                <div class="col-12">
                                    <label class="form-label">Also assigned to</label>
                                    <div class="row g-2">
                                        @foreach($branches as $branch)
                                            <div class="col-6 col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="assigned_branches[]" value="{{ $branch->id }}"
                                                           id="ab_edit_{{ $branch->id }}"
                                                           @checked(in_array($branch->id, $assigned))>
                                                    <label class="form-check-label small" for="ab_edit_{{ $branch->id }}">
                                                        {{ $branch->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="form-text">Primary branch is included automatically.</div>
                                </div>
                            @endif
                        @endif

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" id="is_active"
                                       class="form-check-input" @checked($staff->is_active)>
                                <label class="form-check-label" for="is_active">Active (can log in)</label>
                            </div>
                        </div>

                        @if(auth()->user()->isBusinessOwner())
                            @php
                                $allRolePerms = $rolePermissions ?? [];
                                $extras = old('extra_permissions', $directPermissions ?? []);
                            @endphp
                            <div class="col-12">
                                <hr class="my-2">
                                <h6 class="fw-semibold mb-1">Extra Permissions</h6>
                                <p class="small text-muted mb-2">
                                    Grant this individual access beyond what their role allows.
                                    To change permissions for everyone in a role, edit the
                                    <a href="{{ route('admin.roles.index') }}">role itself</a> instead.
                                </p>

                                <div class="accordion" id="permsAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed small" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#permsBody">
                                                Show / edit individual permissions
                                            </button>
                                        </h2>
                                        <div id="permsBody" class="accordion-collapse collapse"
                                             data-bs-parent="#permsAccordion">
                                            <div class="accordion-body">
                                                @foreach($permissionGroups as $groupLabel => $perms)
                                                    <div class="mb-3">
                                                        <div class="fw-semibold small mb-1">{{ $groupLabel }}</div>
                                                        <div class="row g-1">
                                                            @foreach($perms as $perm)
                                                                @php
                                                                    $fromRole = in_array($perm, $allRolePerms);
                                                                    $checked  = $fromRole || in_array($perm, $extras);
                                                                @endphp
                                                                <div class="col-12 col-md-6">
                                                                    <div class="form-check">
                                                                        <input type="checkbox"
                                                                               class="form-check-input"
                                                                               name="extra_permissions[]"
                                                                               value="{{ $perm }}"
                                                                               id="extra_{{ $perm }}"
                                                                               @checked($checked)
                                                                               @disabled($fromRole)>
                                                                        <label class="form-check-label small" for="extra_{{ $perm }}">
                                                                            {{ $perm }}
                                                                            @if($fromRole)
                                                                                <span class="badge text-bg-light text-muted ms-1">from role</span>
                                                                            @endif
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                @can('delete', $staff)
                <hr class="my-4">
                <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}"
                      onsubmit="return confirm('Deactivate this staff member?')"
                      class="d-flex justify-content-end">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-link text-danger p-0">Deactivate staff member</button>
                </form>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection
