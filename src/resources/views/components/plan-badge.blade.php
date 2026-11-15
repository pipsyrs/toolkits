@props(['limitKey' => null, 'plan' => null])

{{-- Badge paket untuk fitur berbayar: nama paket pemilik (via limitKey) atau 'Terkunci' bila tak ada paket yang membukanya. --}}
@php
    $name = $plan ?? ($limitKey ? app(\App\Services\Tier\PlanLimits::class)->planForLimit($limitKey) : null);
@endphp

<span {{ $attributes->class(['badge', 'badge-pro' => $name, 'badge-muted' => ! $name]) }} role="note"
    aria-label="{{ $name ? 'Butuh paket '.$name : 'Fitur terkunci' }}">
    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
    {{ $name ?? 'Terkunci' }}
</span>
