@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $paid = $tier->isPaid();
    $firstName = Str::before((string) auth()->user()?->name, ' ') ?: 'kamu';
    $today = now()->setTimezone(config('toolkits.timezone'))->locale('id')->translatedFormat('l, j F Y');
    $quotaTiles = [
        ['key' => 'links', 'label' => 'Sisa link hari ini', 'action' => 'Buat link baru', 'route' => 'tools.shorten',
            'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
        ['key' => 'file_ops', 'label' => 'Sisa proses file hari ini', 'action' => 'Buka alat file', 'route' => 'tools.index',
            'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
    ];
    // Quick links: live tools only, in catalog order.
    $quickTools = collect(app(\App\Services\Tools\ToolCatalog::class)->all())
        ->where('status', \App\Enums\ToolStatus::Live->value)
        ->filter(fn ($tool) => Route::has($tool['route'] ?? ''))
        ->take(5);
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 animate-rise sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">{{ $today }}</p>
            <h2 class="page-title mt-1">Halo, {{ $firstName }}</h2>
            <p class="mt-1 text-sm text-muted">Pemakaian ToolKits kamu hari ini.</p>
        </div>
        <a href="{{ route('tools.shorten') }}" class="btn-primary self-start sm:self-auto">Perpendek tautan</a>
    </div>

    <section aria-label="Paket dan kuota" class="stagger grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Paket --}}
        <div class="glass glass-hover group flex flex-col p-5">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-fg/6 text-fg transition-colors group-hover:bg-tool group-hover:text-ink">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                </span>
                <p class="text-sm font-medium text-muted">Paket kamu</p>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <p class="font-display text-3xl font-extrabold tracking-tight text-fg">{{ $tier->label() }}</p>
                @if($paid) <span class="badge badge-ok">Aktif</span> @endif
            </div>
            <div class="mt-auto pt-3 text-sm">
                @if($paid && $planUntil)
                    <p class="text-muted">Berlaku s.d. {{ Carbon::parse($planUntil)->translatedFormat('d M Y') }}</p>
                @elseif(! $paid)
                    <a href="{{ route('dashboard.billing') }}" class="link">Tingkatkan ke Pro</a>
                @endif
            </div>
        </div>

        {{-- Kuota harian --}}
        @foreach($quotaTiles as $tile)
            @php
                $quota = $quotas[$tile['key']];
                $limit = $quota['limit'];
                $remaining = $quota['remaining'];
                $usedPct = $limit ? (int) round(($limit - (int) $remaining) / $limit * 100) : 0;
            @endphp
            <div class="glass glass-hover group flex flex-col p-5">
                <div class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-fg/6 text-fg transition-colors group-hover:bg-tool group-hover:text-ink">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['icon'] }}"/></svg>
                    </span>
                    <p class="text-sm font-medium text-muted">{{ $tile['label'] }}</p>
                </div>
                <p class="mt-4 font-display text-3xl font-extrabold tracking-tight tabular-nums text-fg">
                    {{ $limit === null ? 'Tanpa batas' : number_format((int) $remaining) }}
                    @if($limit !== null) <span class="text-base font-semibold text-muted">/ {{ number_format($limit) }}</span> @endif
                </p>
                @if($limit !== null)
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-fg/8" role="meter" aria-label="Kuota terpakai" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usedPct }}">
                        <div @class(['h-full origin-left rounded-full animate-grow', 'bg-danger' => $usedPct >= 80, 'bg-tool-deep' => $usedPct < 80]) style="width: {{ $usedPct }}%"></div>
                    </div>
                @endif
                <a href="{{ route($tile['route']) }}" class="link mt-auto self-start pt-3 text-sm">{{ $tile['action'] }}</a>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-dashboard.panel title="Link terbaru" id="recent-links" :href="route('dashboard.links.index')" href-label="Lihat semua" delay="3" class="lg:col-span-2">
            @if($recentLinks->isNotEmpty())
                <ul class="-mx-5 divide-y divide-line">
                    @foreach($recentLinks as $link)
                        <li class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-fg/[0.03]">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-mono text-sm font-medium text-link">/s/{{ $link->code }}</p>
                                <p class="truncate text-xs text-muted">{{ $link->target_host }}</p>
                            </div>
                            <span class="shrink-0 text-sm tabular-nums text-muted">{{ number_format($link->clicks_total) }} klik</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-8 text-center">
                    <p class="text-sm text-muted">Belum ada link. Mulai buat tautan pendek pertamamu.</p>
                    <a href="{{ route('tools.shorten') }}" class="btn-secondary mt-4">Perpendek tautan</a>
                </div>
            @endif
        </x-dashboard.panel>

        <x-dashboard.panel title="Alat cepat" id="quick-tools" :href="route('tools.index')" href-label="Semua alat" delay="4">
            <ul class="-mx-2 space-y-1">
                @foreach($quickTools as $tool)
                    <li>
                        <a href="{{ route($tool['route']) }}" class="group flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-fg/6">
                            <x-dashboard.tool-icon :category="$tool['category'] ?? null" class="h-9 w-9" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-fg">{{ $tool['name'] }}</span>
                            <svg class="h-4 w-4 shrink-0 text-muted transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-dashboard.panel>
    </div>
</div>
