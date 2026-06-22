@extends('layouts.super')
@section('title', 'Platform Settings')

@push('styles')
@include('super._partials.premium-ui')
<style>
    /* Payment gateway integration tiles */
    .gateway-tile {
        border: 1px solid var(--bs-border-color); border-radius: .9rem; padding: 1.1rem;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .gateway-tile:hover { border-color: rgba(16,185,129,.35); box-shadow: 0 12px 26px -20px rgba(0,0,0,.5); }
    .gw-logo {
        width: 46px; height: 46px; flex-shrink: 0; border-radius: 12px;
        display: grid; place-items: center; font-size: 1.5rem; color: #fff;
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
        color: var(--bs-secondary-color); cursor: pointer; font-size: .8rem;
    }
    .gw-copy-btn:hover { color: var(--bs-body-color); }
</style>
@endpush

@section('content')

<x-page-header title="Platform Settings" subtitle="Branding and platform-level subscription billing"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">

        {{-- Branding --}}
        @php
            $logoUrl    = file_url($branding['logo'] ?? null);
            $faviconUrl = file_url($branding['favicon'] ?? null);
        @endphp
        <div class="card mb-4">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-palette"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Platform Branding</h6>
                    <small class="text-muted">
                        Your logo appears in the Super Admin sidebar; the favicon is the small icon
                        shown in the browser tab across the whole product. These are separate from
                        each tenant's own logo.
                    </small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('super.settings.branding') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="row g-4">
                        {{-- Logo --}}
                        <div class="col-12 col-sm-6">
                            <label class="form-label"><i class="bi bi-image me-1 text-muted"></i>Logo</label>
                            @if($logoUrl)
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img src="{{ $logoUrl }}" alt="Logo"
                                         class="border rounded" style="width:64px;height:64px;object-fit:contain;background:#fff;padding:4px">
                                    <div class="form-check form-check-inline small">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                        <label class="form-check-label" for="remove_logo">Remove</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                   class="form-control form-control-sm @error('logo') is-invalid @enderror">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">PNG/JPG/SVG, square works best, max 2MB.</div>
                        </div>

                        {{-- Favicon --}}
                        <div class="col-12 col-sm-6">
                            <label class="form-label"><i class="bi bi-star me-1 text-muted"></i>Favicon</label>
                            @if($faviconUrl)
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img src="{{ $faviconUrl }}" alt="Favicon"
                                         class="border rounded" style="width:32px;height:32px;object-fit:contain;background:#fff;padding:2px">
                                    <div class="form-check form-check-inline small">
                                        <input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="remove_favicon">
                                        <label class="form-check-label" for="remove_favicon">Remove</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="favicon" accept="image/png,image/svg+xml,image/webp,.ico"
                                   class="form-control form-control-sm @error('favicon') is-invalid @enderror">
                            @error('favicon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">ICO/PNG/SVG, 32&times;32 or larger, max 512KB.</div>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Branding
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-credit-card-2-front"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Subscription Billing — Payment Gateways</h6>
                    <small class="text-muted">
                        Connect the platform's own PayMongo or Stripe account here. These keys are
                        used to collect monthly/yearly subscription dues from your tenants. They are
                        separate from each tenant's own gateway keys (which settle the tenant's
                        booking/POS revenue to the tenant's bank).
                    </small>
                </div>
            </div>
            <div class="card-body">
                @php
                    $pm   = $credentials['paymongo'] ?? [];
                    $sp   = $credentials['stripe']   ?? [];
                    $mask = fn ($v) => $v ? str_repeat('•', 6) . substr($v, -4) : '';
                @endphp

                <form method="POST" action="{{ route('super.settings.gateways') }}">
                    @csrf @method('PUT')

                    {{-- PayMongo --}}
                    <div class="gateway-tile gw-paymongo mb-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="gw-logo"><i class="bi bi-wallet2"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-0 fw-semibold">PayMongo</h6>
                                <small class="text-muted d-block">GCash, Maya, QR Ph, cards (Philippines)</small>
                                <small class="text-muted">Sign up at <a href="https://dashboard.paymongo.com/signup" target="_blank" rel="noopener">dashboard.paymongo.com</a></small>
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
                                <label class="form-label small"><i class="bi bi-key me-1 text-muted"></i>Secret key <small class="text-muted">(starts with sk_live_…)</small></label>
                                <input type="password" name="paymongo_secret_key" autocomplete="off"
                                       placeholder="{{ $mask($pm['secret_key'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <small class="text-muted">From PayMongo dashboard → Developers → API Keys. Leave blank to keep what's saved.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small"><i class="bi bi-link-45deg me-1 text-muted"></i>Webhook secret <small class="text-muted">(starts with whsk_…)</small></label>
                                <input type="password" name="paymongo_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($pm['webhook_secret'] ?? null) ?: 'whsk_…' }}"
                                       class="form-control font-monospace">
                                <small class="text-muted">Optional. Leave blank to keep what's saved.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small"><i class="bi bi-grid me-1 text-muted"></i>Payment methods <small class="text-muted">— must match what you've enabled in your PayMongo dashboard</small></label>
                                @php $pmMethods = $pm['methods'] ?? ['gcash','paymaya','card','qrph']; @endphp
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    @foreach(['gcash' => 'GCash', 'paymaya' => 'Maya', 'card' => 'Card', 'qrph' => 'QR Ph'] as $val => $label)
                                    <div class="form-check form-check-inline border rounded px-3 py-2 mb-0">
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
                                <label class="form-label small"><i class="bi bi-globe me-1 text-muted"></i>Webhook URL <small class="text-muted">— paste into PayMongo dashboard</small></label>
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
                                <small class="text-muted">Sign up at <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">dashboard.stripe.com</a></small>
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
                                <label class="form-label small"><i class="bi bi-key me-1 text-muted"></i>Secret key <small class="text-muted">(starts with sk_live_…)</small></label>
                                <input type="password" name="stripe_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['secret'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <small class="text-muted">From Stripe dashboard → Developers → API Keys. Leave blank to keep what's saved.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small"><i class="bi bi-link-45deg me-1 text-muted"></i>Webhook signing secret <small class="text-muted">(starts with whsec_…)</small></label>
                                <input type="password" name="stripe_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['webhook_secret'] ?? null) ?: 'whsec_…' }}"
                                       class="form-control font-monospace">
                                <small class="text-muted">Optional. Leave blank to keep what's saved.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small"><i class="bi bi-globe me-1 text-muted"></i>Webhook URL <small class="text-muted">— paste into Stripe dashboard</small></label>
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

                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-shield-lock me-1"></i>
                        Keys are encrypted at rest and never shown back after saving. If you lose a
                        key, create a new one in your PayMongo or Stripe dashboard and paste it here.
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Platform Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Database Backups --}}
        <div class="card mb-4">
            <div class="card-header step-head">
                <span class="head-icon"><i class="bi bi-database-down"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Database Backups</h6>
                    <small class="text-muted">
                        Automated daily backups at 2:00 AM (Asia/Manila). Only the last 3 backups are kept.
                    </small>
                </div>
            </div>
            <div class="card-body p-0">
                @if(count($backups) === 0)
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-database-x fs-2 d-block mb-2"></i>
                        No backups found yet. The first backup will run tonight at 2:00 AM.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Backup File</th>
                                    <th>Created</th>
                                    <th>Size</th>
                                    <th class="text-end pe-4">Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backups as $i => $backup)
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-zip text-primary"></i>
                                            <span class="font-monospace small">{{ $backup['name'] }}</span>
                                            @if($i === 0)
                                                <span class="badge bg-success-subtle text-success ms-1">Latest</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span title="{{ $backup['created_at']->format('Y-m-d H:i:s T') }}">
                                            {{ $backup['created_at']->format('M d, Y h:i A') }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        {{ number_format($backup['size'] / 1024, 1) }} KB
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="{{ $backup['download_url'] }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection
