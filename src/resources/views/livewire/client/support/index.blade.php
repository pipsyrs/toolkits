@php
    use App\Actions\Support\SubmitSupportReport;

    $wib = fn ($d) => $d?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i');
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <div class="flex flex-col gap-3 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Ada kendala? Kirim laporan ke tim CS. Status dan balasannya muncul di daftar ini.</p>
        <button type="button" class="btn-primary shrink-0" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">Kirim laporan</button>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif

    <section aria-labelledby="my-reports-heading" class="animate-rise">
        <h2 id="my-reports-heading" class="sr-only">Laporan saya</h2>

        <div class="relative" wire:loading.class="opacity-60" wire:target="gotoPage,nextPage,previousPage">
            @if($reports->isEmpty())
                <div class="glass px-6 py-14 text-center">
                    <p class="font-semibold text-fg">Belum ada laporan.</p>
                    <p class="mt-1 text-sm text-muted">Laporan yang kamu kirim akan muncul di sini beserta statusnya.</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach($reports as $report)
                        <li wire:key="report-{{ $report->id }}" class="glass p-5">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="break-words font-semibold text-fg">{{ $report->subject }}</h3>
                                    <p class="mt-0.5 text-xs text-muted">{{ $report->category->label() }} · {{ $wib($report->created_at) }} WIB</p>
                                </div>
                                <span class="badge {{ $report->status->badge() }}">{{ $report->status->label() }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-line break-words text-sm text-fg">{{ $report->message }}</p>

                            @if($report->admin_reply)
                                <div class="mt-4 rounded-lg border-l-2 border-fg bg-fg/4 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Balasan CS</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm text-fg">{{ $report->admin_reply }}</p>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">{{ $reports->links() }}</div>
            @endif
        </div>
    </section>

    <x-dashboard.modal wire:model="formOpen" submit="submit" submit-label="Kirim laporan" size="lg"
        title="Kirim laporan" description="Ceritakan kendalamu. Jangan sertakan password atau kode OTP.">
        <div>
            <label for="report-category" class="label">Kategori</label>
            <select id="report-category" wire:model="category" required
                @class(['input', 'border-danger' => $errors->has('category')]) @error('category') aria-invalid="true" aria-describedby="report-category-error" @enderror>
                <option value="">Pilih kategori…</option>
                @foreach($categories as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('category') <p id="report-category-error" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="report-subject" class="label">Judul</label>
            <input id="report-subject" type="text" wire:model="subject" maxlength="{{ SubmitSupportReport::SUBJECT_MAX }}" required
                placeholder="mis. Pembayaran QRIS belum terkonfirmasi"
                @class(['input', 'border-danger' => $errors->has('subject')]) @error('subject') aria-invalid="true" aria-describedby="report-subject-error" @enderror>
            @error('subject') <p id="report-subject-error" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="report-message" class="label">Pesan</label>
            <textarea id="report-message" wire:model="message" rows="6" minlength="{{ SubmitSupportReport::MESSAGE_MIN }}" maxlength="{{ SubmitSupportReport::MESSAGE_MAX }}" required
                placeholder="Apa yang terjadi, kapan, dan langkah yang sudah dicoba."
                @class(['input', 'border-danger' => $errors->has('message')]) @error('message') aria-invalid="true" aria-describedby="report-message-error" @enderror></textarea>
            @error('message') <p id="report-message-error" class="field-error">{{ $message }}</p> @enderror
        </div>
    </x-dashboard.modal>
</div>
