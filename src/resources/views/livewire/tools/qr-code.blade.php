@php
    use App\Enums\QrFrame;
    use App\Enums\QrTemplate;
    use App\Services\Tier\Entitlements;
    use App\Services\Tier\PlanLimits;

    $locked = $this->locked;
    // Nama paket pemilik tiap fitur berbayar untuk badge; null = tak ada paket yang membukanya.
    $planNames = collect(['svg', 'premium_template', 'premium_frame', 'custom_color'])
        ->mapWithKeys(fn ($f) => [$f => app(PlanLimits::class)->planForLimit(Entitlements::toolKey('qr-code', $f))])->all();
    $colorFields = ['colorDark' => 'Modul', 'colorEye' => 'Sudut', 'colorBg' => 'Latar', 'colorFrame' => 'Bingkai'];
    $styleTargets = 'text,ecc,template,frame,frameTitle,frameFooter,colorDark,colorEye,colorBg,colorFrame,resetColors';
@endphp

<x-tool-layout slug="qr-code" :steps="[
    ['Isi teks atau tautan', 'Pratinjau tampilan langsung muncul di samping.'],
    ['Pilih desain', 'Template, warna, dan bingkai sesuai merekmu.'],
    ['Buat dan unduh', 'PNG untuk umum, SVG untuk cetak tajam.'],
]">
    @error('quota')
        <div class="rounded-md border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            {{ $message }}
        </div>
    @enderror

    <form id="qr-form" wire:submit="generate" class="space-y-4">
        <div class="rounded-lg border border-line bg-surface p-4">
            <label for="text" class="label">Teks atau tautan</label>
            <textarea id="text" wire:model.live.debounce.400ms="text" rows="3" maxlength="{{ (int) config('toolkits.qr.max_text') }}" placeholder="https://contoh.com"
                @class(['input', 'border-danger' => $errors->has('text')])></textarea>
            @error('text') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        {{-- Template --}}
        <fieldset class="rounded-lg border border-line bg-surface p-4">
            <legend class="label">Template desain</legend>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                @foreach(QrTemplate::cases() as $option)
                    @php($isLocked = $option->isPro() && $locked['premium_template'])
                    <label class="option-tile flex-col items-center gap-1.5 p-2">
                        <input type="radio" wire:model.live="template" value="{{ $option->value }}" @disabled($isLocked) class="sr-only">
                        <x-qr-swatch :template="$option" class="h-12 w-12" />
                        <span class="font-medium">{{ $option->label() }}</span>
                        @if($option->isPro()) <span class="absolute right-1 top-1"><x-plan-badge :plan="$planNames['premium_template']" /></span> @endif
                    </label>
                @endforeach
            </div>
            @error('template') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        {{-- Warna --}}
        <fieldset class="rounded-lg border border-line bg-surface p-4">
            {{-- Tombol di dalam legend: posisi absolute di fieldset jatuh ke area isi dan menutupi kotak warna. --}}
            <legend class="label flex min-h-8 w-full items-center justify-between gap-2">
                <span class="flex items-center gap-2">Warna @if($locked['custom_color']) <x-plan-badge :plan="$planNames['custom_color']" /> @endif</span>
                @unless($locked['custom_color'])
                    <button type="button" wire:click="resetColors" class="btn-ghost btn-sm">Kembalikan warna template</button>
                @endunless
            </legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($colorFields as $property => $label)
                    <label @class(['flex items-center gap-2 rounded-md border border-line bg-surface px-2.5 py-2 text-sm', 'cursor-not-allowed opacity-55' => $locked['custom_color'], 'cursor-pointer hover:border-line-strong' => ! $locked['custom_color']])>
                        <input type="color" wire:model.live.debounce.300ms="{{ $property }}" @disabled($locked['custom_color'])
                            class="h-7 w-9 shrink-0 cursor-[inherit] rounded border border-line bg-transparent p-0">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @if($locked['custom_color'])
                <p class="mt-1.5 text-xs text-muted">Warna mengikuti template. Upgrade ke Pro untuk memilih warna sendiri.</p>
            @elseif($this->lowContrast)
                <p class="mt-1.5 text-xs font-medium text-warn" role="status">Modul kurang kontras dengan latar, QR bisa gagal dipindai. Pakai warna modul yang lebih gelap.</p>
            @endif
            @error('colors') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        {{-- Bingkai --}}
        <fieldset class="rounded-lg border border-line bg-surface p-4">
            <legend class="label">Bingkai</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach(QrFrame::cases() as $option)
                    @php($isLocked = $option->isPro() && $locked['premium_frame'])
                    <label class="option-tile items-center justify-center gap-2 px-3 py-2.5">
                        <input type="radio" wire:model.live="frame" value="{{ $option->value }}" @disabled($isLocked) class="sr-only">
                        <span class="font-medium">{{ $option->label() }}</span>
                        @if($option->isPro()) <x-plan-badge :plan="$planNames['premium_frame']" /> @endif
                    </label>
                @endforeach
            </div>
            @error('frame') <p class="field-error">{{ $message }}</p> @enderror

            @if($frame !== QrFrame::None->value)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="frameTitle" class="label">Judul</label>
                        <input id="frameTitle" type="text" wire:model.live.debounce.500ms="frameTitle" maxlength="{{ \App\Services\Qr\QrStyle::MAX_FRAME_TEXT }}" placeholder="Scan untuk melihat menu"
                            @class(['input', 'border-danger' => $errors->has('frameTitle')])>
                        @error('frameTitle') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="frameFooter" class="label">Footer</label>
                        <input id="frameFooter" type="text" wire:model.live.debounce.500ms="frameFooter" maxlength="{{ \App\Services\Qr\QrStyle::MAX_FRAME_TEXT }}" placeholder="tokokamu.id"
                            @class(['input', 'border-danger' => $errors->has('frameFooter')])>
                        @error('frameFooter') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </fieldset>

        <div class="grid gap-4 rounded-lg border border-line bg-surface p-4 sm:grid-cols-2">
            <fieldset>
                <legend class="label">Format</legend>
                <div class="flex gap-2">
                    <label class="option-tile flex-1 items-center justify-center px-3 py-2">
                        <input type="radio" wire:model.live="format" value="png" class="sr-only"> PNG
                    </label>
                    <label class="option-tile flex-1 items-center justify-center gap-2 px-3 py-2">
                        <input type="radio" wire:model.live="format" value="svg" @disabled($locked['svg']) class="sr-only">
                        SVG @if($locked['svg']) <x-plan-badge :plan="$planNames['svg']" /> @endif
                    </label>
                </div>
                @error('format') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label for="ecc" class="label">Koreksi kesalahan</label>
                <select id="ecc" wire:model.live="ecc" class="input">
                    <option value="L">Rendah (L)</option>
                    <option value="M">Sedang (M)</option>
                    <option value="Q">Tinggi (Q)</option>
                    <option value="H">Tertinggi (H)</option>
                </select>
            </div>
        </div>

        <div class="rounded-lg border border-line bg-surface p-4">
            <label for="size" class="label">Ukuran QR <span class="font-normal text-muted">(<span x-text="$wire.size"></span> px, belum termasuk bingkai)</span></label>
            <input id="size" type="range" min="200" max="1000" step="50" wire:model.live.debounce.300ms="size" class="w-full accent-tool-deep">
        </div>
    </form>

    <x-slot:aside>
        <div class="tool-panel space-y-4 p-5">
            <p class="tool-panel-title">Hasil</p>
            @if($dataUri)
                <div class="checker grid place-items-center rounded-lg border border-line p-4">
                    <img src="{{ $dataUri }}" alt="QR code hasil" class="h-auto max-h-[28rem] w-auto max-w-full" width="300">
                </div>
                <a href="{{ $dataUri }}" download="qrcode.{{ $format === 'svg' ? 'svg' : 'png' }}" class="btn-primary w-full">
                    Unduh {{ $format === 'svg' ? 'SVG' : 'PNG' }}
                </a>
            @elseif($previewUri)
                <div class="checker relative grid place-items-center rounded-lg border border-line p-4" data-no-save>
                    <img src="{{ $previewUri }}" alt="Pratinjau tampilan QR code (belum bisa dipindai)" class="h-auto max-h-[28rem] w-auto max-w-full" width="240">
                    <span wire:loading wire:target="{{ $styleTargets }}" class="badge absolute right-2 top-2 bg-ink/75 text-white">Memperbarui…</span>
                </div>
                <p class="text-sm text-muted">Pratinjau tampilan, belum bisa dipindai. Klik <strong class="text-fg">Buat QR</strong> untuk QR asli ukuran {{ $size }} px.</p>
            @else
                <div class="grid aspect-square place-items-center rounded-lg border-2 border-dashed border-line text-line">
                    <svg class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 4h2m4 0h.01M14 14h.01M20 14v.01M18 20h2v-2"/></svg>
                </div>
                <p class="text-sm text-muted">Ketik teks atau tautan, pratinjau QR langsung muncul di sini.</p>
            @endif
            <button type="submit" form="qr-form" @class(['w-full', 'btn-secondary' => $dataUri, 'btn-primary' => ! $dataUri]) wire:loading.attr="disabled" wire:target="generate">
                <span wire:loading.remove wire:target="generate">{{ $dataUri ? 'Buat ulang' : 'Buat QR' }}</span>
                <span wire:loading wire:target="generate">Membuat…</span>
            </button>
        </div>
    </x-slot:aside>
</x-tool-layout>
