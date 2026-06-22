@extends('layouts.super')
@section('title', 'Platform Settings')

@push('styles')
@include('super._partials.premium-ui')
<style>
    .gateway-tile {
        border: 1px solid var(--bs-border-color); border-radius: .9rem; padding: 1.25rem;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .gateway-tile:hover { border-color: rgba(16,185,129,.35); box-shadow: 0 12px 26px -20px rgba(0,0,0,.4); }
    .gw-logo {
        width: 46px; height: 46px; flex-shrink: 0; border-radius: 12px;
        display: grid; place-items: center; font-size: 1.4rem; color: #fff;
        box-shadow: 0 8px 18px -10px rgba(0,0,0,.5);
    }
    .gw-paymongo .gw-logo { background: linear-gradient(135deg, #2563eb, #1e3a8a); }
    .gw-stripe   .gw-logo { background: linear-gradient(135deg, #635bff, #4338ca); }
    .gw-webhook-url {
        display: flex; align-items: center; gap: .5rem;
        background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color);
        border-radius: .5rem; padding: .45rem .75rem; font-size: .8rem;
    }
    .gw-webhook-url code { flex: 1; word-break: break-all; background: none; padding: 0; font-size: inherit; }
    .gw-copy-btn {
        flex-shrink: 0; background: none; border: none; padding: 0 .25rem;
        color: var(--bs-secondary-color); cursor: pointer; font-size: .85rem;
    }
    .gw-copy-btn:hover { color: #10b981; }

    /* Settings tabs */
    .stg-tabs { display: flex; border-bottom: 1px solid var(--bs-border-color); margin-bottom: 1.5rem; gap: 0; }
    .stg-tab {
        padding: .7rem 1.25rem; font-size: .875rem; font-weight: 500;
        border: 0; background: none; color: var(--bs-secondary-color); text-decoration: none;
        border-bottom: 2px solid transparent; margin-bottom: -1px;
        transition: color .15s ease, border-color .15s ease; white-space: nowrap;
    }
    .stg-tab:hover { color: var(--bs-body-color); }
    .stg-tab.active { color: #10b981; border-bottom-color: #10b981; font-weight: 600; }
</style>
@endpush

@section('content')

<x-page-header title="Platform Settings" subtitle="Branding and platform-level subscription billing"/>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">

        {{-- Tab bar --}}
        @php $activeTab = request('tab', 'branding'); @endphp
        <div class="stg-tabs">
            <a href="{{ route('super.settings.index') }}?tab=branding"
               class="stg-tab {{ $activeTab === 'branding' ? 'active' : '' }}">Branding</a>
            <a href="{{ route('super.settings.index') }}?tab=gateways"
               class="stg-tab {{ $activeTab === 'gateways' ? 'active' : '' }}">Payment Gateways</a>
            <a href="{{ route('super.settings.index') }}?tab=backups"
               class="stg-tab {{ $activeTab === 'backups' ? 'active' : '' }}">Backups</a>
        </div>

        {{-- ── Branding ── --}}
        @if($activeTab === 'branding')
        @php
            $logoUrl    = file_url($branding['logo'] ?? null);
            $faviconUrl = file_url($branding['favicon'] ?? null);
        @endphp
        <div class="card">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-palette"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Platform Branding</h6>
                    <small class="text-muted">
                        Your logo appears in the Super Admin sidebar; the favicon shows in the browser tab across the whole product.
                        These are separate from each tenant's own logo.
                    </small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('super.settings.branding') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="row g-4">
                        {{-- Logo --}}
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Logo</label>
                            @if($logoUrl)
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="{{ $logoUrl }}" alt="Logo"
                                         class="border rounded" style="width:64px;height:64px;object-fit:contain;background:#fff;padding:4px">
                                    <div class="form-check small">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                        <label class="form-check-label text-danger" for="remove_logo">Remove logo</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                   class="form-control @error('logo') is-invalid @enderror">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">PNG / JPG / SVG · square works best · max 2 MB</div>
                        </div>

                        {{-- Favicon --}}
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-medium">Favicon</label>
                            @if($faviconUrl)
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="{{ $faviconUrl }}" alt="Favicon"
                                         class="border rounded" style="width:32px;height:32px;object-fit:contain;background:#fff;padding:2px">
                                    <div class="form-check small">
                                        <input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="remove_favicon">
                                        <label class="form-check-label text-danger" for="remove_favicon">Remove favicon</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="favicon" accept="image/png,image/svg+xml,image/webp,.ico"
                                   class="form-control @error('favicon') is-invalid @enderror">
                            @error('favicon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">ICO / PNG / SVG · 32×32 or larger · max 512 KB</div>
                        </div>

                        <div class="col-12 d-flex justify-content-end border-top pt-3 mt-1">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Branding
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- ── Payment Gateways ── --}}
        @if($activeTab === 'gateways')
        @php
            $pm   = $credentials['paymongo'] ?? [];
            $sp   = $credentials['stripe']   ?? [];
            $mask = fn ($v) => $v ? str_repeat('•', 6) . substr($v, -4) : '';
        @endphp
        <div class="card">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-credit-card-2-front"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Subscription Billing — Payment Gateways</h6>
                    <small class="text-muted">
                        Connect the platform's PayMongo or Stripe account to collect subscription dues from tenants.
                        These are separate from each tenant's own gateway keys.
                    </small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('super.settings.gateways') }}">
                    @csrf @method('PUT')

                    {{-- PayMongo --}}
                    <div class="gateway-tile gw-paymongo mb-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="gw-logo"><i class="bi bi-wallet2"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-0 fw-semibold">PayMongo</h6>
                                <small class="text-muted d-block">GCash · Maya · QR Ph · Cards (Philippines)</small>
                                <small class="text-muted">
                                    Sign up at <a href="https://dashboard.paymongo.com/signup" target="_blank" rel="noopener">dashboard.paymongo.com</a>
                                </small>
                            </div>
                            <div class="form-check form-switch flex-shrink-0">
                                <input type="hidden" name="paymongo_enabled" value="0">
                                <input type="checkbox" name="paymongo_enabled" value="1" class="form-check-input"
                                       id="pm_enabled" @checked($pm['enabled'] ?? false)>
                                <label class="form-check-label small" for="pm_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-medium small">Secret key <span class="text-muted fw-normal">(sk_live_…)</span></label>
                                <input type="password" name="paymongo_secret_key" autocomplete="off"
                                       placeholder="{{ $mask($pm['secret_key'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <div class="form-text">From PayMongo → Developers → API Keys. Leave blank to keep current.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-medium small">Webhook secret <span class="text-muted fw-normal">(whsk_…)</span></label>
                                <input type="password" name="paymongo_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($pm['webhook_secret'] ?? null) ?: 'whsk_…' }}"
                                       class="form-control font-monospace">
                                <div class="form-text">Optional. Leave blank to keep current.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium small">Payment methods</label>
                                <div class="form-text mb-2">Must match what's enabled in your PayMongo dashboard.</div>
                                @php $pmMethods = $pm['methods'] ?? ['gcash','paymaya','card','qrph']; @endphp
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach(['gcash' => 'GCash', 'paymaya' => 'Maya', 'card' => 'Card', 'qrph' => 'QR Ph'] as $val => $label)
                                    <div class="form-check border rounded px-3 py-2 mb-0">
                                        <input class="form-check-input" type="checkbox"
                                               name="paymongo_methods[]" value="{{ $val }}"
                                               id="pm_method_{{ $val }}"
                                               @checked(in_array($val, $pmMethods))>
                                        <label class="form-check-label small" for="pm_method_{{ $val }}">{{ $label }}</label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium small">Webhook URL <span class="text-muted fw-normal">— paste into PayMongo dashboard</span></label>
                                <div class="gw-webhook-url" x-data="{ copied: false }">
                                    <code x-ref="pmUrl">{{ route('api.v1.webhooks.platform.paymongo') }}</code>
                                    <button type="button" class="gw-copy-btn"
                                            @click="navigator.clipboard.writeText($refs.pmUrl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)">
                                        <span x-show="!copied"><i class="bi bi-clipboard"></i></span>
                                        <span x-show="copied" x-cloak><i class="bi bi-check2 text-success"></i></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Stripe --}}
                    <div class="gateway-tile gw-stripe mb-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="gw-logo"><i class="bi bi-credit-card-2-front"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-0 fw-semibold">Stripe</h6>
                                <small class="text-muted d-block">International cards</small>
                                <small class="text-muted">
                                    Sign up at <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">dashboard.stripe.com</a>
                                </small>
                            </div>
                            <div class="form-check form-switch flex-shrink-0">
                                <input type="hidden" name="stripe_enabled" value="0">
                                <input type="checkbox" name="stripe_enabled" value="1" class="form-check-input"
                                       id="sp_enabled" @checked($sp['enabled'] ?? false)>
                                <label class="form-check-label small" for="sp_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-medium small">Secret key <span class="text-muted fw-normal">(sk_live_…)</span></label>
                                <input type="password" name="stripe_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['secret'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <div class="form-text">From Stripe → Developers → API Keys. Leave blank to keep current.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-medium small">Webhook signing secret <span class="text-muted fw-normal">(whsec_…)</span></label>
                                <input type="password" name="stripe_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['webhook_secret'] ?? null) ?: 'whsec_…' }}"
                                       class="form-control font-monospace">
                                <div class="form-text">Optional. Leave blank to keep current.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium small">Webhook URL <span class="text-muted fw-normal">— paste into Stripe dashboard</span></label>
                                <div class="gw-webhook-url" x-data="{ copied: false }">
                                    <code x-ref="spUrl">{{ route('api.v1.webhooks.platform.stripe') }}</code>
                                    <button type="button" class="gw-copy-btn"
                                            @click="navigator.clipboard.writeText($refs.spUrl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)">
                                        <span x-show="!copied"><i class="bi bi-clipboard"></i></span>
                                        <span x-show="copied" x-cloak><i class="bi bi-check2 text-success"></i></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-shield-lock me-1"></i>
                        Keys are encrypted at rest and never shown back after saving. If you lose a key, generate a new one in your gateway dashboard and paste it here.
                    </div>

                    <div class="d-flex justify-content-end border-top pt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- ── Database Backups ── --}}
        @if($activeTab === 'backups')
        <div class="card">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-database-down"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Database Backups</h6>
                    <small class="text-muted">Daily at 2:00 AM · last 3 kept</small>
                </div>
            </div>

            @if(count($backups) === 0)
                <x-empty-state title="No backups yet" icon="bi-database-x"
                    description="The first backup will run tonight at 2:00 AM."/>
            @else
            <table class="table table-hover mb-0 align-middle table-stack">
                <thead class="table-light">
                    <tr>
                        <th style="text-transform:none;letter-spacing:0;">Backup File</th>
                        <th style="text-transform:none;letter-spacing:0;">Created</th>
                        <th style="text-transform:none;letter-spacing:0;">Size</th>
                        <th class="cell-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($backups as $i => $backup)
                    <tr>
                        <td data-label="File" class="cell-plain">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-zip text-primary flex-shrink-0" style="font-size:1.1rem;"></i>
                                <span class="font-monospace small">{{ $backup['name'] }}</span>
                                @if($i === 0)
                                    <span class="badge bg-success-subtle text-success-emphasis">Latest</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Created" class="small text-muted text-nowrap">
                            {{ $backup['created_at']->format('M d, Y h:i A') }}
                        </td>
                        <td data-label="Size" class="small text-muted">
                            {{ number_format($backup['size'] / 1024, 1) }} KB
                        </td>
                        <td class="cell-actions">
                            <a href="{{ $backup['download_url'] }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @endif

    </div>
</div>

@endsection
