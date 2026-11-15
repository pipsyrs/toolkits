@props([
    'labels' => [],          // list<string> x labels
    'values' => [],          // list<int>
    'name' => 'Nilai',       // series name (table header)
    'color' => 'var(--c-s1)',
    'format' => 'number',
    'label' => 'Grafik',
])

@php
    use App\Support\ChartAxis;

    $n = count($values);
    $max = ChartAxis::niceMax($n ? max($values) : 0);
    $ticks = [0, $max / 2, $max];
    $xTicks = ChartAxis::ticks($n);
    $x = fn ($i) => $n > 1 ? $i / ($n - 1) * 100 : 50;
    $y = fn ($v) => 100 - $v / $max * 100;
    $line = collect($values)->map(fn ($v, $i) => ($i ? 'L' : 'M').round($x($i), 3).' '.round($y($v), 3))->implode(' ');
    $area = $n ? $line.' L100 100 L0 100 Z' : '';
    $points = collect($values)->map(fn ($v, $i) => [
        'label' => $labels[$i] ?? '',
        'value' => ChartAxis::format($v, $format),
        'x' => round($x($i), 3),
        'y' => round($y($v), 3),
    ])->all();
    $last = $points[$n - 1] ?? null;
@endphp

<figure {{ $attributes->class('min-w-0') }}
    x-data="{ i: null, n: {{ $n }}, d: @js($points),
        pick(e) { const r = this.$refs.plot.getBoundingClientRect(); this.i = Math.min(this.n - 1, Math.max(0, Math.round((e.clientX - r.left) / r.width * (this.n - 1)))) },
        step(k) { this.i = this.i === null ? this.n - 1 : Math.min(this.n - 1, Math.max(0, this.i + k)) } }">
    <div class="relative pl-12">
        <div x-ref="plot" tabindex="0" role="group" aria-label="{{ $label }}. Gunakan panah kiri dan kanan untuk membaca nilai."
            class="relative h-44 focus-visible:outline-offset-4"
            @pointermove="pick($event)" @pointerleave="i = null" @focus="step(0)" @blur="i = null"
            @keydown.arrow-right.prevent="step(1)" @keydown.arrow-left.prevent="step(-1)">
            {{-- Sumbu Y + gridline --}}
            @foreach($ticks as $t)
                <div class="pointer-events-none absolute inset-x-0 border-t border-line" style="bottom: {{ $t / $max * 100 }}%" aria-hidden="true"></div>
                <span class="pointer-events-none absolute -left-12 w-10 translate-y-1/2 text-right text-[11px] tabular-nums text-muted" style="bottom: {{ $t / $max * 100 }}%" aria-hidden="true">{{ ChartAxis::format($t, $format, true) }}</span>
            @endforeach

            <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <path d="{{ $area }}" fill="{{ $color }}" fill-opacity="0.1"/>
                <path d="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            </svg>

            {{-- Titik akhir --}}
            @if($last)
                <span class="pointer-events-none absolute h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full ring-2 ring-surface" x-show="i === null"
                    style="left: {{ $last['x'] }}%; bottom: {{ 100 - $last['y'] }}%; background: {{ $color }}" aria-hidden="true"></span>
            @endif

            {{-- Crosshair + titik hover --}}
            <template x-if="i !== null">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute inset-y-0 w-px bg-line-strong/40" :style="`left: ${d[i].x}%`"></div>
                    <span class="absolute h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full ring-2 ring-surface" :style="`left: ${d[i].x}%; bottom: ${100 - d[i].y}%; background: {{ $color }}`"></span>
                </div>
            </template>

            <div x-show="i !== null" x-cloak class="pointer-events-none absolute bottom-full z-10 mb-2 rounded-md border border-line bg-surface px-3 py-2 text-xs shadow-sm"
                :style="`left: ${d[i]?.x}%; transform: translateX(${i < n * 0.2 ? '-10%' : (i > n * 0.8 ? '-90%' : '-50%')})`">
                <p class="whitespace-nowrap text-muted" x-text="d[i]?.label"></p>
                <p class="mt-0.5 text-sm font-semibold tabular-nums text-fg" x-text="d[i]?.value"></p>
            </div>
        </div>

        <div class="relative mt-2 h-4 text-[11px] text-muted" aria-hidden="true">
            @foreach($xTicks as $k => $t)
                {{-- Layar sempit: hanya awal, tengah, akhir --}}
                <span class="absolute whitespace-nowrap {{ $t === 0 ? '' : ($t === $n - 1 ? '-translate-x-full' : '-translate-x-1/2') }} {{ in_array($k, [0, intdiv(count($xTicks) - 1, 2), count($xTicks) - 1], true) ? '' : 'max-sm:hidden' }}" style="left: {{ $x($t) }}%">{{ $labels[$t] }}</span>
            @endforeach
        </div>
    </div>

    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs text-muted hover:text-fg">Lihat tabel</summary>
        <div class="mt-2 max-h-64 overflow-auto rounded-md border border-line">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-sunken text-muted">
                    <tr><th scope="col" class="px-3 py-2 font-medium">Tanggal</th><th scope="col" class="px-3 py-2 text-right font-medium">{{ $name }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($points as $p)
                        <tr><th scope="row" class="px-3 py-1.5 font-normal">{{ $p['label'] }}</th><td class="px-3 py-1.5 text-right tabular-nums">{{ $p['value'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
