@php
    $catalog = app(\App\Services\Tools\ToolCatalog::class);
    $tools = $catalog->all();
    // Only categories that currently hold a tool get a filter button.
    $filters = collect($catalog->categories())->only(collect($tools)->pluck('category')->unique()->all());
@endphp

<x-layouts::app title="Semua alat">
    <div x-data="{ filter: 'all' }">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Semua alat</h1>
            <p class="mt-1 max-w-prose text-sm text-slate-500 dark:text-slate-400">Pilih alat yang kamu butuhkan. Tanpa instal, langsung jalan.</p>
        </header>

        {{-- Filter kategori --}}
        <div class="mb-6 flex flex-wrap gap-2" role="tablist" aria-label="Filter kategori">
            @foreach(collect(['all' => 'Semua'])->union($filters) as $val => $label)
                <button type="button" role="tab" @click="filter = '{{ $val }}'" :aria-selected="filter === '{{ $val }}'"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                    :class="filter === '{{ $val }}'
                        ? 'bg-brand-600 text-white'
                        : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @forelse($tools as $tool)
                <div x-show="filter === 'all' || filter === '{{ $tool['category'] ?? 'link' }}'" x-transition.opacity class="h-full">
                    <x-tool-card :tool="$tool" />
                </div>
            @empty
                <div class="card col-span-full p-8 text-center text-sm text-slate-500 dark:text-slate-400">Katalog alat sedang disiapkan.</div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
