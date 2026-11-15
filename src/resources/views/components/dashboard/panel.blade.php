@props([
    'title',
    'id',
    'subtitle' => null,
    'href' => null,
    'hrefLabel' => null,
    'delay' => 0, // urutan animasi masuk
])

<section aria-labelledby="{{ $id }}" style="animation-delay: {{ (int) $delay * 70 }}ms"
    {{ $attributes->class('glass flex h-full flex-col p-5 animate-rise') }}>
    <header class="mb-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="{{ $id }}" class="font-display text-base font-bold tracking-tight text-fg">{{ $title }}</h2>
            @if($subtitle)
                <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @if($href)
            <a href="{{ $href }}" class="group inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-muted transition-colors hover:bg-fg/6 hover:text-fg">
                {{ $hrefLabel }}
                <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endif
    </header>
    <div class="min-w-0 flex-1">
        {{ $slot }}
    </div>
</section>
