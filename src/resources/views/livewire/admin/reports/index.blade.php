@php
    use App\Actions\Admin\HandleSupportReport;

    $wib = fn ($d) => $d?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i');
    $filtered = filled($search) || filled($status) || filled($category);
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-1 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Laporan yang dikirim pengguna dari menu Bantuan. Balasan tampil di dashboard pengguna.</p>
        <p class="text-sm text-muted"><span class="font-semibold tabular-nums text-fg">{{ number_format($openCount, 0, ',', '.') }}</span> laporan baru</p>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif

    {{-- Kontrol --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full sm:max-w-xs">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari judul atau email…" class="input pl-9" aria-label="Cari laporan" maxlength="{{ $searchMax }}">
        </div>
        <label for="report-filter-status" class="sr-only">Filter status</label>
        <select id="report-filter-status" wire:model.live="status" class="input py-2 text-sm sm:w-44">
            <option value="">Semua status</option>
            @foreach($statuses as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <label for="report-filter-category" class="sr-only">Filter kategori</label>
        <select id="report-filter-category" wire:model.live="category" class="input py-2 text-sm sm:w-52">
            <option value="">Semua kategori</option>
            @foreach($categories as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    @error('report')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
    @enderror

    {{-- Tabel --}}
    <div class="min-w-0">
        <div class="relative rounded-2xl border border-line bg-surface"
            wire:loading.class="opacity-60" wire:target="search,status,category,setPage">
            @if($reports->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Laporan</th>
                                <th scope="col" class="px-5 py-3 font-medium">Pengguna</th>
                                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                <th scope="col" class="px-5 py-3 font-medium">Dikirim</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($reports as $report)
                                <tr wire:key="report-{{ $report->id }}">
                                    <td class="max-w-[20rem] px-5 py-3">
                                        <button type="button" wire:click="open('{{ $report->id }}')" class="block max-w-full truncate rounded text-left font-medium text-fg underline-offset-2 hover:underline"
                                            @if($selected?->is($report)) aria-current="true" @endif>
                                            {{ $report->subject }}
                                        </button>
                                        <span class="block text-xs text-muted">{{ $report->category->label() }}</span>
                                    </td>
                                    <td class="max-w-[14rem] px-5 py-3">
                                        @if($report->user)
                                            <span class="block truncate text-fg">{{ $report->user->name }}</span>
                                            <span class="block truncate text-xs text-muted">{{ $report->user->email }}</span>
                                        @else
                                            <span class="text-muted">Akun dihapus</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3"><span class="badge {{ $report->status->badge() }}">{{ $report->status->label() }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-muted">{{ $wib($report->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center text-sm text-muted">
                    {{ $filtered ? 'Tidak ada laporan yang cocok dengan filter.' : 'Belum ada laporan dari pengguna.' }}
                </div>
            @endif
        </div>

        <div class="mt-4">{{ $reports->links() }}</div>
    </div>

    {{-- Detail & tanggapan --}}
    @php($canManage = auth()->user()->can('reports.manage'))
    <x-dashboard.modal wire:model="detailOpen" size="lg" :submit="$canManage && $selected ? 'save' : null" submit-label="Simpan tanggapan"
        :title="$selected?->subject ?? 'Laporan'"
        :description="$selected ? $selected->category->label().' · '.$wib($selected->created_at).' WIB' : null">
        @if($selected)
            <p class="text-sm text-muted">
                Dari <span class="font-medium text-fg">{{ $selected->user?->name ?? 'Akun dihapus' }}</span>
                @if($selected->user) · {{ $selected->user->email }} @endif
            </p>
            <p class="whitespace-pre-line break-words rounded-lg bg-fg/4 px-4 py-3 text-sm text-fg">{{ $selected->message }}</p>

            @if($selected->handler)
                <p class="text-xs text-muted">Terakhir ditangani {{ $selected->handler->name }}@if($selected->resolved_at) · selesai {{ $wib($selected->resolved_at) }} WIB @endif</p>
            @endif

            @if($canManage)
                <div class="space-y-4 border-t border-line pt-4">
                    <div>
                        <label for="reply-status" class="label">Status</label>
                        <select id="reply-status" wire:model="replyStatus" @class(['input', 'border-danger' => $errors->has('status')])
                            @error('status') aria-invalid="true" aria-describedby="reply-status-error" @enderror>
                            @foreach($statuses as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p id="reply-status-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="reply-text" class="label">Balasan untuk pengguna</label>
                        <textarea id="reply-text" wire:model="reply" rows="5" maxlength="{{ HandleSupportReport::REPLY_MAX }}"
                            @class(['input', 'border-danger' => $errors->has('reply')]) aria-describedby="reply-help @error('reply') reply-text-error @enderror"></textarea>
                        <p id="reply-help" class="mt-1.5 text-xs text-muted">Tampil di menu Bantuan milik pengguna. Kosongkan bila belum perlu membalas.</p>
                        @error('reply') <p id="reply-text-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @elseif($selected->admin_reply)
                <div class="border-t border-line pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Balasan CS</p>
                    <p class="mt-1 whitespace-pre-line break-words text-sm text-fg">{{ $selected->admin_reply }}</p>
                </div>
            @endif
        @endif
    </x-dashboard.modal>
</div>
