@props([
    'labels' => [],          // list<string> x labels
    'series' => [],          // list<array{name: string, color: string, values: list<int>}>, stacked bottom-up
    'format' => 'number',    // number|rupiah
    'label' => 'Grafik',     // accessible name
])

@php
    use App\Support\ChartAxis;

    $n = count($labels);
    $totals = array_map(fn ($i) => array_sum(array_map(fn ($s) => (int) ($s['values'][$i] ?? 0), $series)), range(0, max(0, $n - 1)));
    $max = ChartAxis::niceMax($n ? max($totals) : 0);
    $ticks = [0, $max / 2, $max];
    $xTicks = ChartAxis::ticks($n);
    $multi = count($series) > 1;

    // Tooltip/table data: server-formatted strings, rendered with x-text.
    $points = [];
    foreach (range(0, max(0, $n - 1)) as $i) {
        $points[] = [
            'label' => $labels[$i] ?? '',
            'total' => ChartAxis::format($totals[$i] ?? 0, $format),
            'rows' => array_map(fn ($s) => ['name' => $s['name'], 'color' => $s['color'], 'value' => ChartAxis::format((int) ($s['values'][$i] ?? 0), $format)], $series),
        ];
    }
@endphp

<figure {{ $attributes->class('min-w-0') }}
    x-data="{ i: null, n: {{ $n }}, d: @js($points),
        pick(e) { const r = this.$refs.plot.getBoundingClientRect(); this.i = Math.min(this.n - 1, Math.max(0, Math.floor((e.clientX - r.left) / r.width * this.n))) },
        step(k) { this.i = this.i === null ? this.n - 1 : Math.min(this.n - 1, Math.max(0, this.i + k)) } }">
    @if($multi)
        <ul class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Legenda">
            @foreach($series as $s)
                <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</li>
            @endforeach
        </ul>
    @endif

    <div class="relative pl-12">
        {{-- Plot --}}
        <div x-ref="plot" tabindex="0" role="group" aria-label="{{ $label }}. Gunakan panah kiri dan kanan untuk membaca nilai."
            class="relative flex h-44 items-end focus-visible:outline-offset-4"
            @pointermove="pick($event)" @pointerleave="i = null" @focus="step(0)" @blur="i = null"
            @keydown.arrow-right.prevent="step(1)" @keydown.arrow-left.prevent="step(-1)">
            {{-- Sumbu Y + gridline --}}
            @foreach($ticks as $t)
                <div class="pointer-events-none absolute inset-x-0 border-t border-line" style="bottom: {{ $t / $max * 100 }}%" aria-hidden="true"></div>
                <span class="pointer-events-none absolute -left-12 w-10 translate-y-1/2 text-right text-[11px] tabular-nums text-muted" style="bottom: {{ $t / $max * 100 }}%" aria-hidden="true">{{ ChartAxis::format($t, $format, true) }}</span>
            @endforeach

            @foreach(range(0, max(0, $n - 1)) as $c)
                @php
                    $top = null;
                    foreach ($series as $k => $s) { if ((int) ($s['values'][$c] ?? 0) > 0) { $top = $k; } }
                @endphp
                <div class="relative flex h-full flex-1 items-end justify-center px-px">
                    <div class="flex h-full w-full max-w-6 flex-col-reverse transition-opacity" :class="i !== null && i !== {{ $c }} && 'opacity-50'">
                        @foreach($series as $k => $s)
                            @php($v = (int) ($s['values'][$c] ?? 0))
                            @if($v > 0)
                                <div class="box-border shrink-0 {{ $k !== array_key_first($series) ? 'border-b-2 border-surface' : '' }} {{ $k === $top ? 'rounded-t' : '' }}"
                                    style="height: {{ $v / $max * 100 }}%; background: {{ $s['color'] }}"></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Tooltip --}}
            <div x-show="i !== null" x-cloak class="pointer-events-none absolute bottom-full z-10 mb-2 min-w-36 rounded-md border border-line bg-surface px-3 py-2 text-xs shadow-sm"
                :style="`left: ${(i + 0.5) / n * 100}%; transform: translateX(${i < n * 0.2 ? '-10%' : (i > n * 0.8 ? '-90%' : '-50%')})`">
                <p class="whitespace-nowrap text-muted" x-text="d[i]?.label"></p>
                @if($multi)
                    <template x-for="r in d[i]?.rows ?? []" :key="r.name">
                        <p class="mt-1 flex items-center gap-2">
                            <span class="h-0.5 w-3 rounded" :style="`background: ${r.color}`"></span>
                            <span class="font-semibold tabular-nums text-fg" x-text="r.value"></span>
                            <span class="text-muted" x-text="r.name"></span>
                        </p>
                    </template>
                    <p class="mt-1.5 border-t border-line pt-1.5"><span class="font-semibold tabular-nums text-fg" x-text="d[i]?.total"></span> <span class="text-muted">total</span></p>
                @else
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-fg" x-text="d[i]?.total"></p>
                @endif
            </div>
        </div>

        {{-- Sumbu X --}}
        <div class="relative mt-2 h-4 text-[11px] text-muted" aria-hidden="true">
            @foreach($xTicks as $k => $t)
                {{-- Layar sempit: hanya awal, tengah, akhir --}}
                <span class="absolute whitespace-nowrap {{ $t === 0 ? '' : ($t === $n - 1 ? '-translate-x-full' : '-translate-x-1/2') }} {{ in_array($k, [0, intdiv(count($xTicks) - 1, 2), count($xTicks) - 1], true) ? '' : 'max-sm:hidden' }}"
                    style="left: {{ $t === 0 ? 0 : ($t === $n - 1 ? 100 : ($t + 0.5) / $n * 100) }}%">{{ $labels[$t] }}</span>
            @endforeach
        </div>
    </div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs text-muted hover:text-fg">Lihat tabel</summary>
        <div class="mt-2 max-h-64 overflow-auto rounded-md border border-line">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-sunken text-muted">
                    <tr>
                        <th scope="col" class="px-3 py-2 font-medium">Periode</th>
                        @foreach($series as $s)<th scope="col" class="px-3 py-2 text-right font-medium">{{ $s['name'] }}</th>@endforeach
                        @if($multi)<th scope="col" class="px-3 py-2 text-right font-medium">Total</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($points as $p)
                        <tr>
                            <th scope="row" class="px-3 py-1.5 font-normal">{{ $p['label'] }}</th>
                            @foreach($p['rows'] as $r)<td class="px-3 py-1.5 text-right tabular-nums">{{ $r['value'] }}</td>@endforeach
                            @if($multi)<td class="px-3 py-1.5 text-right font-medium tabular-nums">{{ $p['total'] }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
