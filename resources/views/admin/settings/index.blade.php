@extends('layouts.app')
@section('title', 'Settings')

@section('content')

<x-page-header title="Club Settings"/>

<div class="row justify-content-center" x-data="{ tab: new URLSearchParams(location.search).get('tab') || 'general' }">
    <div class="col-12 col-lg-9">

        {{-- Tabs --}}
        <div class="settings-tabs mb-4" role="tablist">
            @foreach(['general' => 'General', 'booking' => 'Booking', 'gateways' => 'Payments', 'notifications' => 'Notifications', 'email' => 'Email', 'security' => 'Security'] as $key => $label)
            <button @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')"
                    :class="tab === '{{ $key }}' ? 'active' : ''"
                    class="settings-tab-btn" role="tab" type="button">{{ $label }}</button>
            @endforeach
        </div>

        {{-- General --}}
        <div x-show="tab === 'general'">

        @php
            $brandingSettings = $settings ?? [];
            $publicUrl = route('tenant.public', $tenant->slug);
            $logoUrl   = file_url($tenant->logo);
        @endphp

        {{-- Public profile highlight (the URL customers visit before signup) --}}
        <div class="set-public mb-4">
            <div class="set-public-text">
                <span class="set-public-eyebrow"><i class="bi bi-globe2"></i> Your public page</span>
                <p class="mb-0 small">The branded page customers land on before they sign up. Share this link or print its QR.</p>
            </div>
            <div class="gw-copy set-public-copy" x-data="{ copied:false }">
                <code x-ref="pubUrl">{{ $publicUrl }}</code>
                <a href="{{ $publicUrl }}" target="_blank" class="gw-copy-btn" style="--gw-accent:#10b981" title="Open page">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
                <button type="button" class="gw-copy-btn" style="--gw-accent:#10b981"
                        @click="navigator.clipboard.writeText($refs.pubUrl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)">
                    <span x-show="!copied"><i class="bi bi-clipboard"></i> Copy</span>
                    <span x-show="copied" x-cloak><i class="bi bi-check2"></i> Copied</span>
                </button>
            </div>
        </div>

        @php
            $heroImagePath = $brandingSettings['hero_image'] ?? null;
            $heroImageUrl  = file_url($heroImagePath);
        @endphp

        <form method="POST" action="{{ route('admin.settings.general') }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            {{-- Club information --}}
            <div class="card mb-4">
                <div class="card-header set-head">
                    <span class="set-head-icon" style="--sh:#3b82f6"><i class="bi bi-building"></i></span>
                    <div>
                        <h6 class="mb-0 fw-semibold">Club Information</h6>
                        <small class="text-muted">The basics used across receipts, bookings and your public page.</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Club name</label>
                            <input type="text" name="name" value="{{ $tenant->name }}" required class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Contact email <span class="badge bg-secondary fw-normal ms-1" style="font-size:.7em">Display only</span></label>
                            <input type="email" name="email" value="{{ $tenant->email }}" required class="form-control">
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>Shown on your public profile. To receive notification emails, set your address under the <a href="?tab=notifications">Notifications tab</a>.</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Phone number</label>
                            <input type="text" name="phone" value="{{ $tenant->phone }}" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" value="{{ $tenant->city }}" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" value="{{ $tenant->address }}" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                @foreach(['PHP' => 'PHP (₱)', 'USD' => 'USD ($)', 'SGD' => 'SGD ($)'] as $val => $label)
                                <option value="{{ $val }}" @selected($tenant->currency === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-select">
                                @foreach(timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" @selected($tenant->timezone === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Public page & branding --}}
            <div class="card mb-4">
                <div class="card-header set-head">
                    <span class="set-head-icon" style="--sh:#10b981"><i class="bi bi-palette"></i></span>
                    <div>
                        <h6 class="mb-0 fw-semibold">Public Page &amp; Branding</h6>
                        <small class="text-muted">How your venue looks to customers on the public page.</small>
                    </div>
                </div>
                <div class="card-body">

                    <div class="set-subhead">Media &amp; identity</div>
                    <div class="row g-3 mb-2">
                        <div class="col-12 col-sm-3">
                            <label class="form-label">Logo</label>
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

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Tagline <span class="text-muted small">(under the hero name)</span></label>
                            <input type="text" name="tagline" maxlength="200"
                                   value="{{ $brandingSettings['tagline'] ?? '' }}"
                                   placeholder="e.g. The friendliest pickleball club in town."
                                   class="form-control @error('tagline') is-invalid @enderror">
                            @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-sm-3">
                            <label class="form-label">Brand color</label>
                            <input type="color" name="brand_color"
                                   value="{{ $brandingSettings['brand_color'] ?? '#10b981' }}"
                                   class="form-control form-control-color w-100" style="height:38px">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Hero image <span class="text-muted small">(big photo below the hero)</span></label>
                            @if($heroImageUrl)
                                <div class="d-flex align-items-start gap-3 mb-2">
                                    <img src="{{ $heroImageUrl }}" alt="Hero"
                                         class="border rounded" style="max-width:280px;height:120px;object-fit:cover">
                                    <div class="form-check small mt-1">
                                        <input class="form-check-input" type="checkbox" name="remove_hero_image" value="1" id="remove_hero">
                                        <label class="form-check-label" for="remove_hero">Remove current</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="hero_image" accept="image/png,image/jpeg,image/webp"
                                   class="form-control form-control-sm @error('hero_image') is-invalid @enderror">
                            @error('hero_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Landscape orientation works best. PNG/JPG/WEBP, max 5MB.</div>
                        </div>
                    </div>

                    <div class="set-subhead">Page content</div>
                    <div class="row g-3 mb-2">
                        <div class="col-12">
                            <label class="form-label">About <span class="text-muted small">(rendered on the "About Us" section)</span></label>
                            <textarea name="about" rows="4" maxlength="2000"
                                      placeholder="Tell visitors who you are, what makes your venue special, and who it's for."
                                      class="form-control @error('about') is-invalid @enderror">{{ $brandingSettings['about'] ?? '' }}</textarea>
                            @error('about')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Rules / House Policies <span class="text-muted small">(rendered on the "Rules" section)</span></label>
                            <textarea name="rules" rows="5" maxlength="4000"
                                      placeholder="One rule per line works great. Example:&#10;&#10;Reserve your slot in advance&#10;Non-marking shoes required&#10;15-minute grace period"
                                      class="form-control @error('rules') is-invalid @enderror">{{ $brandingSettings['rules'] ?? '' }}</textarea>
                            @error('rules')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Plain text. Blank lines split paragraphs.</div>
                        </div>
                    </div>

                    <div class="set-subhead">Links</div>
                    <div class="row g-3">
                        <div class="col-12 col-sm-4">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" placeholder="https://..."
                                   value="{{ $brandingSettings['website'] ?? '' }}"
                                   class="form-control @error('website') is-invalid @enderror">
                            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label">Facebook</label>
                            <input type="url" name="facebook" placeholder="https://facebook.com/..."
                                   value="{{ $brandingSettings['facebook'] ?? '' }}"
                                   class="form-control @error('facebook') is-invalid @enderror">
                            @error('facebook')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label">Instagram</label>
                            <input type="url" name="instagram" placeholder="https://instagram.com/..."
                                   value="{{ $brandingSettings['instagram'] ?? '' }}"
                                   class="form-control @error('instagram') is-invalid @enderror">
                            @error('instagram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
        </div>{{-- /tab=general --}}

        {{-- Booking --}}
        <div x-show="tab === 'booking'" class="card" x-cloak>
            <div class="card-header set-head">
                <span class="set-head-icon" style="--sh:#8b5cf6"><i class="bi bi-calendar-check"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Booking Settings</h6>
                    <small class="text-muted">Tax, grace periods, pricing windows and confirmation rules.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.booking') }}">
                    @csrf @method('PUT')
                    @php $bs = $settings; @endphp
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Tax rate (%)</label>
                            <div class="input-group">
                                <input type="number" name="tax_rate" value="{{ $bs['tax_rate'] ?? 12 }}"
                                       min="0" max="100" step="0.5" class="form-control">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Grace period (minutes)</label>
                            <input type="number" name="grace_period_minutes" value="{{ $bs['grace_period_minutes'] ?? 5 }}"
                                   min="0" max="60" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Cancellation window (hours)</label>
                            <input type="number" name="cancellation_hours" value="{{ $bs['cancellation_hours'] ?? 24 }}"
                                   min="0" class="form-control">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Max advance booking (days)</label>
                            <input type="number" name="advance_booking_days" value="{{ $bs['advance_booking_days'] ?? 30 }}"
                                   min="1" max="365" class="form-control">
                        </div>

                        <div class="col-12">
                            <div class="set-subhead mt-2">Pricing hours</div>
                            <div class="form-text">Evening hours use the court's Evening rate; all other daytime hours use the Daylight rate. Saturday &amp; Sunday always use the Weekend rate.</div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label">Evening starts</label>
                            <input type="time" name="evening_start"
                                   value="{{ $bs['evening_start'] ?? '18:00' }}"
                                   class="form-control @error('evening_start') is-invalid @enderror">
                            @error('evening_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label">Evening ends</label>
                            <input type="time" name="evening_end"
                                   value="{{ $bs['evening_end'] ?? '22:00' }}"
                                   class="form-control @error('evening_end') is-invalid @enderror">
                            @error('evening_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check mb-2">
                                <input type="hidden" name="require_payment" value="0">
                                <input type="checkbox" name="require_payment" value="1" id="require_payment"
                                       class="form-check-input" @checked($bs['require_payment'] ?? false)>
                                <label class="form-check-label" for="require_payment">Require payment to confirm booking</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="hidden" name="auto_confirm" value="0">
                                <input type="checkbox" name="auto_confirm" value="1" id="auto_confirm"
                                       class="form-check-input" @checked($bs['auto_confirm'] ?? false)>
                                <label class="form-check-label" for="auto_confirm">Auto-confirm bookings</label>
                            </div>
                            <div class="form-check">
                                <input type="hidden" name="auto_stop_after_grace" value="0">
                                <input type="checkbox" name="auto_stop_after_grace" value="1" id="auto_stop_after_grace"
                                       class="form-check-input" @checked($bs['auto_stop_after_grace'] ?? false)>
                                <label class="form-check-label" for="auto_stop_after_grace">
                                    Auto-stop session when grace period expires
                                </label>
                                <div class="form-text">
                                    When <strong>ON</strong>: the booking ends automatically the moment the grace period runs out — no overtime is ever charged.
                                    When <strong>OFF</strong> (default): the timer keeps running past grace and accumulates overtime at the court's live rate until staff clicks Stop.
                                </div>
                            </div>
                            <div class="form-check mt-2">
                                <input type="hidden" name="shift_auto_clockout" value="0">
                                <input type="checkbox" name="shift_auto_clockout" value="1" id="shift_auto_clockout"
                                       class="form-check-input" @checked($bs['shift_auto_clockout'] ?? true)>
                                <label class="form-check-label" for="shift_auto_clockout">
                                    Auto clock-out staff at end of shift
                                </label>
                                <div class="form-text">
                                    When <strong>ON</strong> (default): staff still clocked in are automatically clocked out at their shift's scheduled end time. Schedule a longer shift to extend it past the usual 8 hours.
                                    When <strong>OFF</strong>: staff stay on the clock until they (or an owner) clock out manually.
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Payment --}}
        {{-- Gateways tab is now the single source of truth for payment methods.
             The old "Payment Settings" tab (accept_cash / accept_card / etc.) was
             write-only — nothing in the codebase read those flags — so it was
             removed in favor of the gateway method-pickers below. --}}
        <div x-show="tab === 'gateways'" x-cloak>
            @php
                $creds = $tenant->payment_credentials ?? [];
                $pm    = $creds['paymongo'] ?? [];
                $sp    = $creds['stripe']   ?? [];
                $mask  = fn ($v) => $v ? str_repeat('•', 6) . substr($v, -4) : '';
                $webhookToken = $tenant->webhook_token;
                $pmMethods = $pm['methods'] ?? [];
            @endphp

            {{-- Intro banner --}}
            <div class="gw-intro mb-4">
                <div class="gw-intro-icon"><i class="bi bi-bank2"></i></div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-semibold">Online Payments</h6>
                    <p class="mb-0 text-muted small">
                        Connect your own PayMongo or Stripe account. When customers pay online the money
                        lands <strong>directly in your bank</strong> — we never hold or touch it.
                    </p>
                </div>
                <a href="{{ route('admin.settings.gateways.guide') }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                    <i class="bi bi-question-circle me-1"></i>Setup Guide
                </a>
            </div>

            <form method="POST" action="{{ route('admin.settings.gateways') }}">
                @csrf @method('PUT')

                {{-- ── PayMongo ─────────────────────────────────────────────── --}}
                <div class="gw-card mb-4" style="--gw-accent:#15c5a8"
                     x-data="{ on: {{ ($pm['enabled'] ?? false) ? 'true' : 'false' }} }"
                     :class="on ? 'is-on' : ''">
                    <div class="gw-card-head">
                        <div class="gw-logo">P</div>
                        <div class="gw-meta">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="gw-name">PayMongo</span>
                                <span class="gw-status" x-show="on" x-cloak><span class="gw-dot"></span>Connected</span>
                            </div>
                            <div class="gw-sub">GCash · Maya · QR Ph · Cards — for Philippine customers</div>
                        </div>
                        <label class="gw-switch" title="Enable PayMongo">
                            <input type="hidden" name="paymongo_enabled" value="0">
                            <input type="checkbox" name="paymongo_enabled" value="1"
                                   x-model="on" @checked($pm['enabled'] ?? false)>
                            <span class="gw-switch-track"></span>
                        </label>
                    </div>

                    <div class="gw-card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="gw-label">Secret key <span class="gw-hint-inline">sk_live_…</span></label>
                                <input type="password" name="paymongo_secret_key" autocomplete="off"
                                       placeholder="{{ $mask($pm['secret_key'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <div class="gw-help">Dashboard → Developers → API Keys. Leave blank to keep the saved key.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="gw-label">Webhook secret <span class="gw-hint-inline">whsk_…</span></label>
                                <input type="password" name="paymongo_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($pm['webhook_secret'] ?? null) ?: 'whsk_…' }}"
                                       class="form-control font-monospace">
                                <div class="gw-help">Shown once when you create the webhook below.</div>
                            </div>

                            <div class="col-12">
                                <label class="gw-label">What customers can pay with</label>
                                <div class="gw-help mb-2">Tick only the methods you've enabled in your PayMongo dashboard.</div>
                                <div class="gw-chips">
                                    @foreach(['gcash' => ['GCash','bi-wallet2'], 'paymaya' => ['Maya','bi-phone'], 'card' => ['Card','bi-credit-card'], 'qrph' => ['QR Ph','bi-qr-code']] as $val => $meta)
                                        <label class="gw-chip">
                                            <input type="checkbox" name="paymongo_methods[]" value="{{ $val }}"
                                                   @checked(in_array($val, $pmMethods))>
                                            <span><i class="bi {{ $meta[1] }}"></i>{{ $meta[0] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            @if($webhookToken)
                            <div class="col-12">
                                <label class="gw-label">Webhook URL <span class="gw-hint-inline">paste into PayMongo</span></label>
                                <div class="gw-copy" x-data="{ copied:false }">
                                    <code x-ref="pmUrl">{{ url('/api/v1/webhooks/paymongo/' . $webhookToken) }}</code>
                                    <button type="button" class="gw-copy-btn"
                                            @click="navigator.clipboard.writeText($refs.pmUrl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)">
                                        <span x-show="!copied"><i class="bi bi-clipboard"></i> Copy</span>
                                        <span x-show="copied" x-cloak><i class="bi bi-check2"></i> Copied</span>
                                    </button>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Stripe ───────────────────────────────────────────────── --}}
                <div class="gw-card mb-4" style="--gw-accent:#635bff"
                     x-data="{ on: {{ ($sp['enabled'] ?? false) ? 'true' : 'false' }} }"
                     :class="on ? 'is-on' : ''">
                    <div class="gw-card-head">
                        <div class="gw-logo">S</div>
                        <div class="gw-meta">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="gw-name">Stripe</span>
                                <span class="gw-status" x-show="on" x-cloak><span class="gw-dot"></span>Connected</span>
                            </div>
                            <div class="gw-sub">International credit &amp; debit cards</div>
                        </div>
                        <label class="gw-switch" title="Enable Stripe">
                            <input type="hidden" name="stripe_enabled" value="0">
                            <input type="checkbox" name="stripe_enabled" value="1"
                                   x-model="on" @checked($sp['enabled'] ?? false)>
                            <span class="gw-switch-track"></span>
                        </label>
                    </div>

                    <div class="gw-card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="gw-label">Secret key <span class="gw-hint-inline">sk_live_…</span></label>
                                <input type="password" name="stripe_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['secret'] ?? null) ?: 'sk_live_…' }}"
                                       class="form-control font-monospace">
                                <div class="gw-help">Dashboard → Developers → API Keys. Leave blank to keep the saved key.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="gw-label">Webhook signing secret <span class="gw-hint-inline">whsec_…</span></label>
                                <input type="password" name="stripe_webhook_secret" autocomplete="off"
                                       placeholder="{{ $mask($sp['webhook_secret'] ?? null) ?: 'whsec_…' }}"
                                       class="form-control font-monospace">
                                <div class="gw-help">Shown once when you create the webhook below.</div>
                            </div>

                            @if($webhookToken)
                            <div class="col-12">
                                <label class="gw-label">Webhook URL <span class="gw-hint-inline">paste into Stripe</span></label>
                                <div class="gw-copy" x-data="{ copied:false }">
                                    <code x-ref="spUrl">{{ url('/api/v1/webhooks/stripe/' . $webhookToken) }}</code>
                                    <button type="button" class="gw-copy-btn"
                                            @click="navigator.clipboard.writeText($refs.spUrl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)">
                                        <span x-show="!copied"><i class="bi bi-clipboard"></i> Copy</span>
                                        <span x-show="copied" x-cloak><i class="bi bi-check2"></i> Copied</span>
                                    </button>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="gw-secure-note">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Keys are encrypted at rest and never shown back to you. Lost a key? Create a new one in your provider's dashboard and paste it here again.</span>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Payment Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- Notifications --}}
        <div x-show="tab === 'notifications'" class="card" x-cloak>
            <div class="card-header set-head">
                <span class="set-head-icon" style="--sh:#f59e0b"><i class="bi bi-bell"></i></span>
                <div>
                    <h6 class="mb-0 fw-semibold">Notification Settings</h6>
                    <small class="text-muted">Where alerts go and which events trigger them.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.notifications') }}">
                    @csrf @method('PUT')
                    @php $ns = $settings['notifications'] ?? []; @endphp
                    <div class="mb-4">
                        <label class="form-label">Notification email</label>
                        <input type="email" name="notification_email"
                               value="{{ $ns['notification_email'] ?? auth()->user()->email }}"
                               class="form-control">
                        <div class="form-text">Alerts will be sent to this address.</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="email_enabled" value="0">
                        <input type="checkbox" name="email_enabled" value="1" id="email_enabled"
                               class="form-check-input" @checked($ns['email_enabled'] ?? true)>
                        <label class="form-check-label" for="email_enabled">
                            Send email notifications
                        </label>
                    </div>
                    <div class="set-subhead">Notify me when</div>
                    <div class="d-flex flex-column gap-2 mb-4">
                        @foreach(['notify_new_booking' => 'New booking created', 'notify_cancellation' => 'Booking cancelled', 'notify_low_stock' => 'Low stock alert', 'notify_membership_expiry' => 'Membership expiring'] as $key => $label)
                        <div class="form-check">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input type="checkbox" name="{{ $key }}" value="1"
                                   id="{{ $key }}" class="form-check-input" @checked($ns[$key] ?? true)>
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Email / SMTP --}}
        <div x-show="tab === 'email'" x-cloak>
            @php
                $mailCfg     = $tenant->mail_credentials ?? [];
                $mailFlags   = $settings['mail'] ?? [];
                $isOwner     = auth()->user()->isBusinessOwner() || auth()->user()->isSuperAdmin();
                $hasOwnSmtp  = !empty($mailCfg['host']);
                $requireSmtp = (bool) ($mailFlags['require_smtp'] ?? false);
            @endphp

            @if($requireSmtp && !$hasOwnSmtp)
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle"></i>
                <div>You asked to require your own SMTP, but none is configured — email is
                using the platform mailer (or not sending). Add your SMTP details below.</div>
            </div>
            @endif

            {{-- Beginner-friendly setup guide (collapsible) --}}
            <div class="card mb-4 smtp-guide" x-data="{ help: false }">
                <button type="button" class="smtp-guide-toggle" @click="help = !help" :aria-expanded="help">
                    <span class="set-head-icon" style="--sh:#0ea5e9"><i class="bi bi-info-circle"></i></span>
                    <div class="flex-grow-1 text-start">
                        <h6 class="mb-0 fw-semibold">New to this? How to set up email (SMTP)</h6>
                        <small class="text-muted">A plain-English, step-by-step guide — no jargon.</small>
                    </div>
                    <i class="bi" :class="help ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>

                <div class="card-body border-top" x-show="help" x-cloak>
                    <p class="text-muted">
                        Using <strong>Gmail</strong>? Just follow these 3 steps. Google no longer allows
                        your normal password for apps — you'll create a special <strong>App Password</strong> instead.
                    </p>

                    <div class="smtp-steps">
                        <div class="smtp-step">
                            <span class="smtp-step-num">1</span>
                            <div>
                                <div class="fw-semibold">Turn on 2-Step Verification</div>
                                <small class="text-muted">
                                    Open <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">Google Account → Security</a>
                                    and switch on <strong>2-Step Verification</strong>. (Required before step 2.)
                                </small>
                            </div>
                        </div>
                        <div class="smtp-step">
                            <span class="smtp-step-num">2</span>
                            <div>
                                <div class="fw-semibold">Generate an App Password</div>
                                <small class="text-muted">
                                    Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App passwords</a>,
                                    name it (e.g. “CourtMaster”), click <strong>Generate</strong>, and copy the
                                    16-character password. Use this — not your Gmail password.
                                </small>
                            </div>
                        </div>
                        <div class="smtp-step">
                            <span class="smtp-step-num">3</span>
                            <div>
                                <div class="fw-semibold">Fill in the form below &amp; save</div>
                                <small class="text-muted">Use the settings in the table, paste the App Password into the Password field, then click <strong>Save Email Settings</strong> and <strong>Send test</strong>.</small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle smtp-provider-table mb-2">
                            <tbody>
                                <tr><th class="fw-semibold" style="width:9rem">Host</th><td><code>smtp.gmail.com</code></td></tr>
                                <tr><th class="fw-semibold">Port</th><td><code>587</code> (TLS) <span class="text-muted">— or <code>465</code> (SSL)</span></td></tr>
                                <tr><th class="fw-semibold">Encryption</th><td>TLS</td></tr>
                                <tr><th class="fw-semibold">Username</th><td>your full Gmail address</td></tr>
                                <tr><th class="fw-semibold">Password</th><td>the 16-character App Password from step 2</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <details class="smtp-other text-muted small">
                        <summary class="fw-semibold">Not using Gmail?</summary>
                        <table class="table table-sm align-middle smtp-provider-table mt-2 mb-0">
                            <thead><tr><th>Provider</th><th>Host</th><th>Port</th><th>Encryption</th></tr></thead>
                            <tbody>
                                <tr><td class="fw-semibold">Outlook / Microsoft 365</td><td><code>smtp.office365.com</code></td><td>587</td><td>TLS</td></tr>
                                <tr><td class="fw-semibold">Yahoo Mail</td><td><code>smtp.mail.yahoo.com</code></td><td>465</td><td>SSL</td></tr>
                                <tr><td class="fw-semibold">Zoho Mail</td><td><code>smtp.zoho.com</code></td><td>587</td><td>TLS</td></tr>
                                <tr><td class="fw-semibold">Other / web host</td><td colspan="3" class="text-muted">Ask your provider for “SMTP host, port, and encryption”.</td></tr>
                            </tbody>
                        </table>
                    </details>

                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-shield-lock me-1"></i>
                        Your password is encrypted and never shown again — leave the Password field blank
                        when saving later to keep the one you already entered.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header set-head">
                    <span class="set-head-icon" style="--sh:#6366f1"><i class="bi bi-envelope-at"></i></span>
                    <div>
                        <h6 class="mb-0 fw-semibold">Email Delivery (SMTP)</h6>
                        <small class="text-muted">Send notifications through your own mail server.</small>
                    </div>
                </div>
                <div class="card-body">
                    @unless($isOwner)
                    <div class="alert alert-secondary mb-0">
                        Only the business owner can change SMTP credentials.
                    </div>
                    @else
                    <form method="POST" action="{{ route('admin.settings.email') }}">
                        @csrf @method('PUT')

                        <div class="form-check form-switch mb-4">
                            <input type="hidden" name="require_smtp" value="0">
                            <input type="checkbox" name="require_smtp" value="1" id="require_smtp"
                                   class="form-check-input" @checked($requireSmtp)>
                            <label class="form-check-label" for="require_smtp">
                                Require my own SMTP (warn me if mail uses the platform mailer)
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP host</label>
                                <input type="text" name="smtp_host" class="form-control"
                                       value="{{ $mailCfg['host'] ?? '' }}" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       value="{{ $mailCfg['port'] ?? '' }}" placeholder="587">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Encryption</label>
                                <select name="smtp_encryption" class="form-select">
                                    <option value="">None</option>
                                    <option value="tls" @selected(($mailCfg['encryption'] ?? '') === 'tls')>TLS</option>
                                    <option value="ssl" @selected(($mailCfg['encryption'] ?? '') === 'ssl')>SSL</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Username</label>
                                <input type="text" name="smtp_username" autocomplete="off" class="form-control"
                                       value="{{ $mailCfg['username'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="smtp_password" autocomplete="new-password"
                                       class="form-control" placeholder="{{ !empty($mailCfg['password']) ? '•••••••• (unchanged)' : '' }}">
                                <div class="form-text">Leave blank to keep the current password.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From name</label>
                                <input type="text" name="smtp_from_name" class="form-control"
                                       value="{{ $mailCfg['from_name'] ?? $tenant->name }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">From address</label>
                                <input type="email" name="smtp_from_address" class="form-control"
                                       value="{{ $mailCfg['from_address'] ?? '' }}" placeholder="no-reply@yourclub.com">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <small class="text-muted">Leave host blank to use the platform mailer.</small>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Email Settings
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <form method="POST" action="{{ route('admin.settings.email.test') }}"
                          class="d-flex align-items-center justify-content-between gap-3">
                        @csrf
                        <div>
                            <div class="fw-semibold">Send a test email</div>
                            <small class="text-muted">Sends to {{ auth()->user()->email }} using the settings above.</small>
                        </div>
                        <button type="submit" class="btn btn-outline-primary flex-shrink-0">
                            <i class="bi bi-send me-1"></i>Send test
                        </button>
                    </form>
                    @endunless
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div x-show="tab === 'security'" x-cloak>
            @include('partials.security-card')
        </div>

    </div>
</div>

@push('styles')
<style>
    /* ── SMTP beginner guide ───────────────────────────────────────────── */
    .smtp-guide-toggle {
        display: flex; align-items: center; gap: .75rem; width: 100%;
        padding: 1rem 1.25rem; background: transparent; border: 0;
        color: inherit; text-align: left; cursor: pointer;
    }
    .smtp-guide-toggle:hover { background: color-mix(in srgb, var(--bs-body-color, #1e293b) 4%, transparent); }
    .smtp-steps { display: flex; flex-direction: column; gap: .9rem; margin-top: .5rem; }
    .smtp-step { display: flex; align-items: flex-start; gap: .75rem; }
    .smtp-step-num {
        flex-shrink: 0; width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; font-size: .8rem; font-weight: 700;
        color: #0ea5e9; background: rgba(14,165,233,.12);
        box-shadow: inset 0 0 0 1px rgba(14,165,233,.25);
    }
    .smtp-provider-table code { font-size: .82rem; }
    .smtp-provider-table th {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--bs-secondary-color, #9aa3b2); font-weight: 700;
    }

    /* ── Shared settings section chrome ────────────────────────────────── */
    .set-head { display: flex; align-items: center; gap: .75rem; }
    .set-head-icon {
        width: 38px; height: 38px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 11px; font-size: 1.05rem;
        color: var(--sh, #10b981);
        background: color-mix(in srgb, var(--sh, #10b981) 12%, transparent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--sh, #10b981) 22%, transparent);
    }
    .set-subhead {
        font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--bs-secondary-color, #9aa3b2);
        padding-bottom: .5rem; margin-bottom: .85rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .set-subhead:not(:first-child) { margin-top: 1.5rem; }

    /* Public page highlight banner */
    .set-public {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
        padding: 1.1rem 1.25rem; border-radius: 1rem;
        background: linear-gradient(120deg, rgba(16,185,129,.10), rgba(59,130,246,.06) 70%);
        border: 1px solid var(--bs-border-color);
    }
    .set-public-text { flex: 1 1 240px; min-width: 0; }
    .set-public-eyebrow {
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
        color: #0f9d68; margin-bottom: .3rem;
    }
    .set-public-copy { flex: 1 1 340px; background: var(--bs-card-bg, #fff) !important; }
    .set-public-copy .gw-copy-btn { color: #10b981; }

    /* ── Payment gateways tab ──────────────────────────────────────────── */
    .gw-intro {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.25rem;
        background: linear-gradient(120deg, rgba(16,185,129,.08), rgba(16,185,129,0) 60%);
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
    }
    .gw-intro-icon {
        width: 44px; height: 44px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.25rem;
        color: #10b981; background: rgba(16,185,129,.12);
        box-shadow: inset 0 0 0 1px rgba(16,185,129,.18);
    }

    /* Gateway card */
    .gw-card {
        position: relative; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        border-radius: 1rem;
        background: var(--bs-card-bg, #fff);
        box-shadow: 0 1px 2px rgba(15,23,42,.04);
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .gw-card::before {
        content: ''; position: absolute; inset: 0 auto 0 0; width: 4px;
        background: var(--gw-accent, #10b981);
        opacity: .25; transition: opacity .2s ease;
    }
    .gw-card.is-on { box-shadow: 0 6px 20px -12px rgba(15,23,42,.25); }
    .gw-card.is-on::before { opacity: 1; }

    .gw-card-head {
        display: flex; align-items: center; gap: .875rem;
        padding: 1rem 1.25rem; border-bottom: 1px solid transparent;
    }
    .gw-card.is-on .gw-card-head { border-bottom-color: var(--bs-border-color); }

    .gw-logo {
        width: 42px; height: 42px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
        font-weight: 800; font-size: 1.15rem; color: #fff;
        background: var(--gw-accent, #10b981);
        box-shadow: 0 4px 12px -4px var(--gw-accent);
    }
    .gw-meta { flex-grow: 1; min-width: 0; }
    .gw-name { font-weight: 700; font-size: 1rem; color: var(--bs-heading-color, #111827); letter-spacing: -.01em; }
    .gw-sub  { font-size: .8125rem; color: var(--bs-secondary-color, #6b7280); margin-top: .1rem; }
    .gw-status {
        display: inline-flex; align-items: center; gap: .35rem;
        font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
        color: #0f9d68; background: rgba(16,185,129,.12);
        padding: .15rem .5rem; border-radius: 999px;
    }
    .gw-dot { width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.2); }

    /* Toggle switch */
    .gw-switch { position: relative; flex-shrink: 0; cursor: pointer; margin: 0; }
    .gw-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .gw-switch-track {
        display: block; width: 46px; height: 26px; border-radius: 999px;
        background: #cbd2dd; transition: background .2s ease;
    }
    .gw-switch-track::after {
        content: ''; position: absolute; top: 3px; left: 3px;
        width: 20px; height: 20px; border-radius: 50%;
        background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.25);
        transition: transform .2s ease;
    }
    .gw-switch input:checked + .gw-switch-track { background: var(--gw-accent, #10b981); }
    .gw-switch input:checked + .gw-switch-track::after { transform: translateX(20px); }
    .gw-switch input:focus-visible + .gw-switch-track { outline: 2px solid var(--gw-accent); outline-offset: 2px; }

    /* Collapsible body — dimmed & collapsed when off */
    .gw-card-body {
        max-height: 0; opacity: 0; overflow: hidden;
        padding: 0 1.25rem;
        transition: max-height .3s ease, opacity .25s ease, padding .3s ease;
    }
    .gw-card.is-on .gw-card-body { max-height: 1200px; opacity: 1; padding: 1.25rem; }

    .gw-label {
        display: block; font-size: .8125rem; font-weight: 600;
        color: var(--bs-heading-color, #1f2937); margin-bottom: .35rem;
    }
    .gw-hint-inline {
        font-weight: 500; font-size: .72rem; color: var(--bs-secondary-color, #9aa3b2);
        font-family: ui-monospace, monospace;
    }
    .gw-help { font-size: .75rem; color: var(--bs-secondary-color, #6b7280); margin-top: .35rem; line-height: 1.4; }

    /* Payment method chips */
    .gw-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .gw-chip { margin: 0; cursor: pointer; }
    .gw-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
    .gw-chip > span {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .45rem .85rem; border-radius: 999px;
        font-size: .8125rem; font-weight: 500;
        color: var(--bs-secondary-color, #6b7280);
        background: var(--bs-body-bg-alt, #f8fafc);
        border: 1px solid var(--bs-border-color);
        transition: all .15s ease;
    }
    .gw-chip > span i { font-size: .9rem; }
    .gw-chip:hover > span { border-color: var(--gw-accent); color: var(--bs-body-color); }
    .gw-chip input:checked + span {
        color: var(--gw-accent); font-weight: 600;
        background: color-mix(in srgb, var(--gw-accent) 12%, transparent);
        border-color: var(--gw-accent);
    }
    .gw-chip input:checked + span i { filter: none; }
    .gw-chip input:focus-visible + span { outline: 2px solid var(--gw-accent); outline-offset: 2px; }

    /* Webhook copy box */
    .gw-copy {
        display: flex; align-items: stretch; gap: 0;
        border: 1px solid var(--bs-border-color); border-radius: .6rem; overflow: hidden;
        background: var(--bs-body-bg-alt, #f8fafc);
    }
    .gw-copy code {
        flex-grow: 1; min-width: 0; padding: .55rem .75rem;
        font-size: .8rem; color: var(--bs-body-color);
        white-space: nowrap; overflow-x: auto; scrollbar-width: none;
        display: flex; align-items: center;
    }
    .gw-copy code::-webkit-scrollbar { display: none; }
    .gw-copy-btn {
        flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; gap: .35rem;
        border: none; border-left: 1px solid var(--bs-border-color);
        background: var(--bs-card-bg, #fff); color: var(--gw-accent);
        font-size: .8rem; font-weight: 600; padding: 0 1rem; cursor: pointer;
        text-decoration: none; line-height: 1; white-space: nowrap;
        transition: background .15s ease;
    }
    .gw-copy-btn > span { display: inline-flex; align-items: center; gap: .35rem; }
    .gw-copy-btn:hover { background: color-mix(in srgb, var(--gw-accent) 10%, transparent); }

    /* Secure note */
    .gw-secure-note {
        display: flex; align-items: flex-start; gap: .6rem;
        padding: .75rem 1rem; border-radius: .75rem;
        font-size: .8rem; line-height: 1.45;
        color: var(--bs-secondary-color, #6b7280);
        background: var(--bs-body-bg-alt, #f8fafc);
        border: 1px solid var(--bs-border-color);
    }
    .gw-secure-note i { color: #10b981; font-size: 1rem; margin-top: .05rem; flex-shrink: 0; }

    @media (max-width: 575.98px) {
        .gw-intro { flex-wrap: wrap; }
        .gw-card-head { flex-wrap: wrap; }
    }
</style>
@endpush

@endsection
