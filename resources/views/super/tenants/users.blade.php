@extends('layouts.super')
@section('title', $tenant->name . ' — Users')

@section('content')

<x-page-header :title="$tenant->name . ' — Users'"
               :back="route('super.tenants.show', $tenant)"
               :subtitle="$users->total() . ' user' . ($users->total() === 1 ? '' : 's') . ' in this tenant'"/>

{{-- Counts --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-stat-card label="Owners" :value="(int) ($counts['business_owner'] ?? 0)" icon="bi-person-vcard" color="emerald"/>
    </div>
    <div class="col-6 col-md-3">
        <x-stat-card label="Staff" :value="(int) ($counts['staff'] ?? 0)" icon="bi-person-badge" color="blue"/>
    </div>
    <div class="col-6 col-md-3">
        <x-stat-card label="Customers" :value="(int) ($counts['customer'] ?? 0)" icon="bi-people" color="purple"/>
    </div>
    <div class="col-6 col-md-3">
        <x-stat-card label="Total" :value="$users->total()" icon="bi-person-lines-fill" color="primary"/>
    </div>
</div>

{{-- Flash with copyable temp password --}}
@if(session('success') && str_contains(session('success'), 'Temporary password'))
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-shield-lock mt-1"></i>
    <div class="flex-grow-1">
        <strong>{{ session('success') }}</strong>
        <p class="small mb-0 mt-1 text-muted">Copy this now — it will not be shown again.</p>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ explode(': ', session('success'))[1] ?? '' }}'.split(' —')[0]); this.innerText='Copied';">Copy</button>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Home Branch</th>
                    <th>Phone</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr>
                    <td>
                        <div class="fw-medium">{{ $u->name }}</div>
                        <div class="small text-muted">{{ $u->email }}</div>
                    </td>
                    <td>
                        @php
                            $typeClass = ['business_owner'=>'success','staff'=>'primary','customer'=>'secondary','super_admin'=>'danger'][$u->user_type] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $typeClass }}-subtle text-{{ $typeClass }}">{{ str_replace('_', ' ', ucfirst($u->user_type)) }}</span>
                    </td>
                    <td class="small text-muted">{{ $u->homeBranch?->name ?? '—' }}</td>
                    <td class="small text-muted">{{ $u->phone ?? '—' }}</td>
                    <td class="small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                    <td>
                        @if($u->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                        @if($u->two_factor_enabled)
                            <span class="badge bg-primary-subtle text-primary ms-1" title="2FA enabled"><i class="bi bi-shield-check"></i></span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if(! $u->isSuperAdmin())
                            <form method="POST" action="{{ route('super.tenants.users.reset-password', [$tenant, $u]) }}" class="d-inline"
                                  onsubmit="return confirm('Reset password for {{ addslashes($u->name) }}? A new temporary password will be generated.');">
                                @csrf
                                <button class="btn btn-sm btn-link p-0 text-warning" title="Reset password">
                                    <i class="bi bi-key"></i> Reset PW
                                </button>
                            </form>
                            @if($u->two_factor_enabled)
                                <form method="POST" action="{{ route('super.tenants.users.disable-2fa', [$tenant, $u]) }}" class="d-inline ms-2"
                                      onsubmit="return confirm('Remove 2FA for {{ addslashes($u->name) }}? They will be able to log in with their password alone until they re-enroll.');">
                                    @csrf
                                    <button class="btn btn-sm btn-link p-0 text-danger" title="Remove two-factor authentication">
                                        <i class="bi bi-shield-slash"></i> Remove 2FA
                                    </button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted small py-4">No users in this tenant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer d-flex justify-content-end">{{ $users->links() }}</div>
    @endif
</div>

@endsection
