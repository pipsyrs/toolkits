{{-- $tool: catalog entry or null, $status: App\Enums\ToolStatus --}}
<x-layouts::app :title="$tool['name'] ?? 'Alat tidak tersedia'">
    <div class="mx-auto max-w-md py-10 text-center">
        <div class="card p-8">
            <span class="badge mb-4 bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $status->label() }}</span>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $tool['name'] ?? 'Alat tidak tersedia' }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $status->message() }}</p>
            <a href="{{ route('tools.index') }}" class="btn-primary mt-6 w-full">Lihat alat lain</a>
        </div>
    </div>
</x-layouts::app>
