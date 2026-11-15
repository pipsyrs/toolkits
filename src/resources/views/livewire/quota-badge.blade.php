@php
    $label = ($metric ?? 'links') === 'file_ops' ? 'File' : 'Link';
    $unlimited = $limit === null;
    $low = ! $unlimited && $remaining !== null && $remaining <= max(1, (int) ($limit * 0.2));
@endphp

<div class="badge {{ $low ? 'badge-danger' : 'badge-muted' }} rounded-full px-2.5 py-1 tabular-nums" title="Sisa kuota {{ $label }} hari ini">
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    <span>{{ $label }}: {{ $unlimited ? 'Tanpa batas' : ($remaining ?? 0) . '/' . $limit }}</span>
</div>
