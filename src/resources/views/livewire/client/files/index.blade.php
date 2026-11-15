@php
    use App\Enums\FileOperationStatus;
    use Illuminate\Support\Number;

    // Kontrak: $operations (paginator FileOperation milik user), $tools (katalog keyed by slug), $polling.
    $tones = [
        FileOperationStatus::Done->value => 'badge-ok',
        FileOperationStatus::Failed->value => 'badge-danger',
        FileOperationStatus::Pending->value => 'badge-warn',
        FileOperationStatus::Processing->value => 'badge-warn',
    ];
@endphp

<div class="space-y-4" @if($polling) wire:poll.5s @endif>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Hasil bisa diunduh sampai masa simpannya habis. Riwayatnya tetap tercatat di sini.</p>
        <a href="{{ route('tools.index') }}" class="btn-primary btn-sm whitespace-nowrap">Proses file</a>
    </div>

    @error('files')
        <div class="rounded-md border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="glass overflow-hidden" wire:loading.class="opacity-60" wire:target="delete, gotoPage, nextPage, previousPage">
        @if($operations->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">File</th>
                            <th scope="col" class="px-5 py-3 font-medium">Alat</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Ukuran hasil</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Dibuat</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($operations as $operation)
                            @php
                                $tool = $tools->get($operation->tool);
                                $inputs = (array) $operation->inputs;
                                $first = $inputs[0]['name'] ?? '—';
                                $downloadable = $operation->isDownloadable();
                            @endphp
                            <tr wire:key="file-{{ $operation->id }}">
                                <td class="max-w-[18rem] px-5 py-3">
                                    <span class="block truncate font-medium text-fg" title="{{ $operation->output_name ?? $first }}">{{ $operation->output_name ?? $first }}</span>
                                    <span class="block text-xs text-muted">
                                        {{ count($inputs) }} file masuk · {{ Number::fileSize($operation->inputSize()) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($tool && isset($tool['route']))
                                        <a href="{{ route($tool['route']) }}" class="text-link hover:underline">{{ $tool['name'] }}</a>
                                    @else
                                        <span class="text-muted">{{ $operation->tool }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="badge {{ $tones[$operation->status->value] ?? 'badge-muted' }}">{{ $operation->status->label() }}</span>
                                    @if($operation->status === FileOperationStatus::Failed && $operation->error)
                                        <span class="mt-1 block max-w-[14rem] text-xs text-muted">{{ $operation->error }}</span>
                                    @elseif($downloadable)
                                        <span class="mt-1 block text-xs text-muted">{{ $operation->expires_at ? 'Sampai '.$operation->expires_at->timezone(config('toolkits.timezone'))->translatedFormat('d M Y, H:i').' WIB' : 'Disimpan permanen' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-muted">
                                    {{ $operation->output_size !== null ? Number::fileSize($operation->output_size) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-muted">
                                    {{ $operation->created_at->timezone(config('toolkits.timezone'))->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($downloadable)
                                            <a href="{{ route('files.download', $operation) }}" class="btn-secondary btn-sm">Unduh</a>
                                        @endif
                                        @unless($operation->status->isActive())
                                            <x-confirm-button :action="'delete(\''.$operation->id.'\')'" :title="'Hapus file ini?'"
                                                message="Hasil dan riwayatnya dihapus permanen." confirm="Hapus"
                                                class="btn-danger-soft btn-sm" aria-label="Hapus file">
                                                Hapus
                                            </x-confirm-button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-5 py-14 text-center">
                <h2 class="font-semibold text-fg">Belum ada riwayat file</h2>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Hasil kompres, konversi, dan olah PDF yang kamu buat saat masuk akan tersimpan di sini.</p>
                <a href="{{ route('tools.index') }}" class="btn-primary mt-4">Buka alat file</a>
            </div>
        @endif
    </div>

    <div>{{ $operations->links() }}</div>
</div>
