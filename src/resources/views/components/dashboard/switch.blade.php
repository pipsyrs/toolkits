@props(['on' => false, 'labelledby'])

{{-- Toggle on/off; the slot is the screen-reader action text. --}}
<button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-labelledby="{{ $labelledby }}"
    {{ $attributes->class([
        'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none',
        'bg-ok' => $on,
        'bg-line' => ! $on,
    ]) }}>
    <span class="sr-only">{{ $slot }}</span>
    <span aria-hidden="true" @class([
        'inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-[cubic-bezier(0.2,0.7,0.2,1)] motion-reduce:transition-none',
        'translate-x-[1.375rem]' => $on,
        'translate-x-0.5' => ! $on,
    ])></span>
</button>
