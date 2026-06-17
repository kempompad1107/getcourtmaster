@props([
    'lines'  => 1,
    'height' => null,
    'circle' => false,
    'width'  => null,
])

@if($circle)
<div {{ $attributes->merge(['class' => 'skeleton rounded-circle']) }}
     style="{{ $height ? 'height:' . $height . ';width:' . ($width ?? $height) : 'height:2.5rem;width:2.5rem' }}"></div>
@elseif($lines > 1)
<div {{ $attributes->merge(['class' => 'd-flex flex-column gap-2']) }}>
    @for($i = 0; $i < $lines; $i++)
    <div class="skeleton rounded" style="height:1rem;width:{{ $i === $lines - 1 ? '75%' : '100%' }}"></div>
    @endfor
</div>
@else
<div {{ $attributes->merge(['class' => 'skeleton rounded']) }}
     style="{{ $height ? 'height:' . $height . ';' : 'height:1rem;' }}{{ $width ? 'width:' . $width : 'width:100%' }}"></div>
@endif
