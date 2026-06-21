@extends('layouts.app')
@section('title', 'My Account')

@section('content')

<x-page-header title="My Account"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i>
            {{ session('success') }}
        </div>
        @endif

        {{-- Account Details --}}
        <div class="card mb-4" id="details">
            <div class="card-header set-head">
                <div>
                    <h6 class="mb-0 fw-semibold">Account Details</h6>
                    <small class="text-muted">Your login name and email address.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.account.update') }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Full name</label>
                            <input type="text" name="name"
                                   value="{{ old('name', auth()->user()->name) }}"
                                   required class="form-control @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Login email</label>
                            <input type="email" name="email"
                                   value="{{ old('email', auth()->user()->email) }}"
                                   required class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Change Password --}}
        <div class="card mb-4" id="password">
            <div class="card-header set-head">
                <div>
                    <h6 class="mb-0 fw-semibold">Change Password</h6>
                    <small class="text-muted">Use a strong password you don't use elsewhere.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.account.password') }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current password</label>
                            <input type="password" name="current_password" autocomplete="current-password"
                                   required class="form-control @error('current_password') is-invalid @enderror">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">New password</label>
                            <input type="password" name="password" autocomplete="new-password"
                                   required class="form-control @error('password') is-invalid @enderror">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Confirm new password</label>
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                   required class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-lock me-1"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection
