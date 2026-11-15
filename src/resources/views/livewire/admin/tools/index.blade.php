@php
    use App\Enums\ToolStatus;

    $liveCount = collect($tools)->where('status', ToolStatus::Live->value)->count();
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-1 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Pilih alat untuk mengatur status dan fitur Pro-nya.</p>
        @if($tools)
            <p class="text-sm text-muted"><span class="font-semibold tabular-nums text-fg">{{ $liveCount }}</span> dari {{ count($tools) }} alat aktif</p>
        @endif
    </div>

    @if(empty($tools))
        <div class="glass px-6 py-14 text-center animate-rise">
            <p class="font-semibold text-fg">Belum ada alat di katalog.</p>
            <p class="mt-1 text-sm text-muted">Tambahkan alat di <code class="font-mono text-xs">config/toolkits.php</code>.</p>
        </div>
    @else
        <ul class="stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($tools as $tool)
                @php
                    $status = ToolStatus::from($tool['status']);
                    $features = $tool['features'];
                @endphp
                <li wire:key="tool-{{ $tool['slug'] }}">
                    <a href="{{ route('admin.tools.show', $tool['slug']) }}"
                        class="glass glass-hover group flex h-full flex-col gap-4 p-5 hover:border-line-strong">
                        <div class="flex items-start gap-3">
                            <x-dashboard.tool-icon :category="$tool['category'] ?? null" />
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-semibold text-fg">{{ $tool['name'] }}</h3>
                                <p class="truncate font-mono text-xs text-muted">/tools/{{ $tool['slug'] }}</p>
                            </div>
                            <svg class="mt-1 h-4 w-4 shrink-0 text-muted transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-fg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div class="mt-auto flex flex-wrap items-center gap-2">
                            <x-dashboard.tool-status :status="$status" />
                            <span class="badge badge-muted">{{ $tool['category_name'] }}</span>
                            @if($features)
                                <span class="badge badge-muted">{{ count($features) }} fitur per paket</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
