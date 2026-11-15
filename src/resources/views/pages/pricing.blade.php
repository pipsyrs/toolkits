@php
    // Kontrak: $plans = Plan::listed(); field slug, name, price_monthly, price_yearly, is_purchasable. $highlights = slug => daftar fitur dari matriks paket.
    $plans = collect($plans ?? []);
    $rp = fn (int $v) => 'Rp' . number_format($v, 0, ',', '.');
    $hasPaid = $plans->contains(fn ($p) => $p->is_purchasable && (int) $p->price_monthly > 0);
@endphp

<x-layouts::app title="Harga">
    <div class="mx-auto max-w-5xl" x-data="{ yearly: false }">
        <header class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="font-display text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl dark:text-white">Pilih paket ToolKits</h1>
                <p class="mt-3 max-w-[52ch] text-slate-600 dark:text-slate-300">Mulai gratis tanpa daftar. Pro dibayar per periode, tanpa tagihan otomatis.</p>
            </div>

            @if($hasPaid)
                <fieldset class="inline-flex shrink-0 self-start rounded-full border border-slate-200 bg-white p-1 text-sm font-medium sm:self-auto dark:border-slate-700 dark:bg-slate-900">
                    <legend class="sr-only">Periode tagihan</legend>
                    @foreach([['Bulanan', false], ['Tahunan', true]] as [$label, $value])
                        <label class="cursor-pointer rounded-full px-4 py-1.5 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500"
                            :class="yearly === @js($value) ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'">
                            <input type="radio" name="period" class="sr-only" :value="@js($value)" :checked="yearly === @js($value)" @change="yearly = @js($value)">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>
            @endif
        </header>

        @if($plans->isEmpty())
            <p class="mt-10 rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-400">Paket sedang disiapkan. Alat gratis tetap bisa dipakai.</p>
        @else
            {{-- Satu lembar, dibagi per paket; paket berbayar mengambil ruang lebih lebar --}}
            <div class="mt-10 flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white lg:flex-row dark:border-slate-800 dark:bg-slate-900">
                @foreach($plans as $plan)
                    @php
                        $monthly = (int) $plan->price_monthly;
                        $yearly = (int) $plan->price_yearly;
                        $paid = $plan->is_purchasable && $monthly > 0;
                        $saving = $paid && $yearly > 0 ? max(0, $monthly * 12 - $yearly) : 0;
                        $billingUrl = route('dashboard.billing', ['plan' => $plan->slug]);
                        $cta = match (true) {
                            ! $paid => auth()->check() ? ['Buka dashboard', route('dashboard')] : ['Daftar gratis', route('register')],
                            auth()->check() => ['Pilih ' . $plan->name, $billingUrl . '&period=monthly'],
                            default => ['Daftar untuk ' . $plan->name, route('register')],
                        };
                    @endphp
                    <section aria-labelledby="plan-{{ $plan->slug }}"
                        @class([
                            'flex flex-col p-7 sm:p-10',
                            'lg:flex-[1.35] bg-brand-950 text-white dark:bg-brand-600' => $paid,
                            'lg:flex-1' => ! $paid,
                            'border-t border-slate-200 lg:border-l lg:border-t-0 dark:border-slate-800' => ! $loop->first && ! $paid,
                        ])>
                        <h2 id="plan-{{ $plan->slug }}" class="text-lg font-semibold {{ $paid ? 'text-brand-200 dark:text-brand-100' : 'text-slate-600 dark:text-slate-300' }}">{{ $plan->name }}</h2>

                        <p class="mt-4 flex flex-wrap items-baseline gap-x-2">
                            @if($paid)
                                <span class="font-display text-5xl font-extrabold tracking-tight tabular-nums sm:text-6xl"
                                    x-text="yearly ? @js($rp($yearly)) : @js($rp($monthly))">{{ $rp($monthly) }}</span>
                                <span class="text-brand-200 dark:text-brand-100" x-text="yearly ? 'per tahun' : 'per bulan'">per bulan</span>
                            @else
                                <span class="font-display text-5xl font-extrabold tracking-tight tabular-nums text-slate-900 sm:text-6xl dark:text-white">{{ $rp(0) }}</span>
                                <span class="text-slate-500 dark:text-slate-400">selamanya</span>
                            @endif
                        </p>

                        <p class="mt-2 min-h-5 text-sm {{ $paid ? 'text-brand-200 dark:text-brand-100' : 'text-slate-500 dark:text-slate-400' }}">
                            @if($paid && $yearly > 0)
                                <span x-show="!yearly">atau {{ $rp($yearly) }} per tahun{{ $saving > 0 ? ', hemat ' . $rp($saving) : '' }}</span>
                                <span x-show="yearly" x-cloak>setara {{ $rp(intdiv($yearly, 12)) }} per bulan{{ $saving > 0 ? ', hemat ' . $rp($saving) : '' }}</span>
                            @elseif(! $paid)
                                Tidak perlu kartu kredit.
                            @endif
                        </p>

                        @php($lines = $highlights[$plan->slug] ?? [])
                        @if($lines !== [])
                            <ul class="mt-8 divide-y {{ $paid ? 'divide-white/10 border-y border-white/10' : 'divide-slate-200 border-y border-slate-200 dark:divide-slate-800 dark:border-slate-800' }}"
                                aria-label="Fitur {{ $plan->name }}">
                                @foreach($lines as $line)
                                    <li class="flex items-start gap-3 py-3.5 {{ $paid ? '' : 'text-slate-700 dark:text-slate-200' }}">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $paid ? 'text-brand-300 dark:text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <span class="min-w-0 break-words">{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="flex-1"></div>

                        <a href="{{ $cta[1] }}"
                            @if($paid && auth()->check()) :href="@js($billingUrl) + '&period=' + (yearly ? 'yearly' : 'monthly')" @endif
                            @class([
                                'mt-8 w-full sm:w-auto sm:self-start',
                                'btn bg-white text-brand-950 hover:bg-brand-50 focus-visible:ring-white focus-visible:ring-offset-brand-950 dark:focus-visible:ring-offset-brand-600' => $paid,
                                'btn-secondary' => ! $paid,
                            ])>{{ $cta[0] }}</a>
                    </section>
                @endforeach
            </div>
        @endif

        <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">"Tak terbatas" mengikuti kebijakan pemakaian wajar. Harga dalam Rupiah.</p>
    </div>
</x-layouts::app>
