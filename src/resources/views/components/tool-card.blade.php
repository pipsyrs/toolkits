@props(['tool'])

@php
    $status = \App\Enums\ToolStatus::tryFrom($tool['status'] ?? 'live') ?? \App\Enums\ToolStatus::Live;
    $isLive = $status === \App\Enums\ToolStatus::Live;
    $statusBadge = match ($status) {
        \App\Enums\ToolStatus::Maintenance => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        \App\Enums\ToolStatus::Disabled => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
        default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    };
    $cat = $tool['category'] ?? 'link';
    $catStyle = match ($cat) {
        'image' => ['label' => 'Gambar', 'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'tile' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300'],
        'pdf' => ['label' => 'PDF', 'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'tile' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300'],
        default => ['label' => 'Link', 'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'tile' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
    };
    // Ikon per slug (heroicons outline), fallback per kategori
    $icon = match ($tool['slug'] ?? '') {
        'shorten' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        'qr-code' => 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 4h2m4 0h.01M14 14h.01M20 14v.01M18 20h2v-2',
        'image-convert' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'image-compress' => 'M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25',
        'image-resize' => 'M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4',
        'pdf-merge' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2',
        'pdf-split' => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z',
        'pdf-compress' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
        'image-to-pdf' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        default => $cat === 'pdf'
            ? 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'
            : 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
    };
    $tag = $isLive ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($isLive) href="{{ route($tool['route']) }}" @else aria-disabled="true" @endif
    {{ $attributes->merge(['class' => 'card group flex h-full flex-col p-5 transition duration-200 '
        . ($isLive
            ? 'hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md focus-visible:-translate-y-0.5 focus-visible:border-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:border-brand-500/50 motion-reduce:hover:translate-y-0'
            : 'cursor-not-allowed opacity-70')]) }}>
    <div class="flex items-start justify-between gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $catStyle['tile'] }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
        </span>
        <div class="flex flex-wrap justify-end gap-1.5">
            <span class="badge {{ $catStyle['badge'] }}">{{ $catStyle['label'] }}</span>
            @unless($isLive)
                <span class="badge {{ $statusBadge }}">{{ $status->label() }}</span>
            @endunless
        </div>
    </div>

    <h3 class="mt-4 truncate font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300" title="{{ $tool['name'] }}">{{ $tool['name'] }}</h3>
    <p class="mt-1 mb-4 line-clamp-2 min-h-10 text-sm text-slate-500 dark:text-slate-400">{{ $tool['description'] ?? '' }}</p>

    <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-4 text-sm font-medium dark:border-slate-800">
        @if($isLive)
            <span class="text-brand-600 dark:text-brand-400">Buka alat</span>
            <svg class="h-4 w-4 text-brand-600 transition group-hover:translate-x-0.5 motion-reduce:transition-none dark:text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        @else
            <span class="text-slate-500 dark:text-slate-400">{{ $status->label() }}</span>
        @endif
    </div>
</{{ $tag }}>
