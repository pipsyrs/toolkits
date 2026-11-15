@props(['channel' => null, 'logo' => null, 'alt' => ''])

{{-- Uploaded logo when the method has one, else a text badge in the channel's color; callers set the width. Empty alt = decorative. --}}
@if($logo)
    <img src="{{ $logo }}" alt="{{ $alt }}" width="72" height="28" loading="lazy" decoding="async" {{ $attributes->class('h-7 shrink-0 object-contain') }}>
@elseif($channel)
    <span aria-hidden="true" {{ $attributes->class('inline-grid h-7 shrink-0 place-items-center rounded-md px-1.5 text-[10px] font-extrabold leading-none tracking-tight') }}
        style="background-color: {{ $channel->color }}; color: {{ \App\Support\Color::readableOn($channel->color) }}">{{ $channel->name }}</span>
@endif
