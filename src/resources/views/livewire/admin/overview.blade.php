@php
    use App\Support\ChartAxis;
    use Illuminate\Support\Str;

    // Kontrak: $summary, $dayLabels, $signups, $linksByTier, $revenue|null, $topLinks|null, $topHosts|null, $audit|null; wire:model days (7|30|90).
    $num = fn ($v) => ChartAxis::format($v);
    // Perubahan vs periode sebelumnya: [teks, naik?] (naik null = tanpa pembanding)
    $delta = function (int $now, int $prev) {
        if ($prev === 0) {
            return [$now > 0 ? 'Baru' : 'Belum ada data', null];
        }
        $pct = (int) round(($now - $prev) / $prev * 100);

        return [($pct > 0 ? '+' : '') . $pct . '%', $pct >= 0];
    };
    // Sparkline 100x32 (viewBox), dirender sebagai garis + area tipis.
    $spark = function (array $values) {
        $values = array_values($values);
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $max = max(max($values), 1);
        $pts = collect($values)->map(fn ($v, $i) => round($i / ($n - 1) * 100, 2) . ' ' . round(30 - $v / $max * 28, 2));

        return ['line' => 'M' . $pts->implode(' L'), 'area' => 'M' . $pts->implode(' L') . ' L100 32 L0 32 Z'];
    };
    $period = $days . ' hari';
    $tiers = [
        'guest' => ['Guest', 'var(--c-s1)'],
        'free' => ['Free', 'var(--c-s2)'],
        'pro' => ['Pro', 'var(--c-s3)'],
    ];
    $linksDaily = array_map(fn (...$v) => array_sum($v), ...array_values($linksByTier));

    $icons = [
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.5-1.35',
        'pro' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        'links' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        'clicks' => 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122',
        'revenue' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    ];

    $tiles = [
        ['key' => 'users', 'label' => 'Pengguna', 'value' => $summary['users'], 'sub' => '+' . $num($summary['users_new']) . ' baru dalam ' . $period,
            'change' => $delta($summary['users_new'], $summary['users_new_prev']), 'extra' => $num($summary['users_blocked']) . ' diblokir', 'trend' => $signups, 'color' => 'var(--c-s1)'],
        ['key' => 'pro', 'label' => 'Pro aktif', 'value' => $summary['pro_active'],
            'sub' => $summary['pro_expiring'] > 0 ? $num($summary['pro_expiring']) . ' berakhir dalam 7 hari' : 'Tidak ada yang berakhir dalam 7 hari', 'change' => null, 'extra' => null, 'trend' => null, 'color' => 'var(--c-s3)'],
        ['key' => 'links', 'label' => 'Link', 'value' => $summary['links'], 'sub' => '+' . $num($summary['links_new']) . ' baru dalam ' . $period,
            'change' => $delta($summary['links_new'], $summary['links_new_prev']), 'extra' => $num($summary['links_disabled']) . ' dinonaktifkan', 'trend' => $linksDaily, 'color' => 'var(--c-s2)'],
        ['key' => 'clicks', 'label' => 'Total klik', 'value' => $summary['clicks'], 'sub' => 'Semua link, sejak awal', 'change' => null, 'extra' => null, 'trend' => null, 'color' => 'var(--c-s1)'],
    ];
    if ($revenue) {
        $months = $revenue['values'];
        $thisMonth = (int) end($months);
        $lastMonth = (int) (count($months) > 1 ? $months[count($months) - 2] : 0);
        $tiles[] = ['key' => 'revenue', 'label' => 'Pendapatan bulan ini', 'value' => null, 'display' => ChartAxis::format($thisMonth, 'rupiah', true),
            'sub' => $num($revenue['pending']) . ' invoice menunggu dibayar', 'change' => $delta($thisMonth, $lastMonth), 'extra' => null, 'trend' => $months, 'color' => 'var(--c-s3)'];
    }
    $actionLabel = fn ($a) => Str::ucfirst(str_replace(['.', '_'], ' ', (string) $a));
    $today = now()->setTimezone(config('toolkits.timezone'))->locale('id')->translatedFormat('l, j F Y');
    $firstName = Str::before((string) auth()->user()?->name, ' ') ?: 'Admin';
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    {{-- Sapaan + filter periode --}}
    <div class="flex flex-col gap-4 animate-rise sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-medium uppercase tracking-wider text-muted">{{ $today }}</p>
            <h2 class="mt-1 font-display text-2xl font-extrabold tracking-tight text-fg sm:text-3xl">Halo, {{ $firstName }}</h2>
            <p class="mt-1 text-sm text-muted">Kondisi ToolKits dalam {{ $period }} terakhir.</p>
        </div>
        <div class="flex flex-col items-start gap-1.5 sm:items-end">
            <fieldset class="inline-flex rounded-lg border border-line bg-surface p-1 text-sm shadow-sm">
                <legend class="sr-only">Periode</legend>
                @foreach(\App\Livewire\Admin\Overview::PERIODS as $p)
                    <label class="cursor-pointer rounded-md px-3.5 py-1.5 font-medium text-muted transition-colors duration-200 hover:text-fg has-[:checked]:bg-ink has-[:checked]:text-white dark:has-[:checked]:bg-fg dark:has-[:checked]:text-ink has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-fg">
                        <input type="radio" wire:model.live="days" value="{{ $p }}" class="sr-only">{{ $p }} hari
                    </label>
                @endforeach
            </fieldset>
            <p class="flex items-center gap-1.5 text-xs text-muted">
                <span class="relative flex h-2 w-2" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-ok opacity-60 motion-reduce:hidden"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-ok"></span>
                </span>
                Angka diperbarui tiap 10 menit.
            </p>
        </div>
    </div>

    <div class="space-y-6 transition-opacity duration-300" wire:loading.class="opacity-50" wire:target="days">
        {{-- Ringkasan angka --}}
        <section aria-label="Ringkasan" @class([
            'grid gap-4 sm:grid-cols-2',
            'xl:grid-cols-5' => count($tiles) === 5,
            'xl:grid-cols-4' => count($tiles) !== 5,
        ])>
            @foreach($tiles as $i => $tile)
                @php
                    $display = $tile['display'] ?? $num($tile['value']);
                    $sparkPath = $tile['trend'] ? $spark($tile['trend']) : null;
                @endphp
                <div wire:key="tile-{{ $tile['key'] }}-{{ $days }}-{{ $display }}" style="animation-delay: {{ $i * 70 }}ms"
                    @class([
                        'glass group relative flex flex-col overflow-hidden p-5 animate-rise transition duration-200 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-md motion-reduce:hover:translate-y-0',
                        'sm:col-span-2 xl:col-span-1' => $loop->last && $loop->count % 2 === 1,
                    ])>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-sunken text-fg transition-colors group-hover:bg-tool group-hover:text-ink">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$tile['key']] }}"/></svg>
                            </span>
                            <p class="truncate text-sm font-medium text-muted">{{ $tile['label'] }}</p>
                        </div>
                        @if($tile['change'])
                            @php([$changeText, $up] = $tile['change'])
                            <span @class([
                                'inline-flex shrink-0 items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                'bg-ok/12 text-ok' => $up === true,
                                'bg-danger/12 text-danger' => $up === false,
                                'bg-sunken text-muted' => $up === null,
                            ]) title="Dibanding {{ $period }} sebelumnya">
                                @if($up !== null)
                                    <svg class="h-3 w-3 {{ $up ? '' : 'rotate-180' }}" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 2l4 6H2z"/></svg>
                                    <span class="sr-only">{{ $up ? 'Naik' : 'Turun' }}</span>
                                @endif
                                {{ $changeText }}
                            </span>
                        @endif
                    </div>

                    @if($tile['value'] !== null)
                        <p class="mt-4 font-display text-3xl font-extrabold tracking-tight tabular-nums text-fg"
                            x-data="{ shown: @js($display), target: {{ (int) $tile['value'] }} }"
                            x-init="if (target >= 10 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                const t0 = performance.now(), d = 900;
                                const tick = (t) => { const p = Math.min(1, (t - t0) / d); shown = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('id-ID'); if (p < 1) requestAnimationFrame(tick) };
                                requestAnimationFrame(tick);
                            }"
                            x-text="shown">{{ $display }}</p>
                    @else
                        <p class="mt-4 font-display text-3xl font-extrabold tracking-tight tabular-nums text-fg">{{ $display }}</p>
                    @endif

                    <p class="mt-1.5 text-sm text-fg">{{ $tile['sub'] }}</p>
                    @if($tile['extra'])
                        <p class="mt-0.5 text-xs text-muted">{{ $tile['extra'] }}</p>
                    @endif

                    @if($sparkPath)
                        <svg class="-mx-5 -mb-5 mt-auto h-14 w-[calc(100%+2.5rem)] pt-4" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
                            <path d="{{ $sparkPath['area'] }}" fill="{{ $tile['color'] }}" fill-opacity="0.1"/>
                            <path d="{{ $sparkPath['line'] }}" fill="none" stroke="{{ $tile['color'] }}" stroke-width="2" vector-effect="non-scaling-stroke"
                                stroke-linejoin="round" stroke-linecap="round" pathLength="1" stroke-dasharray="1" class="animate-draw" style="animation-delay: {{ 200 + $i * 70 }}ms"/>
                        </svg>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Tren harian --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <x-dashboard.panel id="chart-signups" title="Pendaftaran per hari" :subtitle="'Akun baru, ' . $period . ' terakhir (WIB).'" :delay="3">
                <x-chart.line :labels="$dayLabels" :values="$signups" name="Pendaftar" label="Pendaftaran per hari" />
            </x-dashboard.panel>

            <x-dashboard.panel id="chart-links" title="Link dibuat per hari" subtitle="Dipisah menurut paket pembuatnya." :delay="4">
                <x-chart.columns :labels="$dayLabels" label="Link dibuat per hari per paket"
                    :series="collect($tiers)->map(fn ($t, $key) => ['name' => $t[0], 'color' => $t[1], 'values' => $linksByTier[$key]])->values()->all()" />
            </x-dashboard.panel>
        </div>

        {{-- Pendapatan + link teratas --}}
        @if($revenue || $topLinks !== null)
            <div class="grid items-stretch gap-6 lg:grid-cols-2">
                @if($revenue)
                    <x-dashboard.panel id="chart-revenue" title="Pendapatan per bulan" subtitle="Pembayaran lunas, 6 bulan terakhir." :delay="5"
                        :href="auth()->user()->can('payments.view_any') ? route('admin.payments.index') : null" href-label="Transaksi">
                        <x-chart.columns :labels="$revenue['labels']" format="rupiah" label="Pendapatan per bulan"
                            :series="[['name' => 'Pendapatan', 'color' => 'var(--c-s1)', 'values' => $revenue['values']]]" />
                    </x-dashboard.panel>
                @endif

                @if($topLinks !== null)
                    <x-dashboard.panel id="top-links" title="Link paling banyak diklik" subtitle="Sepanjang waktu." :delay="6"
                        :href="route('admin.links.index')" href-label="Semua link">
                        @if($topLinks->isEmpty())
                            <p class="text-sm text-muted">Belum ada link yang diklik.</p>
                        @else
                            @php($clickMax = max(1, (int) $topLinks->max('clicks_total')))
                            <table class="w-full text-sm">
                                <thead class="sr-only"><tr><th scope="col">Peringkat</th><th scope="col">Link</th><th scope="col">Klik</th></tr></thead>
                                <tbody>
                                    @foreach($topLinks as $link)
                                        <tr class="group">
                                            <td class="w-8 py-2.5 align-top">
                                                <span @class([
                                                    'grid h-6 w-6 place-items-center rounded-md text-xs font-bold tabular-nums',
                                                    'bg-tool text-ink' => $loop->first,
                                                    'bg-sunken text-muted' => ! $loop->first,
                                                ])>{{ $loop->iteration }}</span>
                                            </td>
                                            <td class="py-2.5 pr-3">
                                                <span class="font-medium text-fg">/s/{{ $link->code }}</span>
                                                @if($link->disabled_at)<span class="badge ml-1 bg-sunken text-danger">nonaktif</span>@endif
                                                <span class="block truncate text-xs text-muted">{{ $link->target_host }}</span>
                                                <span class="mt-1.5 block h-1 rounded-full bg-sunken" aria-hidden="true">
                                                    <span class="block h-full origin-left rounded-full bg-series-1 animate-grow" style="width: {{ max(2, $link->clicks_total / $clickMax * 100) }}%; animation-delay: {{ 300 + $loop->index * 60 }}ms"></span>
                                                </span>
                                            </td>
                                            <td class="py-2.5 text-right align-top tabular-nums text-fg">{{ $num($link->clicks_total) }} <span class="text-xs text-muted">klik</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </x-dashboard.panel>
                @endif
            </div>
        @endif

        {{-- Domain teratas + aktivitas admin --}}
        @if($topHosts !== null || $audit !== null)
            <div class="grid items-stretch gap-6 lg:grid-cols-2">
                @if($topHosts !== null)
                    <x-dashboard.panel id="top-hosts" title="Domain tujuan teratas" :delay="7"
                        :subtitle="'Menurut jumlah link baru dalam ' . $period . '. Lonjakan dari satu domain bisa jadi tanda spam.'">
                        @if(empty($topHosts))
                            <p class="text-sm text-muted">Belum ada link baru di periode ini.</p>
                        @else
                            @php($hostMax = max(array_column($topHosts, 'links')))
                            <ul class="space-y-3">
                                @foreach($topHosts as $h)
                                    <li>
                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="min-w-0 truncate font-medium text-fg">{{ $h['host'] }}</span>
                                            <span class="shrink-0 tabular-nums text-muted">{{ $num($h['links']) }} link, {{ $num($h['clicks']) }} klik</span>
                                        </div>
                                        <div class="mt-1.5 h-2 rounded-full bg-sunken" aria-hidden="true">
                                            <div class="h-full origin-left rounded-full bg-series-2 animate-grow" style="width: {{ max(2, $h['links'] / $hostMax * 100) }}%; animation-delay: {{ 300 + $loop->index * 60 }}ms"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-dashboard.panel>
                @endif

                @if($audit !== null)
                    <x-dashboard.panel id="recent-audit" title="Aktivitas admin terbaru" :delay="8"
                        :href="route('admin.audit-logs.index')" href-label="Audit log">
                        @if($audit->isEmpty())
                            <p class="text-sm text-muted">Belum ada tindakan admin.</p>
                        @else
                            <ol class="relative space-y-4 border-l border-line pl-5">
                                @foreach($audit as $log)
                                    <li class="relative animate-rise" style="animation-delay: {{ 400 + $loop->index * 50 }}ms">
                                        <span class="absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-surface bg-fg" aria-hidden="true"></span>
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                            <span class="text-sm font-medium text-fg">{{ $actionLabel($log->action) }}</span>
                                            <time class="text-xs text-muted" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->setTimezone(config('toolkits.timezone'))->locale('id')->translatedFormat('j M Y, H:i') }} WIB">{{ $log->created_at->locale('id')->diffForHumans() }}</time>
                                        </div>
                                        <p class="mt-0.5 truncate text-sm text-muted">
                                            oleh {{ $log->admin?->name ?? 'Sistem' }}@if($log->reason), alasan: {{ $log->reason }}@endif
                                        </p>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-dashboard.panel>
                @endif
            </div>
        @endif
    </div>
</div>
