@props(['channel'])

{{-- Text badge in the channel's brand color (no third-party logos); callers set the width. --}}
<span aria-hidden="true" {{ $attributes->class('inline-grid h-7 shrink-0 place-items-center rounded-md px-1.5 text-[10px] font-extrabold leading-none tracking-tight') }}
    style="background-color: {{ $channel->color() }}; color: {{ \App\Support\Color::readableOn($channel->color()) }}">{{ $channel->label() }}</span>
