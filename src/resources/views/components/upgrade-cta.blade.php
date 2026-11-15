@props(['reason' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center dark:border-amber-500/30 dark:bg-amber-500/10']) }}
    role="status">
    <svg class="h-6 w-6 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Butuh lebih banyak?</p>
        <p class="text-sm text-amber-700 dark:text-amber-300/90">{{ $reason ?? 'Tingkatkan ke Pro untuk batas lebih besar dan tanpa iklan.' }}</p>
    </div>
    <a href="{{ route('pricing') }}" class="btn-primary btn-sm shrink-0 bg-amber-500 hover:bg-amber-600">Lihat Pro</a>
</div>
