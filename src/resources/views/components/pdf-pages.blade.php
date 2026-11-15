@props(['label' => 'Halaman PDF'])

{{-- Grid thumbnail halaman; dipakai di dalam scope Alpine pdfPages / pdfSplit / pdfResult. --}}
<div {{ $attributes->class('space-y-3') }}>
    <p x-show="loading" class="flex items-center gap-2 text-sm text-muted" role="status">
        <svg class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
        Menyiapkan pratinjau…
    </p>
    <p x-show="error" x-cloak class="field-error" role="alert" x-text="error"></p>

    <ul x-show="pages.length" x-cloak class="grid grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-3" aria-label="{{ $label }}">
        <template x-for="page in pages" :key="page.key">
            <li class="relative" :title="page.title">
                <div x-init="watch($el, page)" :class="pageClass(page)"
                    class="relative grid aspect-[3/4] place-items-center overflow-hidden rounded-md border border-line bg-white transition">
                    <img x-show="page.url" :src="page.url" alt="" class="h-full w-full object-contain">
                    <span x-show="!page.url && !page.failed" class="h-6 w-6 animate-pulse rounded-full bg-sunken motion-reduce:animate-none" aria-hidden="true"></span>
                    <span x-show="page.failed" class="px-2 text-center text-xs text-muted">Gagal dimuat</span>
                    <span x-show="page.badge" x-text="page.badge" class="absolute left-1 top-1 grid h-5 min-w-5 place-items-center rounded bg-ink px-1 text-[11px] font-semibold text-white" aria-hidden="true"></span>
                </div>
                <p class="mt-1 truncate text-center text-xs text-muted" x-text="page.label"></p>
                <button x-show="clickable" type="button" @click="pick(page)" class="absolute inset-0 rounded-md focus-visible:outline-2"
                    :aria-label="`Halaman ${page.number}: ${page.title}`"></button>
            </li>
        </template>
    </ul>

    <p x-show="hidden() > 0" x-cloak class="text-xs text-muted" x-text="`Pratinjau menampilkan ${pages.length} dari ${total} halaman.`"></p>
</div>
