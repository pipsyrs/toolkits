@props(['limits'])

{{-- Chip info paket di header alat file; batas ukuran/jumlah file sudah tampil di area unggah. --}}
@php
    $retention = \App\Services\Tier\Entitlements::retentionMinutes($limits);
    $kept = match (true) {
        $retention === null => 'Hasil disimpan permanen',
        $retention >= 1440 => 'Hasil disimpan '.intdiv($retention, 1440).' hari',
        $retention >= 60 => 'Hasil disimpan '.intdiv($retention, 60).' jam',
        default => "Hasil disimpan {$retention} menit",
    };
@endphp

<span class="badge badge-muted">
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    {{ $kept }}
</span>
<span class="badge badge-muted">
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
    File privat
</span>
@if(($limits['queue'] ?? 'default') === 'pro')
    <span class="badge badge-pro">Antrean prioritas</span>
@endif
