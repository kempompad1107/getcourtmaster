@extends('layouts.app')
@section('title', $product->name . ' — Movements')

@section('content')

<x-page-header :title="$product->name" subtitle="Inventory Movements" :back="route('admin.products.edit', $product)"/>

<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h6 class="fw-semibold mb-0">{{ $product->name }}</h6>
            <small class="text-muted">{{ $product->category->name }} &bull; {{ $product->sku ?? 'No SKU' }}</small>
        </div>
        <span class="badge {{ $product->isLowStock() ? 'text-bg-warning text-dark' : 'text-bg-success' }} fs-6 px-3 py-2">
            Stock: {{ $product->stock_quantity }}
        </span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Note</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $mv)
                @php
                $typeBadge = match(true) {
                    in_array($mv->type, ['restock','return']) => 'text-bg-success',
                    $mv->type === 'sale'                      => 'text-bg-primary',
                    $mv->type === 'damage'                    => 'text-bg-danger',
                    default                                   => 'text-bg-secondary',
                };
                @endphp
                <tr>
                    <td class="small">{{ $mv->created_at->format('M j, Y H:i') }}</td>
                    <td><span class="badge {{ $typeBadge }}">{{ ucfirst($mv->type) }}</span></td>
                    <td>
                        <span class="small fw-semibold {{ $mv->quantity_change > 0 ? 'text-success' : 'text-danger' }}">
                            {{ $mv->quantity_change > 0 ? '+' : '' }}{{ $mv->quantity_change }}
                        </span>
                    </td>
                    <td class="small">{{ $mv->quantity_before }}</td>
                    <td class="small">{{ $mv->quantity_after }}</td>
                    <td class="small text-muted">{{ $mv->notes ?? '—' }}</td>
                    <td class="small text-muted">{{ $mv->createdBy->name ?? 'System' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="No movements recorded" icon="bi-arrow-left-right"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $movements->links() }}
    </div>
</div>

@endsection
