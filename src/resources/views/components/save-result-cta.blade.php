{{-- CTA untuk pengunjung belum login: hasil dipindah ke akun saat masuk/daftar (ADR-003). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 sm:flex-row sm:items-center dark:border-brand-500/30 dark:bg-brand-600/10']) }}
    role="status">
    <svg class="h-6 w-6 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-brand-800 dark:text-brand-200">Simpan hasilmu</p>
        <p class="text-sm text-brand-700 dark:text-brand-300/90">Masuk atau daftar gratis agar hasil ini tersimpan di dashboard-mu.</p>
    </div>
    <div class="flex shrink-0 gap-2">
        <a href="{{ route('login') }}" class="btn-secondary btn-sm">Masuk</a>
        <a href="{{ route('register') }}" class="btn-primary btn-sm">Daftar</a>
    </div>
</div>
