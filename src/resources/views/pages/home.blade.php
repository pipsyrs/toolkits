@php($tools = app(\App\Services\Tools\ToolCatalog::class)->all())

<x-layouts::app title="Alat online gratis">
    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm sm:px-12 dark:border-slate-800 dark:bg-slate-900">
        <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-brand-500/10 blur-3xl" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-2xl">
            <span class="badge mb-4 bg-brand-50 text-brand-700 dark:bg-brand-600/15 dark:text-brand-300">Gratis · Tanpa instal</span>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                Semua alat harianmu dalam satu tempat
            </h1>
            <p class="mt-4 text-base text-slate-600 sm:text-lg dark:text-slate-300">
                Perpendek tautan, buat QR, konversi dan kompres gambar, kelola PDF. Cepat, aman, langsung dari browser.
            </p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('tools.index') }}" class="btn-primary">Jelajahi semua alat</a>
                <a href="{{ route('pricing') }}" class="btn-secondary">Lihat paket harga</a>
            </div>
        </div>
    </section>

    {{-- Kategori --}}
    <section class="mt-10 grid gap-5 sm:grid-cols-3">
        @foreach([
            ['Link', 'Perpendek & QR code', 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'],
            ['Gambar', 'Konversi, kompres, resize', 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'],
            ['PDF', 'Gabung, pisah, kompres', 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'],
        ] as [$name, $desc, $cls])
            <div class="card flex items-center gap-4 p-5">
                <span class="badge {{ $cls }} px-3 py-1">{{ $name }}</span>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $desc }}</p>
            </div>
        @endforeach
    </section>

    {{-- Grid tool --}}
    <section class="mt-10">
        <div class="mb-4 flex items-end justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Alat populer</h2>
            <a href="{{ route('tools.index') }}" class="link text-sm">Lihat semua</a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @forelse($tools as $tool)
                <x-tool-card :tool="$tool" />
            @empty
                <div class="card col-span-full p-8 text-center text-sm text-slate-500 dark:text-slate-400">Katalog alat sedang disiapkan.</div>
            @endforelse
        </div>
    </section>
</x-layouts::app>
