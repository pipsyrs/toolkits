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
        'image' => ['badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'tile' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300'],
        'pdf' => ['badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'tile' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300'],
        default => ['badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'tile' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
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
            <x-tool-glyph :slug="$tool['slug'] ?? null" :category="$cat" />
        </span>
        <div class="flex flex-wrap justify-end gap-1.5">
            <span class="badge {{ $catStyle['badge'] }}">{{ $tool['category_name'] ?? $cat }}</span>
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
