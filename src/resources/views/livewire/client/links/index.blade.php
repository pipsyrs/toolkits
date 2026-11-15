
    <div class="space-y-4">
        {{-- Kontrol --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau tujuan…"
                    class="input pl-9" aria-label="Cari link">
            </div>
            <div class="flex items-center gap-2">
                <label for="sort" class="sr-only">Urutkan</label>
                <select id="sort" wire:model.live="sort" class="input py-2 text-sm">
                    <option value="created_at">Terbaru</option>
                    <option value="clicks_total">Klik terbanyak</option>
                </select>
                <button type="button" wire:click="$set('direction', '{{ ($direction ?? 'desc') === 'asc' ? 'desc' : 'asc' }}')"
                    class="btn-secondary btn-sm" aria-label="Balik arah urutan">
                    {{ ($direction ?? 'desc') === 'asc' ? 'Naik' : 'Turun' }}
                </button>
                <a href="{{ route('tools.shorten') }}" class="btn-primary btn-sm whitespace-nowrap">+ Link</a>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="glass overflow-hidden">
            @if($links && $links->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Link pendek</th>
                                <th scope="col" class="px-5 py-3 font-medium">Tujuan</th>
                                <th scope="col" class="px-5 py-3 text-right font-medium">Klik</th>
                                <th scope="col" class="px-5 py-3 text-right font-medium">Dibuat</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($links as $link)
                                <tr wire:key="link-{{ $link->id }}" x-data="{ copied: false }">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-link">/s/{{ $link->code }}</span>
                                            <button type="button" @click="navigator.clipboard.writeText(@js($link->short_url)); copied = true; setTimeout(() => copied = false, 1200)"
                                                class="text-muted hover:text-muted" aria-label="Salin link">
                                                <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                <svg x-show="copied" x-cloak class="h-4 w-4 text-ok" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="max-w-[16rem] px-5 py-3">
                                        <span class="block truncate text-muted" title="{{ $link->target_url }}">{{ $link->target_host }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-muted">{{ number_format($link->clicks_total) }}</td>
                                    <td class="px-5 py-3 text-right text-muted">{{ $link->created_at?->translatedFormat('d M Y') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <x-confirm-button :action="'delete(\''.$link->id.'\')'" :title="'Hapus link /s/'.$link->code.'?'"
                                            message="Tindakan ini tidak bisa dibatalkan." confirm="Hapus"
                                            class="btn-danger-soft btn-sm" aria-label="Hapus link">
                                            Hapus
                                        </x-confirm-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center">
                    <p class="text-sm text-muted">
                        @if(($search ?? '') !== '')
                            Tidak ada link yang cocok dengan pencarianmu.
                        @else
                            Belum ada link. Buat tautan pendek pertamamu.
                        @endif
                    </p>
                    @if(($search ?? '') === '')
                        <a href="{{ route('tools.shorten') }}" class="btn-primary mt-4">Perpendek tautan</a>
                    @endif
                </div>
            @endif
        </div>

        @if($links)
            <div>{{ $links->links() }}</div>
        @endif
    </div>
