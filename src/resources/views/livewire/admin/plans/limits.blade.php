@php
    use App\Services\Tier\Entitlements;
    use App\Services\Tier\Tier;

    // Kontrak: $plans (urut sort), $groups (group => [key => entry]); binding values.{slug}.{key}, meta.{slug}.{name|price_monthly|price_yearly},
    // draft.{name|slug|price_monthly|price_yearly}; aksi deletePlan('{slug}') dengan error "plan".
    $cell = 'px-3 py-2.5 text-center align-middle';
@endphp

<div>
    @if(session('status'))
        <div class="mb-4 rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok" role="status">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" novalidate x-data="{ saved: false }" x-on:limits-saved.window="saved = true; setTimeout(() => saved = false, 2500)">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-semibold text-fg">Paket, harga, dan batas</h2>
                <p class="mt-0.5 max-w-prose text-sm text-muted">Satu kolom per paket. Angka kosong berarti tak terbatas. Perubahan langsung berlaku untuk semua pengguna paket itu, dan daftar fitur di <a href="{{ route('pricing') }}" target="_blank" rel="noopener" class="text-link underline-offset-4 hover:underline">halaman harga<span class="sr-only"> (tab baru)</span></a> ikut diperbarui.</p>
            </div>
            <div class="flex items-center gap-3">
                <span x-show="saved" x-cloak class="text-sm font-medium text-ok" role="status">Tersimpan</span>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
            </div>
        </div>

        @error('plan')
            <p class="mt-3 rounded-md border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror

        @if(collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'values.') || str_starts_with($k, 'meta.')))
            <p class="mt-3 rounded-md border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">Ada nilai yang belum valid. Periksa kolom bertanda merah.</p>
        @endif

        <div class="glass mt-4 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-surface px-4 py-3 text-left font-medium">Batas / fitur</th>
                        @foreach($plans as $plan)
                            <th scope="col" wire:key="head-{{ $plan->id }}" class="min-w-[10rem] px-3 py-3 text-center align-top font-medium normal-case tracking-normal">
                                <span class="block text-fg">{{ $plan->name }}</span>
                                <span class="font-mono text-[11px]">{{ $plan->slug }}{{ Tier::isSystem($plan->slug) ? ' · sistem' : '' }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="border-b border-line">
                    {{-- Identitas paket: nama, harga, hapus --}}
                    @foreach(['name' => 'Nama paket', 'price_monthly' => 'Harga per bulan (Rp)', 'price_yearly' => 'Harga per tahun (Rp)'] as $field => $fieldLabel)
                        <tr wire:key="meta-{{ $field }}" class="border-b border-line/70">
                            <th scope="row" class="sticky left-0 z-10 bg-surface px-4 py-2.5 text-left font-medium text-fg">
                                {{ $fieldLabel }}
                                @if($field !== 'name') <span class="block text-xs font-normal text-muted">Free selalu Rp0</span> @endif
                            </th>
                            @foreach($plans as $plan)
                                @php
                                    $path = "meta.{$plan->slug}.{$field}";
                                    $id = "m-{$plan->slug}-{$field}";
                                @endphp
                                <td wire:key="meta-{{ $plan->id }}-{{ $field }}" class="{{ $cell }}">
                                    @if($field === 'name')
                                        <input id="{{ $id }}" type="text" wire:model="{{ $path }}" maxlength="50" autocomplete="off" aria-label="Nama paket {{ $plan->slug }}"
                                            @class(['input py-1.5 text-sm', 'border-danger' => $errors->has($path)])>
                                    @elseif(Tier::isSystem($plan->slug))
                                        <span class="text-muted">Rp0</span>
                                    @else
                                        <input id="{{ $id }}" type="number" inputmode="numeric" min="1" step="1000" wire:model="{{ $path }}" aria-label="{{ $fieldLabel }} {{ $plan->name }}"
                                            @class(['input mx-auto w-28 py-1.5 text-right tabular-nums', 'border-danger' => $errors->has($path)])>
                                    @endif
                                    @error($path) <p class="field-error text-left">{{ $message }}</p> @enderror
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <th scope="row" class="sticky left-0 z-10 bg-surface px-4 py-2.5 text-left font-medium text-fg">
                            Hapus paket
                            <span class="block text-xs font-normal text-muted">Hanya bila tidak ada pelanggan aktif</span>
                        </th>
                        @foreach($plans as $plan)
                            <td wire:key="delete-{{ $plan->id }}" class="{{ $cell }}">
                                @if(Tier::isSystem($plan->slug))
                                    <span class="text-xs text-muted">Paket sistem</span>
                                @else
                                    <x-confirm-button :action="'deletePlan(\''.$plan->slug.'\')'" :title="'Hapus paket '.$plan->name.'?'"
                                        message="Paket hilang dari halaman harga dan matriks. Riwayat pembayarannya tetap tersimpan."
                                        confirm="Hapus paket" class="btn-danger-soft btn-sm" aria-label="Hapus paket {{ $plan->name }}">
                                        Hapus
                                    </x-confirm-button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
                <tbody>
                    @foreach($groups as $group => $entries)
                        <tr class="bg-fg/4">
                            <th scope="colgroup" colspan="{{ $plans->count() + 1 }}" class="sticky left-0 px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted">{{ $group }}</th>
                        </tr>
                        @foreach($entries as $key => $entry)
                            <tr wire:key="row-{{ $key }}" class="border-b border-line/70 last:border-0">
                                <th scope="row" class="sticky left-0 z-10 bg-surface px-4 py-2.5 text-left font-medium text-fg">
                                    {{ $entry['label'] }}
                                    @if(! empty($entry['help']) || ! empty($entry['nullable']))
                                        <span class="block text-xs font-normal text-muted">{{ $entry['help'] ?? 'Kosong = tak terbatas' }}</span>
                                    @endif
                                </th>
                                @foreach($plans as $plan)
                                    @php
                                        $path = "values.{$plan->slug}.{$key}";
                                        $id = "v-{$plan->slug}-{$key}";
                                        $label = $entry['label'].' untuk '.$plan->name;
                                    @endphp
                                    <td wire:key="cell-{{ $plan->id }}-{{ $key }}" class="{{ $cell }}">
                                        @if($entry['type'] === Entitlements::BOOL)
                                            <input id="{{ $id }}" type="checkbox" wire:model="{{ $path }}" aria-label="{{ $label }}"
                                                class="h-4 w-4 rounded border-line text-ok focus:ring-fg">
                                        @elseif($entry['type'] === Entitlements::SELECT)
                                            <select id="{{ $id }}" wire:model="{{ $path }}" aria-label="{{ $label }}"
                                                @class(['input py-1.5 text-sm', 'border-danger' => $errors->has($path)])>
                                                @foreach($entry['options'] as $value => $optionLabel)
                                                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input id="{{ $id }}" type="number" inputmode="numeric" wire:model="{{ $path }}" aria-label="{{ $label }}"
                                                min="{{ $entry['min'] }}" max="{{ $entry['max'] }}" step="1"
                                                placeholder="{{ ! empty($entry['nullable']) ? '∞' : '' }}"
                                                @if($errors->has($path)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                                                @class(['input mx-auto w-24 py-1.5 text-right tabular-nums', 'border-danger' => $errors->has($path)])>
                                        @endif
                                        @error($path) <p id="{{ $id }}-error" class="field-error text-left">{{ $message }}</p> @enderror
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>

    {{-- Paket berbayar baru = kolom baru --}}
    <section class="glass mt-8 p-5 sm:p-6" aria-labelledby="new-plan-title">
        <h2 id="new-plan-title" class="font-semibold text-fg">Tambah paket berbayar</h2>
        <p class="mt-0.5 max-w-prose text-sm text-muted">Paket baru langsung dijual di halaman harga. Batasnya disalin dari Free, lalu atur di matriks di atas. Kode paket tidak bisa diubah setelah dibuat.</p>

        <form wire:submit="createPlan" novalidate class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="draft-name" class="label">Nama paket</label>
                <input id="draft-name" type="text" wire:model="draft.name" maxlength="50" autocomplete="off" placeholder="Bisnis"
                    @class(['input', 'border-danger' => $errors->has('draft.name')])>
                @error('draft.name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="draft-slug" class="label">Kode paket</label>
                <input id="draft-slug" type="text" wire:model="draft.slug" maxlength="20" autocomplete="off" placeholder="bisnis" spellcheck="false"
                    @class(['input font-mono', 'border-danger' => $errors->has('draft.slug')])>
                @error('draft.slug') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="draft-monthly" class="label">Harga per bulan (Rp)</label>
                <input id="draft-monthly" type="number" inputmode="numeric" min="1" step="1000" wire:model="draft.price_monthly"
                    @class(['input tabular-nums', 'border-danger' => $errors->has('draft.price_monthly')])>
                @error('draft.price_monthly') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="draft-yearly" class="label">Harga per tahun (Rp)</label>
                <input id="draft-yearly" type="number" inputmode="numeric" min="1" step="1000" wire:model="draft.price_yearly"
                    @class(['input tabular-nums', 'border-danger' => $errors->has('draft.price_yearly')])>
                @error('draft.price_yearly') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="submit" class="btn-secondary" wire:loading.attr="disabled" wire:target="createPlan">Tambah paket</button>
            </div>
        </form>
    </section>
</div>
