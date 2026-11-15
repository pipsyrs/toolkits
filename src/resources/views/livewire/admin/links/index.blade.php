
    <div class="space-y-4" x-data="{ open: false, linkId: null, linkCode: '', reason: '' }"
        @keydown.escape.window="open = false">

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau domain…" class="input pl-9" aria-label="Cari link">
            </div>
            <label for="status" class="sr-only">Filter status</label>
            <select id="status" wire:model.live="status" class="input py-2 text-sm sm:w-48">
                <option value="all">Semua</option>
                <option value="active">Aktif</option>
                <option value="disabled">Dinonaktifkan</option>
                <option value="guest">Dibuat tamu</option>
            </select>
        </div>

        <div class="rounded-2xl border border-line bg-surface">
            @if($links && $links->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Kode</th>
                                <th scope="col" class="px-5 py-3 font-medium">Tujuan</th>
                                <th scope="col" class="px-5 py-3 font-medium">Pemilik</th>
                                <th scope="col" class="px-5 py-3 text-right font-medium">Klik</th>
                                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($links as $link)
                                <tr wire:key="alink-{{ $link->id }}">
                                    <td class="px-5 py-3 font-medium text-fg">/s/{{ $link->code }}</td>
                                    <td class="max-w-[16rem] px-5 py-3">
                                        <span class="block truncate text-fg" title="{{ $link->target_url }}">{{ $link->target_host }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-muted">
                                        {{ $link->user?->email ?? 'Tamu' }}
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-fg">{{ number_format($link->clicks_total) }}</td>
                                    <td class="px-5 py-3">
                                        @if($link->disabled_at)
                                            <span class="badge bg-danger/10 text-danger" title="{{ $link->disabled_reason }}">Nonaktif</span>
                                        @else
                                            <span class="badge bg-ok/10 text-ok">Aktif</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @can('links.moderate')
                                        @if($link->disabled_at)
                                            <x-confirm-button :action="'enable(\''.$link->id.'\')'" :title="'Aktifkan kembali /s/'.$link->code.'?'"
                                                message="Link bisa diakses publik lagi." confirm="Aktifkan" variant="primary"
                                                class="btn-secondary btn-sm">Aktifkan</x-confirm-button>
                                        @else
                                            <button type="button" @click="linkId = '{{ $link->id }}'; linkCode = @js($link->code); reason = ''; open = true"
                                                class="btn-danger-soft btn-sm">Nonaktifkan</button>
                                        @endif
                                        @else
                                            <span class="text-xs text-muted">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center text-sm text-muted">Tidak ada link yang cocok.</div>
            @endif
        </div>

        @if($links)
            <div>{{ $links->links() }}</div>
        @endif

        {{-- Modal alasan nonaktif --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="disable-title">
            <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-xl">
                <h2 id="disable-title" class="text-lg font-semibold text-fg">Nonaktifkan link</h2>
                <p class="mt-1 text-sm text-muted">Nonaktifkan <span class="font-medium text-fg">/s/<span x-text="linkCode"></span></span>. Pengunjung akan melihat halaman tidak tersedia.</p>
                <div class="mt-4">
                    <label for="disable-reason" class="label text-fg">Alasan</label>
                    <textarea id="disable-reason" x-model="reason" rows="3" class="input" placeholder="Contoh: tautan spam / phishing"></textarea>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="open = false">Batal</button>
                    <button type="button" class="btn-danger"
                        @click="$wire.disable(linkId, reason); open = false">Nonaktifkan</button>
                </div>
            </div>
        </div>
    </div>
