{{-- CTA untuk pengunjung belum login: hasil dipindah ke akun saat masuk/daftar (ADR-003).
     Tata letak mengikuti lebar wadah, jadi tetap rapi di panel samping yang sempit. --}}
<div {{ $attributes->merge(['class' => '@container rounded-xl border border-tool/30 bg-tool/10 p-4']) }} role="status">
    <div class="flex flex-col gap-3 @md:flex-row @md:items-center">
        <svg class="h-6 w-6 shrink-0 text-tool-deep" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-fg">Simpan hasilmu</p>
            <p class="text-sm text-muted">Masuk atau daftar gratis agar hasil ini tersimpan di dashboard-mu.</p>
        </div>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('login') }}" class="btn-secondary btn-sm">Masuk</a>
            <a href="{{ route('register') }}" class="btn-primary btn-sm">Daftar</a>
        </div>
    </div>
</div>
