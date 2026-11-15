@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'image/jpeg,image/png,image/webp';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Gambar ke PDF</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gabung beberapa gambar menjadi satu PDF. Atur urutan dengan tombol naik/turun.</p>
    </header>

    <div class="card space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple sortable />

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="pageSize" class="label">Ukuran halaman</label>
                <select id="pageSize" wire:model="pageSize" class="input">
                    <option value="a4">A4</option>
                    <option value="letter">Letter</option>
                    <option value="fit">Sesuai gambar</option>
                </select>
            </div>
            <div>
                <label for="orientation" class="label">Orientasi</label>
                <select id="orientation" wire:model="orientation" class="input">
                    <option value="portrait">Potret</option>
                    <option value="landscape">Lanskap</option>
                </select>
            </div>
            <div>
                <label for="margin" class="label">Margin</label>
                <select id="margin" wire:model="margin" class="input">
                    <option value="0">Tanpa margin</option>
                    <option value="10">Kecil (10)</option>
                    <option value="20">Sedang (20)</option>
                </select>
            </div>
        </div>

        <x-preview-panel title="Pratinjau halaman" hint="Mengikuti ukuran, orientasi, dan margin"
            x-data="imagePdfLayout" x-on:files-changed.window="setFiles($event.detail.files)" x-show="items.length">
            <ol class="grid grid-cols-[repeat(auto-fill,minmax(7rem,1fr))] items-start gap-4" aria-label="Halaman PDF yang akan dibuat">
                <template x-for="(item, i) in items" :key="item.key">
                    <li>
                        <div class="relative w-full overflow-hidden rounded-sm border border-line bg-white shadow-sm" :style="pageStyle(item)">
                            <img :src="item.url" alt="" class="absolute object-fill" :style="imageStyle(item)">
                        </div>
                        <p class="mt-1 text-center text-xs text-muted" x-text="`Halaman ${i + 1}`"></p>
                    </li>
                </template>
            </ol>
        </x-preview-panel>

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
