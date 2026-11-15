@php
    use App\Enums\PaymentStatus;
    use App\Services\Tier\PlanLimits;
    use Illuminate\Support\Carbon;

    // Kontrak: $payments (paginate 25, eager user), $totals, wire:model status/search.
    $rp = fn ($v) => 'Rp' . number_format((int) $v, 0, ',', '.');
    $val = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;
    $wib = fn ($d) => $d ? Carbon::parse($d)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') : null;
    $periods = ['monthly' => 'Bulanan', 'yearly' => 'Tahunan'];

    $totalAmount = is_numeric($totals ?? null) ? (int) $totals : (int) data_get($totals ?? [], 'amount', data_get($totals ?? [], 'paid_amount', 0));
    $totalCount = is_numeric($totals ?? null) ? null : data_get($totals ?? [], 'count', data_get($totals ?? [], 'paid_count'));

    $statusBadge = [
        'paid' => 'bg-ok/10 text-ok',
        'pending' => 'bg-warn/10 text-warn',
        'expired' => 'bg-fg/6 text-fg',
        'failed' => 'bg-danger/10 text-danger',
    ];
@endphp

    <div class="space-y-4">
        {{-- Ringkasan --}}
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-sm text-muted">Pendapatan bulan ini <span class="text-xs">(lunas, {{ data_get($totals ?? [], 'month') ?? now('Asia/Jakarta')->translatedFormat('F Y') }})</span></p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-fg">{{ $rp($totalAmount) }}</p>
            @if($totalCount !== null)
                <p class="mt-1 text-sm text-muted">{{ number_format((int) $totalCount, 0, ',', '.') }} transaksi lunas</p>
            @endif
        </div>

        {{-- Kontrol --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari email atau order ID…" class="input pl-9" aria-label="Cari transaksi" maxlength="100">
            </div>
            <label for="payment-status" class="sr-only">Filter status</label>
            <select id="payment-status" wire:model.live="status" class="input py-2 text-sm sm:w-52">
                <option value="">Semua status</option>
                @foreach(PaymentStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        {{-- Tabel --}}
        <div class="relative rounded-2xl border border-line bg-surface"
            wire:loading.class="opacity-60" wire:target="search,status,gotoPage,nextPage,previousPage">
            @if($payments && $payments->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Pengguna</th>
                                <th scope="col" class="px-5 py-3 font-medium">Order</th>
                                <th scope="col" class="px-5 py-3 font-medium">Paket</th>
                                <th scope="col" class="px-5 py-3 text-right font-medium">Jumlah</th>
                                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                <th scope="col" class="px-5 py-3 font-medium">Metode</th>
                                <th scope="col" class="px-5 py-3 font-medium">Dibayar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($payments as $p)
                                @php
                                    $st = $val($p->status);
                                    $stEnum = $p->status instanceof PaymentStatus ? $p->status : PaymentStatus::tryFrom((string) $st);
                                    $method = collect([$p->payment_method, $p->payment_channel])->filter()->unique()->map(fn ($m) => str_replace('_', ' ', $m))->implode(' · ');
                                @endphp
                                <tr wire:key="payment-{{ $p->id }}">
                                    <td class="max-w-[14rem] px-5 py-3">
                                        @if($p->user)
                                            <span class="block truncate font-medium text-fg">{{ $p->user->name }}</span>
                                            <span class="block truncate text-xs text-muted">{{ $p->user->email }}</span>
                                        @else
                                            <span class="text-muted">Akun dihapus</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <span class="block font-mono text-xs text-fg">{{ $p->order_id }}</span>
                                        <span class="block text-xs text-muted">{{ $wib($p->created_at) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <span class="block text-fg">{{ app(PlanLimits::class)->label((string) $val($p->plan)) }}</span>
                                        <span class="block text-xs text-muted">{{ $periods[$val($p->period)] ?? $val($p->period) }} · {{ ucfirst((string) $p->kind) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-fg">{{ $rp($p->amount) }}</td>
                                    <td class="px-5 py-3">
                                        <span class="badge {{ $statusBadge[$st] ?? $statusBadge['expired'] }}">{{ $stEnum?->label() ?? ucfirst((string) $st) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-xs uppercase tracking-wide text-muted">{{ $method ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-muted">{{ $wib($p->paid_at) ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center text-sm text-muted">
                    {{ filled($search ?? null) || filled($status ?? null) ? 'Tidak ada transaksi yang cocok dengan filter.' : 'Belum ada transaksi.' }}
                </div>
            @endif
        </div>

        @if($payments)
            <div>{{ $payments->links() }}</div>
        @endif
    </div>
