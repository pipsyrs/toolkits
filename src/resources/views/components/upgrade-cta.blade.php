@props(['reason' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-tool/30 bg-tool/10 p-4 sm:flex-row sm:items-center']) }}
    role="status">
    <svg class="h-6 w-6 shrink-0 text-tool-deep" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-fg">Butuh lebih banyak?</p>
        <p class="text-sm text-muted">{{ $reason ?? 'Tingkatkan ke Pro untuk batas lebih besar dan tanpa iklan.' }}</p>
    </div>
    <a href="{{ route('pricing') }}" class="btn-primary btn-sm shrink-0">Lihat Pro</a>
</div>
