@extends('layouts.customer')

@section('title', 'My Wallet')

@push('styles')
<style>
    /* ── Customer wallet — stat tiles (mobile table via shared .table-stack) ── */
    .cw-stat { border: 1px solid var(--bs-border-color); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .cw-stat:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.3); box-shadow: 0 14px 28px -22px rgba(0,0,0,.5); }
    .cw-stat-ico { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.25rem; }
    .cw-stat-label { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .cw-stat-value { font-size: 1.25rem; font-weight: 800; line-height: 1; margin: .25rem 0 0; }
</style>
@endpush

@section('content')

{{-- ── Balance hero card ───────────────────────────────────── --}}
<div class="card mb-4 overflow-hidden">
    <div class="card-body p-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small opacity-75 mb-1">Available Balance</div>
                <div class="display-5 fw-bold mb-0">₱{{ number_format($user->wallet_balance ?? 0, 2) }}</div>
                @if($stats['last_activity'])
                    <div class="small opacity-75 mt-2">
                        <i class="bi bi-clock-history me-1"></i>
                        Last activity {{ \Carbon\Carbon::parse($stats['last_activity'])->diffForHumans() }}
                    </div>
                @endif
            </div>
            <div>
                <span class="ms-2"><i class="bi bi-wallet2 fs-1 opacity-50"></i></span>
            </div>
        </div>
    </div>
</div>

{{-- ── Top-up: online if venue has a gateway, else staff-managed ─── --}}
@if(!empty($availableGateways))
    <div class="card mb-4" x-data="{ open: false }">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h6 class="mb-1 fw-semibold"><i class="bi bi-plus-circle me-2 text-success"></i>Add Money to Your Wallet</h6>
                    <div class="small text-muted">Pay online once, then use your balance for any booking or purchase.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" @click="open = !open">
                    <span x-show="!open"><i class="bi bi-wallet2 me-1"></i>Top up</span>
                    <span x-show="open" x-cloak>Cancel</span>
                </button>
            </div>

            <div x-show="open" x-cloak x-transition class="mt-3 pt-3 border-top">
                <form method="POST" action="{{ route('customer.wallet.topup') }}">
                    @csrf
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-5">
                            <label class="form-label small">Amount (₱)</label>
                            <input type="number" name="amount" min="50" max="50000" step="1"
                                   value="500" required class="form-control">
                            <div class="d-flex gap-1 mt-2 flex-wrap">
                                @foreach([200, 500, 1000, 2000] as $preset)
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="this.closest('form').amount.value={{ $preset }}">₱{{ $preset }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12 col-md-5" x-data="topupChoice()">
                            <label class="form-label small">Payment method</label>
                            <select required class="form-select"
                                    x-model="choice" @change="sync()">
                                @if(in_array('paymongo', $availableGateways))
                                    @php
                                        $pmLabels = [
                                            'gcash'   => 'GCash',
                                            'paymaya' => 'Maya / PayMaya',
                                            'card'    => 'Credit / Debit Card',
                                            'qrph'    => 'QR Ph',
                                        ];
                                    @endphp
                                    @foreach($paymongoMethods as $m)
                                        <option value="paymongo:{{ $m }}">{{ $pmLabels[$m] ?? $m }}</option>
                                    @endforeach
                                @endif
                                @if(in_array('stripe', $availableGateways))
                                    <option value="stripe:">International Card (Stripe)</option>
                                @endif
                            </select>
                            {{-- Real values submitted to the controller. --}}
                            <input type="hidden" name="gateway" x-model="gateway">
                            <input type="hidden" name="method"  x-model="method">
                        </div>
                        <div class="col-12 col-md-2 d-grid">
                            <label class="form-label small invisible">.</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-right-circle me-1"></i>Continue
                            </button>
                        </div>
                    </div>
                    @error('amount')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('gateway')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info d-flex align-items-start mb-4">
        <i class="bi bi-info-circle-fill me-3 fs-4 flex-shrink-0"></i>
        <div>
            <strong>Wallet top-up is handled by venue staff.</strong>
            <div class="small mt-1">This venue accepts cash payments only — please contact staff or owner to add balance.</div>
        </div>
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- ── Stats row ───────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card cw-stat h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="cw-stat-ico bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-down-circle"></i></div>
                <div class="min-w-0">
                    <p class="cw-stat-label">Total Added</p>
                    <p class="cw-stat-value text-success">+ ₱{{ number_format($stats['total_credited'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card cw-stat h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="cw-stat-ico bg-danger bg-opacity-10 text-danger"><i class="bi bi-arrow-up-circle"></i></div>
                <div class="min-w-0">
                    <p class="cw-stat-label">Total Spent</p>
                    <p class="cw-stat-value text-danger">– ₱{{ number_format($stats['total_debited'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card cw-stat h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="cw-stat-ico bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
                <div class="min-w-0">
                    <p class="cw-stat-label">Top-ups Received</p>
                    <p class="cw-stat-value">{{ $stats['top_ups_count'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Filters ─────────────────────────────────────────────── --}}
<div class="card mb-3">
    <form method="GET" class="card-body py-3 d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label small mb-1">Type</label>
            <select name="type" class="form-select form-select-sm" style="width:auto">
                <option value="">All</option>
                @foreach(['credit' => 'Credits', 'debit' => 'Debits', 'refund' => 'Refunds', 'reward' => 'Rewards'] as $val => $label)
                    <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:auto">
        </div>
        <div>
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:auto">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
        @if(request()->hasAny(['type','from','to']))
            <a href="{{ route('customer.wallet.index') }}" class="btn btn-link btn-sm">Reset</a>
        @endif
    </form>
</div>

{{-- ── Transactions ────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-muted"></i>Transactions</h6>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Processed by</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance after</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    @php
                        $isCredit = in_array($t->type, ['credit', 'refund', 'reward']);
                        $badgeClass = ['credit'=>'success','refund'=>'info','reward'=>'primary','debit'=>'danger'][$t->type] ?? 'secondary';
                    @endphp
                    <tr>
                        <td data-label="Date" class="text-muted small" style="white-space:nowrap">{{ $t->created_at->format('M d, Y · g:i A') }}</td>
                        <td data-label="Description">
                            {{ $t->description ?: '—' }}
                            @if($t->note)
                                <div class="small text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ $t->note }}</div>
                            @endif
                        </td>
                        <td data-label="Reference"><code class="small text-muted">{{ $t->reference }}</code></td>
                        <td data-label="Processed by" class="small text-muted">{{ $t->processedBy?->name ?? '—' }}</td>
                        <td data-label="Type">
                            <span class="badge rounded-pill bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} text-capitalize">{{ $t->type }}</span>
                        </td>
                        <td data-label="Amount" class="text-end fw-semibold {{ $isCredit ? 'text-success' : 'text-danger' }}">
                            {{ $isCredit ? '+' : '–' }} ₱{{ number_format($t->amount, 2) }}
                        </td>
                        <td data-label="Balance after" class="text-end">₱{{ number_format($t->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="cell-plain text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                <p class="mb-0">No transactions yet.</p>
                                <p class="small mb-0">Ask venue staff to top up your wallet.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    function topupChoice() {
        // Initial choice = first available option (PayMongo method or Stripe).
        @php
            $initial = '';
            if (in_array('paymongo', $availableGateways) && !empty($paymongoMethods)) {
                $initial = 'paymongo:' . $paymongoMethods[0];
            } elseif (in_array('stripe', $availableGateways)) {
                $initial = 'stripe:';
            }
        @endphp
        return {
            choice: @json($initial),
            get gateway() { return (this.choice.split(':')[0]) || ''; },
            get method()  { return (this.choice.split(':')[1]) || ''; },
            sync() { /* Alpine getters update the hidden inputs reactively */ },
        };
    }
</script>
@endpush

@endsection
