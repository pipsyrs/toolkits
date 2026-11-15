@props(['title' => 'Pratinjau', 'hint' => null])

{{-- Bingkai pratinjau langsung; isi dan data Alpine dari pemanggil. wire:ignore: isinya dikelola Alpine. --}}
<section {{ $attributes->class('space-y-3 rounded-lg border border-line bg-sunken/50 p-4') }} x-cloak wire:ignore data-no-save>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-fg">{{ $title }}</h2>
        @if($hint)
            <span class="badge bg-sunken text-muted">{{ $hint }}</span>
        @endif
    </div>
    {{ $slot }}
</section>
