{{-- Logo unggahan admin (sudah berisi nama) bila ada; selain itu label gantung kuning (lubang = gantungan papan perkakas) + nama --}}
@php($logo = \App\Support\Branding::logoUrl())
@if($logo)
<span {{ $attributes->class('inline-flex max-w-40 items-center') }}>
    <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="32" class="h-8 w-auto max-w-full object-contain">
</span>
@else
<span {{ $attributes->class('inline-flex items-center gap-2 font-display text-lg font-extrabold tracking-tight text-fg') }}>
    <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="2" y="2" width="20" height="20" rx="4" class="fill-tool"/>
        <circle cx="7.5" cy="7.5" r="2" class="fill-ink"/>
        <path d="M11 12.5h7M11 16.5h4.5" class="stroke-ink" stroke-width="2.2" stroke-linecap="round"/>
    </svg>
    <span>ToolKits</span>
</span>
@endif
