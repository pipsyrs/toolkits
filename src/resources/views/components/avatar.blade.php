@props(['user', 'size' => 'h-8 w-8'])

@php
    $name = (string) ($user?->name ?: $user?->email ?: '?');
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@if($user?->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $name }}" referrerpolicy="no-referrer" loading="lazy"
        {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-1 ring-line"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size grid shrink-0 place-items-center rounded-full bg-tool text-xs font-bold text-ink"]) }}
        role="img" aria-label="{{ $name }}">{{ $initials }}</span>
@endif
