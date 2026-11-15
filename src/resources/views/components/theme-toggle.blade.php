@php
    $modes = [
        'light' => ['Terang', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'],
        'dark' => ['Gelap', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'],
        'system' => ['Ikuti sistem', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
    ];
@endphp

{{-- Toggle tema: state & keyboard dikelola resources/js/theme.js --}}
<div role="radiogroup" aria-label="Tema tampilan"
    {{ $attributes->class('inline-flex shrink-0 items-center rounded-md border border-line p-0.5') }}>
    @foreach($modes as $mode => [$label, $icon])
        <button type="button" role="radio" aria-checked="false" tabindex="{{ $mode === 'system' ? 0 : -1 }}"
            data-theme-set="{{ $mode }}" title="{{ $label }}" aria-label="{{ $label }}"
            class="grid h-7 w-7 place-items-center rounded text-muted transition-colors hover:text-fg aria-checked:bg-sunken aria-checked:text-fg">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
        </button>
    @endforeach
</div>
