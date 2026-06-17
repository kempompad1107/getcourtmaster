@extends('layouts.app')
@section('title', 'Add Staff')

@section('content')

<x-page-header title="Add Staff Member" :back="route('admin.staff.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-7">

<form method="POST" action="{{ route('admin.staff.store') }}">
    @csrf

    {{-- Staff details --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Staff Details</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Email address <span class="text-danger">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone number</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Role <span class="text-danger">*</span></label>
                    <select name="role" required class="form-select">
                        <option value="front_desk" @selected(old('role') === 'front_desk')>Front Desk</option>
                        <option value="inventory_manager" @selected(old('role') === 'inventory_manager')>Inventory Manager</option>
                        <option value="manager" @selected(old('role') === 'manager')>Manager</option>
                    </select>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Initial password <span class="text-danger">*</span></label>
                    <input type="password" name="password" required minlength="8"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Min. 8 characters">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if($branches->isEmpty())
                    <div class="col-12">
                        <div class="alert alert-warning small mb-0">
                            No active branches yet. <a href="{{ route('admin.branches.create') }}">Add a branch</a> before creating staff.
                        </div>
                    </div>
                @else
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Primary branch <span class="text-danger">*</span></label>
                        <select name="branch_id" required class="form-select @error('branch_id') is-invalid @enderror">
                            <option value="">— Select branch —</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if($branches->count() > 1)
                        <div class="col-12">
                            <label class="form-label fw-medium">Also assigned to</label>
                            <div class="row g-2">
                                @foreach($branches as $branch)
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="assigned_branches[]" value="{{ $branch->id }}"
                                                   id="ab_create_{{ $branch->id }}"
                                                   @checked(in_array($branch->id, old('assigned_branches', [])))>
                                            <label class="form-check-label small" for="ab_create_{{ $branch->id }}">
                                                {{ $branch->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text">Primary branch is included automatically. Tick additional branches the staff can also work at.</div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <p class="text-muted small mb-0">
            <i class="bi bi-envelope-check me-1"></i>The staff member will receive an email to verify their account.
        </p>
        <div class="d-flex gap-2 ms-auto">
            <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Add Staff Member
            </button>
        </div>
    </div>

</form>

</div>
</div>

@endsection
