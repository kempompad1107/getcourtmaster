@props([
    'name',
    'title'    => null,
    'subtitle' => null,
    'size'     => 'md',
    'centered' => false,
])

@php
$sizes = [
    'sm'   => 'modal-sm',
    'md'   => '',
    'lg'   => 'modal-lg',
    'xl'   => 'modal-xl',
    '2xl'  => 'modal-xl',
    '3xl'  => 'modal-xl',
    'full' => 'modal-fullscreen',
];
$sizeClass = $sizes[$size] ?? '';
@endphp

@push('modals')
<div class="modal fade" id="{{ $name }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog {{ $sizeClass }} {{ $centered ? 'modal-dialog-centered' : '' }} modal-dialog-scrollable">
        <div class="modal-content">

            @if($title)
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $title }}</h5>
                    @if($subtitle)
                    <p class="text-muted small mb-0">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            @else
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3 z-1"
                    data-bs-dismiss="modal"></button>
            @endif

            <div class="modal-body">
                {{ $slot }}
            </div>

            @isset($footer)
            <div class="modal-footer">
                {{ $footer }}
            </div>
            @endisset

        </div>
    </div>
</div>
@endpush
