@php
    use App\Enums\PaymentStatus;
    use App\Services\Tier\PlanLimits;
    use Illuminate\Support\Carbon;

    // Kontrak: $tier, $planUntil, $plans, $payments, $pendingPayment, $pollPending, $canPurchase, $billingEnabled, $upgradePreview, $selectedPeriod,
    // $methods (code => logo_path), $channels (code => PaymentChannel), $activePayment, $activePayable, $qrImage, $canSimulate.
    $tz = 'Asia/Jakarta';
    $rp = fn ($v) => 'Rp' . number_format((int) $v, 0, ',', '.');
    $val = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;
    $wib = fn ($d, $f = 'd M Y, H:i') => $d ? Carbon::parse($d)->setTimezone($tz)->translatedFormat($f) . ($f === 'd M Y' ? '' : ' WIB') : null;

    $planLabel = fn ($slug) => app(PlanLimits::class)->label((string) $slug);
    $until = $planUntil ? Carbon::parse($planUntil) : null;
    $active = $tier->isPaid() && $until?->isFuture();
    $remainingSeconds = $active ? max(0, $until->getTimestamp() - now()->getTimestamp()) : 0;
    $daysLeft = (int) ceil($remainingSeconds / 86400);
    $billingEnabled = (bool) ($billingEnabled ?? false);

    $planList = collect($plans ?? []);
    $findPlan = fn ($slug) => $planList->get($slug) ?? $planList->first(fn ($p) => data_get($p, 'slug') === $slug);
    $periods = ['monthly' => 'Bulanan', 'yearly' => 'Tahunan'];
    $kindLabels = ['new' => 'Pembelian baru', 'renewal' => 'Perpanjangan', 'upgrade' => 'Upgrade'];

    // Matriks pilihan untuk Alpine (harga dari server, bukan dihitung ulang).
    $matrix = [];
    foreach ($planList->keys() as $slug) {
        $plan = $findPlan($slug);
        if (! $plan) continue;
        $cp = $canPurchase[$slug] ?? true;
        $monthly = (int) data_get($plan, 'price_monthly');
        $yearly = (int) data_get($plan, 'price_yearly');
        $kind = is_array($cp) && isset($cp['kind'])
            ? $val($cp['kind'])
            : ($active ? 'renewal' : 'new');
        $matrix[$slug] = [
            'name' => data_get($plan, 'name') ?: $planLabel($slug),
            'allowed' => is_array($cp) ? (bool) ($cp['allowed'] ?? $cp['ok'] ?? false) : (bool) $cp,
            'reason' => is_array($cp) ? ($cp['reason'] ?? null) : null,
            'kind' => $kind,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'price' => ['monthly' => $rp($monthly), 'yearly' => $rp($yearly)],
            'saving' => $monthly > 0 && $yearly > 0 ? max(0, $monthly * 12 - $yearly) : 0,
            'until' => [
                'monthly' => $wib(data_get($upgradePreview ?? [], "$slug.monthly"), 'd M Y'),
                'yearly' => $wib(data_get($upgradePreview ?? [], "$slug.yearly"), 'd M Y'),
            ],
        ];
    }
    foreach ($matrix as $slug => $m) {
        $matrix[$slug]['savingText'] = $m['saving'] > 0 ? 'Hemat ' . $rp($m['saving']) : null;
    }
    $maxSavingPct = collect($matrix)->map(fn ($m) => $m['monthly'] > 0 ? (int) floor($m['saving'] / ($m['monthly'] * 12) * 100) : 0)->max() ?? 0;

    // Pilihan awal: ?plan & ?period dari halaman harga, lalu plan aktif, lalu yang bisa dibeli.
    $qPlan = request()->query('plan');
    $qPeriod = request()->query('period');
    $initialPlan = collect([$qPlan, $active ? $tier->value : null, ...array_keys($matrix)])
        ->first(fn ($s) => is_string($s) && ($matrix[$s]['allowed'] ?? false)) ?? array_key_first($matrix);
    $initialPeriod = in_array($qPeriod, ['monthly', 'yearly'], true) ? $qPeriod : ($val($selectedPeriod ?? null) ?: 'monthly');

    // Invoice dari halaman kembali (?payment).
    $returned = $pendingPayment ?? null;
    $returnedStatus = $returned ? $val($returned->status) : null;
    $returnedExpired = $returned && $returnedStatus === 'pending' && $returned->expires_at && Carbon::parse($returned->expires_at)->isPast();
    $polling = $returnedStatus === 'pending' && ! $returnedExpired && ($pollPending ?? true);

    // Pembayaran in-app yang masih bisa dibayar (invoice lama hanya ditampilkan statusnya).
    $paymentRows = $payments ? collect(method_exists($payments, 'items') ? $payments->items() : $payments) : collect();
    $isPayable = fn ($p) => $val($p->status) === 'pending' && $p->isInApp() && $p->expires_at && Carbon::parse($p->expires_at)->isFuture();
    $isOpen = fn ($p) => $val($p->status) === 'pending' && (! $p->expires_at || Carbon::parse($p->expires_at)->isFuture());
    $openPayment = $paymentRows->first(fn ($p) => $isPayable($p));

    $billingError = $errors->first('billing') ?: session('errors')?->getBag('default')->first('billing');

    $statusBadge = [
        'paid' => 'bg-ok/10 text-ok',
        'pending' => 'bg-warn/10 text-warn',
        'expired' => 'bg-fg/6 text-fg',
        'failed' => 'bg-danger/10 text-danger',
        'canceled' => 'bg-fg/6 text-fg',
    ];
