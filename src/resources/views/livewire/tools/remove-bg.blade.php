@php
    use App\Livewire\Tools\RemoveBackground;
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $locked = $this->cropLocked;
    $ratios = ['free' => 'Bebas', 'original' => 'Asli', '1:1' => '1:1', '4:5' => '4:5', '3:4' => '3:4', '16:9' => '16:9', 'custom' => 'Kustom'];
@endphp

<x-tool-layout slug="remove-bg" :steps="[
    ['Unggah foto', 'JPG, PNG, atau WebP. Objek yang jelas memberi hasil terbaik.'],
    ['Atur crop', 'Geser kotak crop dan pilih rasio sendiri (paket berbayar).'],
    ['Unduh PNG', 'Latar transparan, siap dipakai di desain apa pun.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <div x-data="bgCropper" x-on:files-changed.window="setFiles($event.detail.files)"
        x-on:crop-ratio.window="ratio = $event.detail.ratio; applyRatio()"
        x-on:crop-custom.window="Object.assign($data, { customW: $event.detail.w ?? customW, customH: $event.detail.h ?? customH }); applyRatio()"
        class="space-y-5">
        <x-file-dropzone :accept="$this->accept ?: 'image/jpeg,image/png,image/webp'" :max-files="1" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" />

        {{-- Foto milik pengunjung sendiri; kotak crop dalam satuan 0–1 dikirim bersama "Proses file". --}}
        <section x-show="url && $wire.crop" x-cloak wire:ignore data-no-save class="space-y-3 rounded-lg border border-line bg-surface p-4" aria-label="Area crop">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-fg">Atur area crop</h2>
                <span x-show="natural" class="badge badge-muted tabular-nums" x-text="size()"></span>
            </div>

            <div class="checker relative mx-auto w-fit max-w-full overflow-hidden rounded-md border border-line" x-ref="frame">
                <img :src="url" alt="Foto yang akan diproses" @load="loaded($event)" class="block max-h-[28rem] w-auto max-w-full select-none" draggable="false">

                <div x-show="natural" class="absolute cursor-move touch-none border-2 border-tool shadow-[0_0_0_9999px_rgb(0_0_0/0.55)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                    :style="style()" tabindex="0" role="slider" aria-label="Area crop. Panah untuk menggeser, Shift dan panah untuk mengubah ukuran."
                    :aria-valuetext="size()"
                    @pointerdown.prevent="start($event, 'move')" @pointermove="move($event)" @pointerup="end()" @pointercancel="end()" @keydown="key($event)">
                    {{-- Garis sepertiga untuk komposisi --}}
                    <span class="pointer-events-none absolute inset-0 grid grid-cols-3 grid-rows-3" aria-hidden="true">
                        @for($i = 0; $i < 9; $i++) <span class="border border-white/25"></span> @endfor
                    </span>
                    @foreach(['nw' => '-left-2 -top-2 cursor-nwse-resize', 'ne' => '-right-2 -top-2 cursor-nesw-resize', 'sw' => '-bottom-2 -left-2 cursor-nesw-resize', 'se' => '-bottom-2 -right-2 cursor-nwse-resize'] as $corner => $position)
                        <span class="absolute h-4 w-4 touch-none rounded-sm border-2 border-ink bg-tool {{ $position }}" aria-hidden="true"
                            @pointerdown.prevent.stop="start($event, '{{ $corner }}')" @pointermove.stop="move($event)" @pointerup.stop="end()" @pointercancel.stop="end()"></span>
                    @endforeach
                </div>
            </div>

            <p class="text-xs text-muted">Seret kotak untuk menggeser, tarik sudutnya untuk mengubah ukuran.</p>
        </section>
    </div>

    <x-slot:aside>
        <div class="tool-panel">
            <div class="tool-panel-section">
                <p class="tool-panel-title">
                    <span>Crop</span>
                    @if($locked) <x-plan-badge :limit-key="Entitlements::toolKey('remove-bg', 'crop')" /> @endif
                </p>

                <fieldset @disabled($locked) class="space-y-3">
                    <label class="option-tile justify-between">
                        <span>
                            <span class="block font-medium">Crop manual</span>
                            <span class="option-hint">Pilih area foto sebelum diproses</span>
                        </span>
                        <input type="checkbox" wire:model.live="crop" class="rounded border-line text-ok focus:ring-fg">
                    </label>

                    <div x-show="$wire.crop" x-cloak class="space-y-3">
                        <p class="text-xs font-medium text-muted">Rasio</p>
                        <div class="grid grid-cols-4 gap-1.5" role="radiogroup" aria-label="Rasio crop">
                            @foreach($ratios as $value => $label)
                                <label class="option-tile justify-center px-2 py-2 text-xs">
                                    <input type="radio" name="crop-ratio" value="{{ $value }}" class="sr-only"
                                        @checked($value === 'free')
                                        x-on:change="$dispatch('crop-ratio', { ratio: '{{ $value }}' })"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div x-data="{ custom: false }" x-on:crop-ratio.window="custom = $event.detail.ratio === 'custom'" x-show="custom" x-cloak
                            class="flex items-center gap-2">
                            <label class="sr-only" for="ratioW">Lebar rasio</label>
                            <input id="ratioW" type="number" min="1" max="100" value="3" class="input text-center"
                                x-on:input="$dispatch('crop-custom', { w: +$event.target.value })">
                            <span class="text-muted" aria-hidden="true">:</span>
                            <label class="sr-only" for="ratioH">Tinggi rasio</label>
                            <input id="ratioH" type="number" min="1" max="100" value="2" class="input text-center"
                                x-on:input="$dispatch('crop-custom', { h: +$event.target.value })">
                        </div>
                    </div>
                    @error('crop') <p class="field-error">{{ $message }}</p> @enderror
                    @error('cropW') <p class="field-error">{{ $message }}</p> @enderror
                    @error('cropH') <p class="field-error">{{ $message }}</p> @enderror

                    <label class="option-tile justify-between">
                        <span>
                            <span class="block font-medium">Potong ke objek</span>
                            <span class="option-hint">Buang area transparan di sekeliling objek</span>
                        </span>
                        <input type="checkbox" wire:model.live="trim" class="rounded border-line text-ok focus:ring-fg">
                    </label>

                    @if($trim && ! $locked)
                        <div>
                            <label for="padding" class="flex items-center justify-between text-xs font-medium text-muted">
                                <span>Jarak tepi</span>
                                <span class="tabular-nums text-fg" x-text="`${$wire.padding} px`"></span>
                            </label>
                            <input id="padding" type="range" min="0" max="{{ RemoveBackground::MAX_PADDING }}" step="4" wire:model="padding" class="mt-2 w-full accent-tool-deep">
                            @error('padding') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    @error('trim') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>

                @if($locked)
                    <p class="text-xs text-muted">Crop manual dan potong ke objek tersedia di paket berbayar. <a href="{{ route('pricing') }}" class="link">Lihat paket</a></p>
                @endif
            </div>

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
