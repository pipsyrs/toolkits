@props(['status'])

@php
    $tone = match ($status) {
        \App\Enums\ToolStatus::Live => 'badge-ok',
        \App\Enums\ToolStatus::Disabled => 'badge-muted',
        default => 'badge-warn',
    };
@endphp

<span {{ $attributes->class(['badge gap-1.5', $tone]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $status->label() }}
</span>