@endphp

<div class="mx-auto max-w-4xl space-y-6">

    {{-- Hasil kembali dari halaman pembayaran --}}
    @if($returned)
        <div aria-live="polite" wire:key="return-{{ $returned->id }}-{{ $returnedExpired ? 'expired' : $returnedStatus }}">
            @if($returnedStatus === 'paid')
                <div class="flex items-start gap-3 rounded-2xl border border-ok/30 bg-ok/10 px-5 py-4 text-ok" role="status">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-ok" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="text-sm">
                        <p class="font-semibold">Pembayaran berhasil</p>
                        <p class="mt-0.5">
                            Order <span class="font-mono">{{ $returned->order_id }}</span> lunas.
                            @if($active) Paket {{ $tier->label() }} aktif sampai {{ $wib($until) }}. @endif
                        </p>
                    </div>
                </div>
            @elseif($returnedStatus === 'pending' && ! $returnedExpired)
                {{-- Poll ringan hanya selama $pollPending (≤ 10 menit sejak checkout) --}}
                <div class="rounded-2xl border border-line bg-tool/15 px-5 py-4 text-link" role="status"
                    @if($polling) wire:poll.5s="checkStatus" @endif>
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-link {{ $polling ? 'motion-safe:animate-spin' : '' }}" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold">Pembayaran sedang diverifikasi</p>
                            @if($polling)
                                <p class="mt-0.5">Kami menunggu konfirmasi dari penyedia pembayaran untuk order <span class="font-mono">{{ $returned->order_id }}</span>. Halaman ini diperbarui otomatis — biasanya kurang dari satu menit.</p>
                            @else
                                <p class="mt-0.5">Belum ada konfirmasi untuk order <span class="font-mono">{{ $returned->order_id }}</span>. Jika sudah membayar, cek status sebentar lagi.</p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" class="btn-ghost btn-sm" wire:click="checkStatus" wire:loading.attr="disabled" wire:target="checkStatus">
                                    <span wire:loading.remove wire:target="checkStatus">Cek status</span>
                                    <span wire:loading wire:target="checkStatus">Memeriksa…</span>
                                </button>
                                @if($returned->expires_at)
                                    <span class="text-xs text-link">Invoice kedaluwarsa {{ $wib($returned->expires_at) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-danger" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <div class="text-sm">
                        <p class="font-semibold">{{ ['failed' => 'Pembayaran gagal', 'canceled' => 'Pembayaran dibatalkan'][$returnedStatus] ?? 'Invoice kedaluwarsa' }}</p>
                        <p class="mt-0.5">Order <span class="font-mono">{{ $returned->order_id }}</span> tidak dibayar. Paket Anda tidak berubah — silakan buat pembayaran baru di bawah.</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if($billingError)
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $billingError }}</div>
    @endif

    {{-- Status paket --}}
    <section class="glass p-6" aria-labelledby="current-plan">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted">Paket saat ini</p>
                <p id="current-plan" class="mt-1 text-2xl font-bold text-fg">{{ $tier->label() }}</p>
                @if($active)
                    <p class="mt-1 text-sm text-muted">Berlaku sampai <span class="font-medium text-fg">{{ $wib($until) }}</span></p>
                @elseif($tier->isPaid() && $until)
                    <p class="mt-1 text-sm text-danger">Berakhir {{ $wib($until) }}</p>
                @else
                    <p class="mt-1 text-sm text-muted">Tingkatkan untuk batas lebih besar, tanpa iklan, dan antrean prioritas.</p>
                @endif
            </div>
            @if($active)
                <div class="text-right">
                    <p class="text-3xl font-bold tabular-nums {{ $daysLeft <= 3 ? 'text-warn' : 'text-fg' }}">{{ $daysLeft }}</p>
                    <p class="text-xs text-muted">hari tersisa</p>
                </div>
            @endif
        </div>
        @if($active && $daysLeft <= 3)
            <p class="mt-4 rounded-lg bg-warn/10 px-3 py-2 text-sm text-warn">Masa aktif hampir habis. Perpanjang sekarang — sisa hari tidak hilang, periode baru dimulai setelah tanggal berakhir.</p>
        @endif
    </section>

    {{-- Pembayaran pending yang masih bisa dibayar --}}
    @if($openPayment && ! $checkoutOpen)
        <div class="flex flex-col gap-3 rounded-2xl border border-warn/30 bg-warn/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-warn">
                <p class="font-semibold">Ada pembayaran yang belum selesai</p>
                <p class="mt-0.5">
                    {{ $matrix[$val($openPayment->plan)]['name'] ?? $val($openPayment->plan) }} · {{ $periods[$val($openPayment->period)] ?? $val($openPayment->period) }} · {{ $rp($openPayment->amount) }}
                    · {{ $openPayment->channel()?->fullLabel() }}
                    <span class="block text-xs text-warn sm:inline">· berlaku s.d. {{ $wib($openPayment->expires_at) }}</span>
                </p>
            </div>
            <button type="button" class="btn-secondary btn-sm shrink-0" wire:click="resume('{{ $openPayment->id }}')" wire:loading.attr="disabled" wire:target="resume">Lanjutkan bayar</button>
        </div>
    @endif

    {{-- Pilih paket (focal point) --}}
    <section class="glass p-6" aria-labelledby="choose-plan" wire:key="chooser-{{ md5($tier->value . '|' . $until) }}">
        @if(empty($matrix))
            <h2 id="choose-plan" class="font-semibold text-fg">Pilih paket</h2>
            <p class="mt-3 text-sm text-muted">Paket berbayar sedang disiapkan.</p>
        @else
            <form x-data="{ plan: @js($initialPlan), period: @js($initialPeriod), m: @js($matrix) }"
                @submit.prevent="if (m[plan]?.allowed) $wire.startOrder(plan, period)">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="choose-plan" class="font-semibold text-fg">{{ $active ? 'Perpanjang paket' : 'Pilih paket' }}</h2>
                        <p class="mt-0.5 text-sm text-muted">Bayar sekali per periode lewat QRIS atau Virtual Account, tanpa tagihan otomatis.</p>
                    </div>
                    <fieldset class="inline-flex shrink-0 self-start rounded-full border border-line bg-fg/6 p-1 text-sm">
                        <legend class="sr-only">Periode pembayaran</legend>
                        @foreach($periods as $key => $label)
                            <label class="relative cursor-pointer rounded-full px-4 py-1.5 font-medium transition motion-reduce:transition-none has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-fg"
                                :class="period === @js($key) ? 'bg-surface text-fg shadow-sm' : 'text-muted hover:text-fg'">
                                <input type="radio" name="period" value="{{ $key }}" x-model="period" class="sr-only">
                                {{ $label }}
                                @if($key === 'yearly' && $maxSavingPct > 0)
                                    <span class="ml-1 rounded-full bg-ok/10 px-1.5 py-0.5 text-[11px] font-semibold text-ok">-{{ $maxSavingPct }}%</span>
                                @endif
                            </label>
                        @endforeach
                    </fieldset>
                </div>

                <div class="mt-5 grid gap-4" role="radiogroup" aria-label="Paket">
                    @foreach($matrix as $slug => $m)
                        <label class="group relative flex flex-col rounded-xl border p-5 transition motion-reduce:transition-none has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-fg
                            {{ $m['allowed'] ? 'cursor-pointer' : 'cursor-not-allowed opacity-70' }}"
                            :class="plan === @js($slug) ? 'border-line-strong bg-tool/15 ring-1 ring-fg' : 'border-line hover:border-line-strong'">
                            <input type="radio" name="plan" value="{{ $slug }}" x-model="plan" class="sr-only" @disabled(! $m['allowed'])
                                @if($m['reason']) aria-describedby="reason-{{ $slug }}" @endif>
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-fg">{{ $m['name'] }}</span>
                                @if($active && $tier->value === $slug)
                                    <span class="badge bg-tool/15 text-link">Paket Anda</span>
                                @endif
                            </span>
                            <span class="mt-3 flex items-baseline gap-1">
                                <span class="text-2xl font-bold tabular-nums text-fg" x-text="m[@js($slug)].price[period]">{{ $m['price']['monthly'] }}</span>
                                <span class="text-sm text-muted" x-text="period === 'yearly' ? '/tahun' : '/bulan'">/bulan</span>
                            </span>
                            <span class="mt-1 min-h-5 text-xs text-muted">
                                <span x-show="period === 'monthly'">atau {{ $m['price']['yearly'] }}/tahun</span>
                                <span x-show="period === 'yearly'" x-cloak class="font-medium text-ok">{{ $m['savingText'] ?? 'Harga tahunan' }}</span>
                            </span>
                            @if(! $m['allowed'])
                                <span id="reason-{{ $slug }}" class="mt-3 flex items-start gap-1.5 text-xs text-muted">
                                    <svg class="mt-px h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    {{ $m['reason'] ?? 'Paket ini belum bisa dibeli.' }}
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>

                {{-- Ringkasan & bayar --}}
                <div class="mt-5 rounded-xl bg-fg/4 p-4 sm:p-5">
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                        <dt class="text-muted">Jenis</dt>
                        <dd class="font-medium text-fg" x-text="(@js($kindLabels))[m[plan]?.kind] ?? '—'">{{ $kindLabels[$matrix[$initialPlan]['kind'] ?? 'new'] ?? '—' }}</dd>
                        <dt class="text-muted">Aktif sampai</dt>
                        <dd class="font-medium text-fg" x-text="m[plan]?.until[period] ? '± ' + m[plan].until[period] : 'Dihitung saat pembayaran dikonfirmasi'">—</dd>
                    </dl>

                    @if($active)
                        <p class="mt-3 text-xs text-muted" x-show="m[plan]?.kind === 'renewal'" x-cloak>
                            Periode baru dimulai setelah masa aktif sekarang berakhir ({{ $wib($until, 'd M Y') }}).
                        </p>
                    @endif

                    <div class="mt-4 flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted">
                            Total
                            <span class="ml-1 text-xl font-bold tabular-nums text-fg" x-text="m[plan]?.price[period]">{{ $matrix[$initialPlan]['price'][$initialPeriod] ?? '' }}</span>
                        </p>
                        @if($billingEnabled)
                            <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="!m[plan]?.allowed" wire:loading.attr="disabled" wire:target="startOrder">
                                <svg wire:loading wire:target="startOrder" class="h-4 w-4 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                                Lanjut ke pembayaran
                            </button>
                        @else
                            <button type="button" class="btn-primary w-full sm:w-auto" disabled>Lanjut ke pembayaran</button>
                        @endif
                    </div>
                    @if(! $billingEnabled)
                        <p class="mt-3 text-xs text-warn" role="status">Pembayaran belum tersedia. Silakan coba lagi nanti.</p>
                    @else
                        <p class="mt-3 text-xs text-muted">Pilih QRIS atau Virtual Account di langkah berikutnya. Paket aktif otomatis setelah pembayaran terkonfirmasi.</p>
                    @endif
                    @error('plan') <p class="field-error">{{ $message }}</p> @enderror
                    @error('period') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </form>
        @endif
    </section>

    {{-- Riwayat pembayaran --}}
    <section class="glass" aria-labelledby="payment-history">
        <div class="flex items-center justify-between gap-3 px-5 pt-5 sm:px-6">
            <h2 id="payment-history" class="font-semibold text-fg">Riwayat pembayaran</h2>
        </div>
        @if($paymentRows->isNotEmpty())
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-y border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Tanggal</th>
                            <th scope="col" class="px-5 py-3 font-medium">Paket</th>
                            <th scope="col" class="px-5 py-3 font-medium">Jumlah</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 font-medium">Dibayar</th>
                            <th scope="col" class="px-5 py-3 sm:px-6"><span class="sr-only">Invoice</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($paymentRows as $p)
                            @php
                                $st = $val($p->status);
                                $stEnum = $p->status instanceof PaymentStatus ? $p->status : PaymentStatus::tryFrom((string) $st);
                                $payable = $isPayable($p);
                                $shownStatus = $st === 'pending' && ! $isOpen($p) ? 'expired' : $st;
                            @endphp
                            <tr wire:key="pay-{{ $p->id }}">
                                <td class="whitespace-nowrap px-5 py-3 sm:px-6">
                                    <span class="block text-fg">{{ $wib($p->created_at, 'd M Y') }}</span>
                                    <span class="block font-mono text-xs text-muted">{{ $p->order_id }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="block font-medium text-fg">{{ $matrix[$val($p->plan)]['name'] ?? $planLabel($val($p->plan)) }}</span>
                                    <span class="block text-xs text-muted">{{ $periods[$val($p->period)] ?? $val($p->period) }}@if($p->period_end) · s.d. {{ $wib($p->period_end, 'd M Y') }}@endif</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 tabular-nums text-fg">{{ $rp($p->amount) }}</td>
                                <td class="px-5 py-3">
                                    <span class="badge {{ $statusBadge[$shownStatus] ?? $statusBadge['expired'] }}">
                                        {{ $shownStatus === 'expired' ? PaymentStatus::Expired->label() : ($stEnum?->label() ?? ucfirst((string) $st)) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-muted">{{ $wib($p->paid_at) ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right sm:px-6">
                                    @if($payable)
                                        <button type="button" class="btn-secondary btn-sm" wire:click="resume('{{ $p->id }}')" wire:loading.attr="disabled" wire:target="resume">Bayar<span class="sr-only"> {{ $p->order_id }}</span></button>
                                    @elseif($st === 'paid' && $p->invoice_url)
                                        <a href="{{ $p->invoice_url }}" target="_blank" rel="noopener noreferrer" class="link text-xs">Invoice<span class="sr-only"> {{ $p->order_id }} (tab baru)</span></a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($payments, 'links'))
                <div class="px-5 py-4 sm:px-6">{{ $payments->links() }}</div>
            @endif
        @else
            <div class="m-5 rounded-lg border border-dashed border-line px-4 py-8 text-center text-sm text-muted sm:m-6">
                Belum ada pembayaran. Riwayat pembayaran akan muncul di sini.
            </div>
        @endif
    </section>
    {{-- Modal pembayaran in-app (QRIS / Virtual Account) --}}
    @if($checkoutOpen)
        @php
            $order = $matrix[$this->plan] ?? null;
            $orderPeriod = $this->period === 'yearly' ? 'yearly' : 'monthly';
            $orderAmount = $activePayment?->amount ?? ($order[$orderPeriod] ?? 0);
            $activeStatus = $activePayment ? $val($activePayment->status) : null;
            $activeChannel = $activePayment?->channel();
            $activeLogo = $activePayment ? \App\Models\PaymentMethod::logoUrl($methods[$activePayment->payment_channel] ?? null) : null;
            $activeCard = $activeChannel?->kind === \App\Enums\PaymentChannelKind::Card;
            $display = $activePayment?->payDisplay();
            $secondsLeft = $activePayable ? max(0, $activePayment->expires_at->getTimestamp() - now()->getTimestamp()) : 0;
            $tileClass = 'flex cursor-pointer items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3 text-sm transition-colors hover:border-line-strong has-[:checked]:border-line-strong has-[:checked]:bg-tool/15 has-[:checked]:ring-1 has-[:checked]:ring-fg has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-fg has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60';
            // Active methods grouped by kind, in admin order.
            $methodGroups = collect($methods)
                ->map(fn ($logoPath, $code) => ['code' => $code, 'channel' => $channels->get($code), 'logo' => \App\Models\PaymentMethod::logoUrl($logoPath)])
                ->filter(fn ($m) => $m['channel'] !== null)
                ->groupBy(fn ($m) => $m['channel']->kind->value);
            // Channels that confirm by push notification need the customer's number.
            $mobileChannels = $methodGroups->flatten(1)->filter(fn ($m) => $m['channel']->needs_mobile_number)->pluck('code')->values()->all();
            $kindHints = ['qris' => 'Scan dari e-wallet atau mobile banking.', 'va' => 'Transfer dari ATM atau m-banking.', 'ewallet' => 'Konfirmasi di aplikasi e-wallet.', 'card' => 'Isi data kartu di halaman pembayaran aman.'];
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="checkout-title"
            x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeCheckout()" wire:key="checkout-modal">
            <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" wire:click="closeCheckout"></div>
            <div class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl border border-line bg-surface p-5 shadow-xl animate-rise sm:max-w-md sm:rounded-2xl sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="checkout-title" class="font-display text-lg font-bold tracking-tight text-fg">Pembayaran</h2>
                        <p class="mt-0.5 text-sm text-muted">
                            {{ $order['name'] ?? $planLabel($this->plan) }} · {{ $periods[$orderPeriod] }}
                            @if($activePayment) · <span class="font-mono text-xs">{{ $activePayment->order_id }}</span> @endif
                        </p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm -mr-2 -mt-1 shrink-0" wire:click="closeCheckout" wire:loading.attr="disabled" wire:target="pay" aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 flex items-baseline justify-between rounded-xl bg-fg/4 px-4 py-3">
                    <span class="text-sm text-muted">Total bayar</span>
                    <span class="text-xl font-bold tabular-nums text-fg">{{ $rp($orderAmount) }}</span>
                </div>

                @if(! $activePayment)
                    {{-- Langkah 1: pilih metode --}}
                    {{-- Locked while the gateway creates the code, so clicks cannot look like a method change. --}}
                    <fieldset class="mt-5" wire:loading.attr="disabled" wire:target="pay">
                        <legend class="label">Metode pembayaran</legend>
                        @if($methods)
                            <div class="space-y-4">
                                @foreach(\App\Enums\PaymentChannelKind::cases() as $kind)
                                    @continue(! $methodGroups->has($kind->value))
                                    <div wire:key="kind-{{ $kind->value }}">
                                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted">{{ $kind->label() }} <span class="font-normal normal-case tracking-normal">· {{ $kindHints[$kind->value] }}</span></p>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach($methodGroups[$kind->value] as $method)
                                                <label wire:key="channel-{{ $method['code'] }}" class="{{ $tileClass }} justify-center px-2.5 py-2.5">
                                                    <input type="radio" wire:model="channel" value="{{ $method['code'] }}" class="sr-only">
                                                    {{-- Logo or channel badge only; screen readers get the channel name (logo alt or sr-only text). --}}
                                                    <x-payment-badge :channel="$method['channel']" :logo="$method['logo']" :alt="$method['channel']->fullLabel()" class="w-[4.5rem]" />
                                                    @unless($method['logo'])
                                                        <span class="sr-only">{{ $method['channel']->fullLabel() }}</span>
                                                    @endunless
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if($mobileChannels)
                                <div class="mt-4" x-show="@js($mobileChannels).includes($wire.channel)" x-cloak>
                                    <label for="wallet-number" class="label">Nomor HP terdaftar di e-wallet</label>
                                    <input id="wallet-number" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" wire:model="mobileNumber" placeholder="0812xxxxxxxx" aria-describedby="wallet-number-help" class="input">
                                    <p id="wallet-number-help" class="mt-1.5 text-xs text-muted">Notifikasi pembayaran dikirim ke aplikasi e-wallet di nomor ini.</p>
                                </div>
                            @endif
                        @else
                            <p class="rounded-lg bg-fg/4 px-4 py-6 text-center text-sm text-muted">Belum ada metode pembayaran yang aktif.</p>
                        @endif
                    </fieldset>

                    @error('checkout') <p class="field-error mt-3" role="alert">{{ $message }}</p> @enderror

                    <button type="button" class="btn-primary mt-5 w-full" wire:click="pay" wire:loading.attr="disabled" wire:target="pay" @disabled(! $methods)>
                        <svg wire:loading wire:target="pay" class="h-4 w-4 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                        <span wire:loading.remove wire:target="pay">Lanjut bayar</span>
                        <span wire:loading wire:target="pay">Menyiapkan…</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-muted" wire:loading.remove wire:target="pay">Diproses aman oleh payment gateway. Paket aktif otomatis setelah pembayaran diterima.</p>
                    <p class="mt-3 text-center text-xs text-muted" wire:loading.block wire:target="pay" role="status">Menghubungi penyedia pembayaran, bisa sampai 30 detik.</p>

                @elseif($activeStatus === 'paid')
                    {{-- Berhasil --}}
                    <div class="mt-6 text-center" role="status">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-ok/10 text-ok">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <p class="mt-3 font-semibold text-fg">Pembayaran diterima</p>
                        <p class="mt-1 text-sm text-muted">
                            Paket {{ $tier->label() }} aktif @if($active) sampai {{ $wib($until) }}@endif.
                        </p>
                    </div>
                    <button type="button" class="btn-primary mt-6 w-full" wire:click="closeCheckout">Selesai</button>

                @elseif($activePayable)
                    {{-- Langkah 2: tampilkan kode bayar, cek status tiap 5 detik --}}
                    <div wire:poll.5s="checkStatus">
                        @if($display === \App\Models\Payment::DISPLAY_QR)
                            <div class="mt-5 flex flex-col items-center">
                                <div class="rounded-xl border border-line bg-white p-3">
                                    <img src="{{ $qrImage }}" width="240" height="240" alt="Kode QR {{ $activeChannel?->name }} untuk order {{ $activePayment->order_id }}" class="h-60 w-60">
                                </div>
                                <p class="mt-3 max-w-xs text-center text-sm text-muted">
                                    {{ $activeChannel?->kind === \App\Enums\PaymentChannelKind::Qris ? 'Scan dengan aplikasi e-wallet atau mobile banking yang mendukung QRIS.' : 'Scan dengan aplikasi '.$activeChannel?->name.'.' }}
                                </p>
                                <a href="{{ $qrImage }}" download="qr-{{ $activePayment->order_id }}.png" class="btn-ghost btn-sm mt-2">Unduh QR</a>
                            </div>
                        @elseif($display === \App\Models\Payment::DISPLAY_VA)
                            <div class="mt-5" x-data="{ copied: false }">
                                <p class="label">Nomor Virtual Account {{ $activeChannel?->name }}</p>
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-line bg-fg/4 px-4 py-3">
                                    <span class="break-all font-mono text-lg font-bold tracking-wider tabular-nums text-fg">{{ trim(chunk_split($activePayment->pay_code, 4, ' ')) }}</span>
                                    <button type="button" class="btn-secondary btn-sm shrink-0"
                                        @click="navigator.clipboard?.writeText(@js($activePayment->pay_code)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                        <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                                    </button>
                                </div>
                                <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-muted">
                                    <li>Buka mobile banking, internet banking, atau ATM {{ $activeChannel?->name }}.</li>
                                    <li>Pilih menu transfer ke Virtual Account, lalu masukkan nomor di atas.</li>
                                    <li>Pastikan nominal {{ $rp($activePayment->amount) }}, lalu konfirmasi.</li>
                                </ol>
                            </div>
                        @elseif($display === \App\Models\Payment::DISPLAY_LINK)
                            <div class="mt-5 flex flex-col items-center text-center">
                                <x-payment-badge :channel="$activeChannel" :logo="$activeLogo" class="h-10 w-24 text-xs" />
                                <p class="mt-3 max-w-xs text-sm text-muted">
                                    {{ $activeCard ? 'Isi data kartu di halaman pembayaran aman. Nomor kartu tidak pernah disimpan di '.config('app.name').'.' : 'Konfirmasi pembayaran di '.$activeChannel?->name.'.' }}
                                    Halaman ini otomatis diperbarui setelah pembayaran diterima.
                                </p>
                                <a href="{{ $activePayment->pay_code }}" target="_blank" rel="noopener noreferrer" class="btn-primary mt-4">
                                    {{ $activeCard ? 'Buka halaman pembayaran' : 'Buka '.$activeChannel?->name }}
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    <span class="sr-only">(tab baru)</span>
                                </a>
                            </div>
                        @else
                            <div class="mt-5 flex flex-col items-center text-center">
                                <x-payment-badge :channel="$activeChannel" :logo="$activeLogo" class="h-10 w-24 text-xs" />
                                <p class="mt-3 font-semibold text-fg">Cek notifikasi di aplikasi {{ $activeChannel?->name }}</p>
                                <p class="mt-1 max-w-xs text-sm text-muted">Buka notifikasi pembayaran {{ $rp($activePayment->amount) }} dari {{ config('app.name') }}, lalu konfirmasi dengan PIN.</p>
                            </div>
                        @endif

                        <div class="mt-5 flex items-center gap-2.5 rounded-xl border border-line bg-tool/15 px-4 py-3 text-sm text-fg" role="status"
                            x-data="{ left: {{ $secondsLeft }}, timer: null,
                                init() { this.timer = setInterval(() => { this.left = Math.max(0, this.left - 1) }, 1000) },
                                destroy() { clearInterval(this.timer) },
                                get clock() { const h = Math.floor(this.left / 3600), m = Math.floor(this.left % 3600 / 60), s = this.left % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0') } }">
                            <svg class="h-4 w-4 shrink-0 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                            <p class="min-w-0 flex-1">
                                Menunggu pembayaran
                                <span class="block text-xs text-muted">Berlaku s.d. {{ $wib($activePayment->expires_at) }} · sisa <span class="font-mono tabular-nums" x-text="clock"></span></span>
                            </p>
                        </div>

                        @if($canSimulate)
                            <div class="mt-4 rounded-xl border border-dashed border-line-strong px-4 py-3">
                                <p class="text-xs text-muted">Mode test: tidak ada uang yang ditarik.</p>
                                <button type="button" class="btn-secondary btn-sm mt-2 w-full" wire:click="simulatePayment" wire:loading.attr="disabled" wire:target="simulatePayment">
                                    <span wire:loading.remove wire:target="simulatePayment">Simulasikan pembayaran</span>
                                    <span wire:loading wire:target="simulatePayment">Mengirim simulasi…</span>
                                </button>
                            </div>
                        @endif

                        @error('checkout') <p class="field-error mt-3" role="alert">{{ $message }}</p> @enderror

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                            <x-confirm-button action="cancelPayment" wire:loading.attr="disabled" wire:target="cancelPayment" class="btn-danger-soft btn-sm"
                                title="Batalkan pembayaran?" message="Kode bayar ini tidak bisa dipakai lagi. Kamu bisa memilih paket dan metode lain setelahnya."
                                confirm="Ya, batalkan" cancel="Kembali">Batalkan</x-confirm-button>
                            <button type="button" class="btn-secondary btn-sm" wire:click="checkStatus" wire:loading.attr="disabled" wire:target="checkStatus">
                                <span wire:loading.remove wire:target="checkStatus">Cek status</span>
                                <span wire:loading wire:target="checkStatus">Memeriksa…</span>
                            </button>
                        </div>
                    </div>

                @else
                    {{-- Kedaluwarsa / gagal --}}
                    <div class="mt-6 text-center" role="alert">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-danger/10 text-danger">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <p class="mt-3 font-semibold text-fg">{{ ['failed' => 'Pembayaran gagal', 'canceled' => 'Pembayaran dibatalkan'][$activeStatus] ?? 'Kode pembayaran sudah tidak berlaku' }}</p>
                        <p class="mt-1 text-sm text-muted">Paket kamu tidak berubah. Buat kode baru untuk melanjutkan.</p>
                    </div>
                    <button type="button" class="btn-primary mt-6 w-full" wire:click="changeMethod">Buat kode baru</button>
                @endif
            </div>
        </div>
    @endif
</div>
