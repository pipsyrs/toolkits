@php
    use Illuminate\Support\Str;
    $actions = $actions ?? [];
    $actionLabel = fn ($a) => ucfirst(str_replace('_', ' ', (string) $a));
@endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-muted">Jejak tindakan oleh superadmin.</p>
            <label for="action" class="sr-only">Filter aksi</label>
            <select id="action" wire:model.live="action" class="input py-2 text-sm sm:w-56">
                <option value="">Semua aksi</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}">{{ $actionLabel($a) }}</option>
                @endforeach
            </select>
        </div>

        <div class="rounded-2xl border border-line bg-surface">
            @if($logs && $logs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Waktu</th>
                                <th scope="col" class="px-5 py-3 font-medium">Admin</th>
                                <th scope="col" class="px-5 py-3 font-medium">Aksi</th>
                                <th scope="col" class="px-5 py-3 font-medium">Subjek</th>
                                <th scope="col" class="px-5 py-3 font-medium">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($logs as $log)
                                <tr wire:key="log-{{ $log->id }}">
                                    <td class="whitespace-nowrap px-5 py-3 text-muted">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                                    <td class="px-5 py-3 text-fg">{{ $log->admin?->email ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="badge bg-fg/6 text-fg">{{ $actionLabel($log->action) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-muted">
                                        @if($log->subject_type)
                                            {{ class_basename($log->subject_type) }}
                                            <span class="text-muted">· {{ Str::limit($log->subject_id, 8, '…') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="max-w-[16rem] px-5 py-3">
                                        <span class="block truncate text-muted" title="{{ $log->reason }}">{{ $log->reason ?: '—' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center text-sm text-muted">Belum ada catatan audit.</div>
            @endif
        </div>

        @if($logs)
            <div>{{ $logs->links() }}</div>
        @endif
    </div>
