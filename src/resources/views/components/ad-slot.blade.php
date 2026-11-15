@props(['position' => 'inline'])

{{-- Slot unit iklan (tier dengan limit ads). Placeholder sampai jaringan iklan dipasang. --}}
<div {{ $attributes->merge(['class' => 'grid min-h-[250px] w-full max-w-[336px] place-items-center rounded-md border border-dashed border-current/30 text-center text-xs opacity-70']) }}
    role="complementary" aria-label="Iklan" data-ad-position="{{ $position }}">
    <span>Ruang iklan 336 × 280</span>
</div>
