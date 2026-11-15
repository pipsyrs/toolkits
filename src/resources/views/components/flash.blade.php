@php
    $map = [
        'success' => ['border-ok/30 bg-ok/10 text-ok'],
        'error'   => ['border-danger/30 bg-danger/10 text-danger'],
        'warning' => ['border-warn/30 bg-warn/10 text-warn'],
        'status'  => ['border-line bg-surface text-fg'],
    ];
@endphp

@foreach(['success', 'error', 'warning', 'status'] as $key)
    @if(session()->has($key))
        <div x-data="{ show: true }" x-show="show" x-cloak x-transition.opacity.duration.200ms role="alert"
            class="relative mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium {{ $map[$key][0] }}">
            <span class="min-w-0 flex-1">{{ session($key) }}</span>
            <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Tutup">
                <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
@endforeach
