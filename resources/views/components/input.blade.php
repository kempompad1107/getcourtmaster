@props([
    'label'    => null,
    'error'    => null,
    'hint'     => null,
    'prefix'   => null,
    'suffix'   => null,
    'required' => false,
    'id'       => null,
    'type'     => 'text',
    'size'     => 'md',
])

@php
$inputId   = $id ?? 'input-' . md5($label . rand());
$sizes     = ['sm' => 'form-control-sm', 'md' => '', 'lg' => 'form-control-lg'];
$sizeClass = $sizes[$size] ?? '';
@endphp

<div>
    @if($label)
    <label for="{{ $inputId }}" class="form-label fw-medium">
        {{ $label }}
        @if($required) <span class="text-danger">*</span> @endif
    </label>
    @endif

    <div class="{{ ($prefix || $suffix) ? 'input-group' : '' }}">
        @if($prefix)
        <span class="input-group-text">{{ $prefix }}</span>
        @endif

        <input
            id="{{ $inputId }}"
            type="{{ $type }}"
            {{ $attributes->class([
                'form-control',
                $sizeClass,
                'is-invalid' => $error,
            ])->except(['class']) }}
        >

        @if($suffix)
        <span class="input-group-text">{{ $suffix }}</span>
        @endif
    </div>

    @if($error)
    <div class="invalid-feedback d-block">
        <i class="bi bi-exclamation-circle me-1"></i>{{ $error }}
    </div>
    @elseif($hint)
    <div class="form-text">{{ $hint }}</div>
    @endif
</div>
