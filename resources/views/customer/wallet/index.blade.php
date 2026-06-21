@extends('layouts.customer')

@section('title', 'My Wallet')

@push('styles')
<style>
    .cw-stat { border: 1px solid var(--bs-border-color); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .cw-stat:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.3); box-shadow: 0 14px 28px -22px rgba(0,0,0,.5); }
    .cw-stat-ico { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.25rem; }
    .cw-stat-label { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .cw-stat-value { font-size: 1.25rem; font-weight: 800; line-height: 1; margin: .25rem 0 0; }

    .cw-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff; border-radius: var(--bs-card-border-radius);
    }
    .cw-hero .cw-balance { font-size: 2.25rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
    .cw-hero .cw-label { font-size: .75rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; opacity: .75; }

    .cw-tx-ico {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; font-size: .95rem;
    }
    .cw-tx-ico.credit  { background: rgba(16,185,129,.12); color: #10b981; }
    .cw-tx-ico.debit   { background: rgba(239,68,68,.12); color: #ef4444; }
    .cw-tx-ico.refund  { background: rgba(59,130,246,.12); color: #3b82f6; }
    .cw-tx-ico.reward  { background: rgba(168,85,247,.12); color: #a855f7; }
</style>
@endpush

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        {{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        {{ session('info') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Page header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">My Wallet</h4>
        <p class="text-muted small mb-0">Manage your balance and view transaction history.</p>
    </div>
</div>

{{-- Balance hero + Top-up --}}
@if(!empty($availableGateways))
<div class="card mb-4 overflow-hidden" x-data="{ open: false }">
    <div class="cw-hero p-4">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <div class="cw-label mb-2">Available Balance</div>
                <div class="cw-balance">₱{{ number_format($user->wallet_balance ?? 0, 2) }}</div>
                @if($stats['last_activity'])
                    <div class="small mt-2" style="opacity:.7">
                        <i class="bi bi-clock-history me-1"></i>
                        Last activity {{ \Carbon\Carbon::parse($stats['last_activity'])->diffForHumans() }}
                    </div>
                @endif
            </div>
            <button type="button" class="btn btn-light fw-semibold flex-shrink-0" @click="open = !open">
                <span x-show="!open"><i class="bi bi-plus-lg me-1"></i>Top Up</span>
                <span x-show="open" x-cloak>Cancel</span>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition class="card-body border-top">
        <form method="POST" action="{{ route('customer.wallet.topup') }}">
            @csrf
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold">Amount (₱)</label>
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
                    <label class="form-label small fw-semibold">Payment Method</label>
                    <select required class="form-select" x-model="choice" @change="sync()">
                        @if(in_array('paymongo', $availableGateways))
                            @php $pmLabels = ['gcash'=>'GCash','paymaya'=>'Maya / PayMaya','card'=>'Credit / Debit Card','qrph'=>'QR Ph']; @endphp
                            @foreach($paymongoMethods as $m)
                                <option value="paymongo:{{ $m }}">{{ $pmLabels[$m] ?? $m }}</option>
                            @endforeach
                        @endif
                        @if(in_array('stripe', $availableGateways))
                            <option value="stripe:">International Card (Stripe)</option>
                        @endif
                    </select>
                    <input type="hidden" name="gateway" x-model="gateway">
                    <input type="hidden" name="method"  x-model="method">
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <label class="form-label small fw-semibold invisible">.</label>
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
@else
{{-- Balance hero (no top-up) --}}
<div class="card mb-4 overflow-hidden">
    <div class="cw-hero p-4">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <div class="cw-label mb-2">Available Balance</div>
                <div class="cw-balance">₱{{ number_format($user->wallet_balance ?? 0, 2) }}</div>
                @if($stats['last_activity'])
                    <div class="small mt-2" style="opacity:.7">
                        <i class="bi bi-clock-history me-1"></i>
                        Last activity {{ \Carbon\Carbon::parse($stats['last_activity'])->diffForHumans() }}
                    </div>
                @endif
            </div>
            <i class="bi bi-wallet2 fs-1" style="opacity:.25"></i>
        </div>
    </div>
    <div class="card-body py-3 d-flex align-items-center gap-2">
        <i class="bi bi-info-circle text-primary me-1"></i>
        <span class="small text-muted">Wallet top-up is handled by venue staff. Please contact us to add balance.</span>
    </div>
</div>
@endif

{{-- Stats row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card cw-stat h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="cw-stat-ico bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-down-circle"></i></div>
                <div class="min-w-0">
                    <p class="cw-stat-label">Total Added</p>
                    <p class="cw-stat-value text-success">₱{{ number_format($stats['total_credited'], 0) }}</p>
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
                    <p class="cw-stat-value text-danger">₱{{ number_format($stats['total_debited'], 0) }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card cw-stat h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="cw-stat-ico bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
                <div class="min-w-0">
                    <p class="cw-stat-label">Top-ups</p>
                    <p class="cw-stat-value">{{ $stats['top_ups_count'] }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Transactions card --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap py-3">
        <h6 class="mb-0 fw-semibold">Transactions</h6>
        {{-- Inline filter --}}
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <select name="type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach(['credit' => 'Credits', 'debit' => 'Debits', 'refund' => 'Refunds', 'reward' => 'Rewards'] as $val => $label)
                    <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:auto" placeholder="From">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:auto" placeholder="To">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            @if(request()->hasAny(['type','from','to']))
                <a href="{{ route('customer.wallet.index') }}" class="btn btn-link btn-sm p-0">Reset</a>
            @endif
        </form>
    </div>

    @if($transactions->isEmpty())
        <x-empty-state
            title="No transactions found"
            description="Your wallet history will appear here once you top up or make a purchase."
            icon="bi-clock-history"/>
    @else
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Transaction</th>
                    <th class="d-none d-md-table-cell">Date</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end d-none d-md-table-cell">Balance After</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $t)
                @php
                    $isCredit = in_array($t->type, ['credit', 'refund', 'reward']);
                    $badgeClass = ['credit'=>'success','refund'=>'info','reward'=>'primary','debit'=>'danger'][$t->type] ?? 'secondary';
                    $ico = match($t->type) { 'credit'=>'bi-arrow-down-circle', 'refund'=>'bi-arrow-counterclockwise', 'reward'=>'bi-gift', default=>'bi-arrow-up-circle' };
                @endphp
                <tr>
                    <td data-label="Transaction" class="cell-plain">
                        <div class="d-flex align-items-center gap-3">
                            <div class="cw-tx-ico {{ $t->type }}">
                                <i class="bi {{ $ico }}"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="small fw-semibold text-truncate">{{ $t->description ?: ucfirst($t->type) }}</div>
                                @if($t->note)
                                    <div class="small text-muted text-truncate">{{ $t->note }}</div>
                                @endif
                                <div class="small text-muted d-md-none">{{ $t->created_at->format('M d, Y · g:i A') }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Date" class="small text-muted text-nowrap d-none d-md-table-cell">
                        {{ $t->created_at->format('M d, Y') }}<br>
                        <span class="opacity-75">{{ $t->created_at->format('g:i A') }}</span>
                    </td>
                    <td data-label="Type">
                        <span class="badge rounded-pill bg-{{ $badgeClass }}-subtle text-{{ $badgeClass }} text-capitalize">{{ $t->type }}</span>
                    </td>
                    <td data-label="Amount" class="text-end fw-bold text-nowrap {{ $isCredit ? 'text-success' : 'text-danger' }}">
                        {{ $isCredit ? '+' : '–' }}₱{{ number_format($t->amount, 2) }}
                    </td>
                    <td data-label="Balance After" class="text-end small text-muted text-nowrap d-none d-md-table-cell">
                        ₱{{ number_format($t->balance_after, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($transactions->hasPages())
        <div class="px-4 py-3 border-top">{{ $transactions->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    function topupChoice() {
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
            sync() {},
        };
    }
</script>
@endpush

@endsection
