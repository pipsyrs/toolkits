@props(['template'])

{{-- Mini illustration of a QR template; decorative, the label names it. --}}
@php
    $c = $template->colors();
    // Fixed module positions in a 21x21 grid, outside the three finder patterns.
    $modules = [[8, 1], [10, 1], [9, 3], [11, 4], [8, 5], [12, 6], [1, 8], [3, 9], [5, 8], [6, 10], [2, 11], [8, 8], [10, 9], [9, 11], [12, 10], [11, 12], [14, 8], [16, 9], [18, 8], [15, 11], [19, 12], [9, 14], [11, 15], [8, 17], [10, 19], [12, 18], [14, 14], [16, 15], [18, 17], [15, 18], [17, 19], [19, 15]];
@endphp

<svg viewBox="0 0 21 21" aria-hidden="true" {{ $attributes->merge(['class' => 'rounded']) }}>
    <rect width="21" height="21" fill="{{ $c['bg'] }}"/>
    @foreach([[0, 0], [14, 0], [0, 14]] as [$x, $y])
        <rect x="{{ $x }}" y="{{ $y }}" width="7" height="7" fill="{{ $c['eye'] }}"/>
        <rect x="{{ $x + 1 }}" y="{{ $y + 1 }}" width="5" height="5" fill="{{ $c['bg'] }}"/>
        <rect x="{{ $x + 2 }}" y="{{ $y + 2 }}" width="3" height="3" fill="{{ $c['eye'] }}"/>
    @endforeach
    @foreach($modules as [$x, $y])
        @if($template->rounded())
            <circle cx="{{ $x + 0.5 }}" cy="{{ $y + 0.5 }}" r="0.45" fill="{{ $c['dark'] }}"/>
        @else
            <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" fill="{{ $c['dark'] }}"/>
        @endif
    @endforeach
</svg>
