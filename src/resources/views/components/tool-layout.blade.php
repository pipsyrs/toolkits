@props(['slug', 'steps' => []])

{{-- Kerangka halaman alat: header, area kerja (slot utama) + panel samping lengket, cara pakai, alat terkait.
     Nama, deskripsi, dan kategori diambil dari katalog agar sama dengan kartu alat. --}}
@php
    $catalog = app(\App\Services\Tools\ToolCatalog::class);
    $tool = $catalog->find($slug) ?? ['slug' => $slug, 'name' => '', 'description' => '', 'category' => null, 'category_name' => '-'];
    $related = collect($catalog->all())
        ->filter(fn ($t) => $t['slug'] !== $slug && $t['category'] === $tool['category'] && $t['status'] === \App\Enums\ToolStatus::Live->value)
        ->take(4);
    $tone = match ($tool['category']) {
        'image' => 'text-image',
        'pdf' => 'text-pdf',
        default => 'text-link',
    };
@endphp

<div class="space-y-10">
    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-tool text-ink shadow-[inset_0_-3px_0_rgb(0_0_0/0.18)]">
                <x-tool-glyph :slug="$slug" :category="$tool['category']" class="h-7 w-7" />
            </span>
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="text-xs font-medium text-muted">
                    <a href="{{ route('tools.index') }}" class="hover:text-fg">Alat</a>
                    <span aria-hidden="true" class="mx-1">/</span>
                    <span class="{{ $tone }}">{{ $tool['category_name'] }}</span>
                </nav>
                <h1 class="page-title mt-0.5">{{ $tool['name'] }}</h1>
                <p class="mt-1 max-w-[60ch] text-sm text-muted">{{ $tool['description'] }}</p>
            </div>
        </div>
        @isset($meta)
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">{{ $meta }}</div>
        @endisset
    </header>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem]">
        <section class="tool-stage min-w-0 space-y-5" aria-label="Area kerja">
            {{ $slot }}
        </section>

        @isset($aside)
            <aside class="min-w-0 space-y-4 lg:sticky lg:top-24" aria-label="Pengaturan">
                {{ $aside }}
            </aside>
        @endisset
    </div>

    @if($steps !== [] || $related->isNotEmpty())
        <div class="grid gap-8 border-t border-line pt-8 lg:grid-cols-[minmax(0,1fr)_23rem]">
            @if($steps !== [])
                <section aria-labelledby="how-to">
                    <h2 id="how-to" class="eyebrow">Cara pakai</h2>
                    <ol class="mt-4 grid gap-4 sm:grid-cols-3">
                        @foreach($steps as $i => [$title, $text])
                            <li class="flex gap-3">
                                <span class="font-display text-2xl font-extrabold leading-none text-tool-deep tabular-nums">{{ $i + 1 }}</span>
                                <span>
                                    <span class="block text-sm font-semibold text-fg">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm text-muted">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @if($related->isNotEmpty())
                <section aria-labelledby="related-tools">
                    <h2 id="related-tools" class="eyebrow">Alat lain di {{ $tool['category_name'] }}</h2>
                    <ul class="mt-4 grid gap-2">
                        @foreach($related as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" class="group flex items-center gap-3 rounded-md px-2 py-1.5 text-sm text-fg hover:bg-sunken">
                                    <x-tool-glyph :slug="$item['slug']" :category="$item['category']" class="h-5 w-5 shrink-0 {{ $tone }}" />
                                    <span class="flex-1 font-medium">{{ $item['name'] }}</span>
                                    <svg class="h-4 w-4 text-muted transition group-hover:translate-x-0.5 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @endif
</div>
