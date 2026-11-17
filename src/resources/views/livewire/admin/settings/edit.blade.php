@php
    // Kontrak: $groups = [group => [key => [label, type, options, help, source(db|env|empty), masked, envPlaceholder, url]]].
    // Binding: values.{key} (non-secret), secrets.{key} (selalu kosong), clear.{key} (bool), uploads.{key} (file gambar). Nilai secret tidak pernah dirender.
    $groups = $groups ?? [];
    $groupMeta = [
        'general' => ['Umum', 'Identitas situs dan kontak dukungan.', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        'branding' => ['Branding', 'Logo dan favicon yang tampil di seluruh situs.', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        'mail' => ['Email / SMTP', 'Server pengirim untuk verifikasi, reset sandi, dan notifikasi.', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        'google' => ['Login Google', 'Kredensial OAuth dari Google Cloud Console.', 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
        'xendit' => ['Payment gateway', 'Pembayaran checkout dan verifikasi webhook.', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ];
    $sourceBadge = [
        'db' => ['Database', 'bg-ok/10 text-ok'],
        'env' => ['.env', 'bg-link/10 text-link'],
        'empty' => ['Kosong', 'bg-fg/6 text-muted'],
        'custom' => ['Kustom', 'bg-ok/10 text-ok'],
        'default' => ['Bawaan', 'bg-fg/6 text-muted'],
    ];
    $imageAccept = 'image/png,image/jpeg,image/webp';
    $inputType = ['email' => 'email', 'url' => 'url', 'int' => 'number'];
@endphp

<div class="mx-auto max-w-4xl space-y-6 pb-24">
    <div>
        <h2 class="text-xl font-bold text-fg">Pengaturan aplikasi</h2>
        <p class="mt-1 max-w-prose text-sm text-muted">
            Nilai di sini menimpa <code class="font-mono text-xs">.env</code>. Kosongkan kolom untuk kembali memakai nilai <code class="font-mono text-xs">.env</code>.
        </p>
    </div>

    <div class="flex gap-3 rounded-xl border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn" role="note">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <p><strong class="font-semibold">Perubahan SMTP, Google, dan payment gateway setara akses superadmin.</strong> Kredensial yang salah dapat memutus login, email, atau pembayaran. Semua perubahan dicatat di audit log.</p>
    </div>

    @if($decryptErrors ?? false)
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            Sebagian pengaturan tidak bisa dibaca (APP_KEY berubah?). Isi ulang nilai yang terdampak.
        </div>
    @endif

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            Ada {{ $errors->count() }} kolom yang perlu diperbaiki. Tidak ada perubahan yang disimpan.
        </div>
    @endif

    @if(empty($groups))
        <div class="glass px-6 py-12 text-center">
            <p class="font-medium text-fg">Belum ada pengaturan terdaftar.</p>
            <p class="mt-1 text-sm text-muted">Registry pengaturan kosong; semua nilai memakai <code class="font-mono text-xs">.env</code>.</p>
        </div>
    @else
        @php
            // Grup yang punya error (values.mail.host, secrets.x.y, uploads.branding.logo, branding.logo → grupnya).
            $errorGroups = collect($errors->keys())
                ->map(fn ($k) => explode('.', preg_replace('/^(values|secrets|clear|uploads)\./', '', $k))[0])
                ->unique()->intersect(array_keys($groups))->values()->all();
        @endphp

        {{-- Satu tab per grup; tab aktif disimpan di hash URL agar bisa dibagikan/di-reload. --}}
        <div class="space-y-6" x-data="{
                tabs: @js(array_keys($groups)),
                active: @js(array_key_first($groups)),
                init() {
                    const fromHash = location.hash.replace('#settings-', '');
                    if (this.tabs.includes(fromHash)) this.active = fromHash;
                },
                select(tab, focus = false) {
                    if (! this.tabs.includes(tab)) return;
                    this.active = tab;
                    history.replaceState(null, '', '#settings-' + tab);
                    this.$nextTick(() => {
                        const el = document.getElementById('settings-tab-' + tab);
                        el?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                        if (focus) el?.focus();
                    });
                },
                move(step) {
                    const i = this.tabs.indexOf(this.active);
                    this.select(this.tabs[(i + step + this.tabs.length) % this.tabs.length], true);
                },
            }">
            <div role="tablist" aria-label="Grup pengaturan" class="-mx-1 flex gap-1 overflow-x-auto overflow-y-hidden px-1 shadow-[inset_0_-1px_0_var(--color-line)] [scrollbar-width:thin]"
                @keydown.arrow-right.prevent="move(1)" @keydown.arrow-left.prevent="move(-1)"
                @keydown.home.prevent="select(tabs[0], true)" @keydown.end.prevent="select(tabs[tabs.length - 1], true)">
                @foreach(array_keys($groups) as $group)
                    <button type="button" role="tab" id="settings-tab-{{ $group }}" aria-controls="settings-{{ $group }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                        :aria-selected="(active === '{{ $group }}').toString()" :tabindex="active === '{{ $group }}' ? 0 : -1"
                        @click="select('{{ $group }}')"
                        class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-muted transition-colors hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ok motion-reduce:transition-none aria-selected:border-ok aria-selected:text-fg">
                        {{ $groupMeta[$group][0] ?? ucfirst($group) }}
                        @if(in_array($group, $errorGroups, true))
                            <span class="h-1.5 w-1.5 rounded-full bg-danger" aria-hidden="true"></span>
                            <span class="sr-only">(ada kolom yang perlu diperbaiki)</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @if($errorGroups !== [])
                {{-- Key berubah hanya saat daftar error berubah: buka tab error pertama sekali, tidak setiap render. --}}
                <span hidden wire:key="settings-errors-{{ md5(implode('|', $errors->keys())) }}" x-init="select(@js($errorGroups[0]))"></span>
            @endif

        <form wire:submit="save" class="space-y-6" novalidate>
            @foreach($groups as $group => $fields)
                @php
                    $meta = $groupMeta[$group] ?? [ucfirst($group), null, 'M4 6h16M4 12h16M4 18h16'];
                @endphp
                <section id="settings-{{ $group }}" role="tabpanel" tabindex="0" class="glass" aria-labelledby="settings-tab-{{ $group }}" wire:key="group-{{ $group }}"
                    x-show="active === '{{ $group }}'" @unless($loop->first) x-cloak @endunless>
                    <header class="flex items-start gap-3 border-b border-line px-5 py-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-ok/10 text-ok">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta[2] }}"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-fg">{{ $meta[0] }}</h3>
                            @if($meta[1])
                                <p class="text-sm text-muted">{{ $meta[1] }}</p>
                            @endif
                        </div>
                        @if($group === 'mail')
                            <button type="button" wire:click="sendTestMail" class="btn-secondary btn-sm shrink-0"
                                wire:loading.attr="disabled" wire:target="sendTestMail">
                                <span wire:loading.remove wire:target="sendTestMail">Kirim email uji</span>
                                <span wire:loading wire:target="sendTestMail">Mengirim…</span>
                            </button>
                        @endif
                    </header>

                    <div class="grid gap-x-5 gap-y-5 p-5 sm:grid-cols-2">
                        @foreach($fields as $key => $field)
                            @php
                                $type = data_get($field, 'type', 'string');
                                $label = data_get($field, 'label', $key);
                                $help = data_get($field, 'help');
                                $source = data_get($field, 'source', 'empty');
                                $masked = data_get($field, 'masked');
                                $envPlaceholder = data_get($field, 'envPlaceholder');
                                $options = (array) data_get($field, 'options', []);
                                $options = array_is_list($options) ? array_combine($options, $options) : $options;
                                $id = 'setting-' . str_replace('.', '-', $key);
                                $badge = $type === 'image'
                                    ? $sourceBadge[$source === 'db' ? 'custom' : 'default']
                                    : ($sourceBadge[$source] ?? $sourceBadge['empty']);
                                $errorKey = match ($type) {
                                    'secret' => "secrets.{$key}",
                                    'image' => $key,
                                    default => "values.{$key}",
                                };
                                $wide = in_array($type, ['secret', 'url'], true);
                            @endphp

                            <div wire:key="field-{{ $key }}" @class(['sm:col-span-2' => $wide])>
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <label for="{{ $id }}" class="text-sm font-medium text-fg">{{ $label }}</label>
                                    <span class="badge {{ $badge[1] }}" title="Sumber nilai aktif">{{ $badge[0] }}</span>
                                </div>

                                @if($type === 'image')
                                    @php
                                        $upload = data_get($uploads, $key);
                                        // Pratinjau hanya untuk format yang diterima (bukan SVG).
                                        $preview = $upload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                            && $upload->isPreviewable()
                                            && in_array($upload->extension(), \App\Actions\Admin\UpdateBrandAssets::MIMES, true)
                                            ? $upload->temporaryUrl() : null;
                                        $isFavicon = $key === 'branding.favicon';
                                        $uploadError = $errors->first($errorKey) ?: $errors->first("uploads.{$key}");
                                    @endphp
                                    <div class="flex items-start gap-4">
                                        <div @class(['grid h-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-fg/4 p-2', 'w-16' => $isFavicon, 'w-36' => ! $isFavicon])>
                                            @if($preview ?? $field['url'])
                                                <img src="{{ $preview ?? $field['url'] }}" alt="{{ $preview ? 'Pratinjau ' : '' }}{{ $label }}{{ $preview ? ' baru' : ' saat ini' }}"
                                                    @if($isFavicon) width="32" height="32" @else height="40" @endif
                                                    @class(['object-contain', 'h-8 w-8' => $isFavicon, 'h-10 w-auto max-w-full' => ! $isFavicon])>
                                            @elseif($isFavicon)
                                                <img src="{{ asset('favicon.ico') }}" alt="Favicon bawaan" width="32" height="32" class="h-8 w-8">
                                            @else
                                                <x-wordmark class="text-sm" />
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <input id="{{ $id }}" type="file" wire:model="uploads.{{ $key }}" accept="{{ $imageAccept }}"
                                                @class(['input text-sm file:mr-3 file:rounded-md file:border-0 file:bg-fg/6 file:px-3 file:py-1 file:text-sm file:font-medium file:text-fg', 'border-danger' => $uploadError])
                                                aria-describedby="{{ $id }}-help @if($uploadError) {{ $id }}-error @endif" @if($uploadError) aria-invalid="true" @endif>
                                            <p class="mt-1.5 text-xs text-muted">
                                                PNG, JPG, atau WebP, maks. {{ \App\Actions\Admin\UpdateBrandAssets::ASSETS[$key]['max_kb'] }} KB.
                                                <span wire:loading wire:target="uploads.{{ $key }}">Mengunggah…</span>
                                            </p>
                                            @if($source === 'db')
                                                <label class="mt-2 inline-flex items-center gap-2 text-sm text-muted">
                                                    <input type="checkbox" wire:model="clear.{{ $key }}"
                                                        class="rounded border-line text-danger focus:ring-danger">
                                                    Hapus, kembali ke bawaan
                                                </label>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($type === 'secret')
                                    @if($source !== 'empty')
                                        <p class="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                                            <span>Tersimpan</span>
                                            <span class="rounded bg-fg/6 px-1.5 py-0.5 font-mono text-fg">{{ $masked ?? '••••••••' }}</span>
                                            @if($source === 'env')
                                                <span>(dari .env)</span>
                                            @endif
                                        </p>
                                    @endif
                                    <input id="{{ $id }}" type="password" wire:model="secrets.{{ $key }}" autocomplete="new-password"
                                        spellcheck="false" placeholder="{{ $source === 'empty' ? 'Belum diatur' : 'Biarkan kosong agar tidak berubah' }}"
                                        @class(['input font-mono', 'border-danger' => $errors->has($errorKey)])
                                        @if($help) aria-describedby="{{ $id }}-help" @endif>
                                    @if($source === 'db')
                                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-muted">
                                            <input type="checkbox" wire:model="clear.{{ $key }}"
                                                class="rounded border-line text-danger focus:ring-danger">
                                            Hapus dari database (kembali ke .env)
                                        </label>
                                    @endif
                                @elseif($type === 'select')
                                    <select id="{{ $id }}" wire:model="values.{{ $key }}"
                                        @class(['input', 'border-danger' => $errors->has($errorKey)])
                                        @if($help) aria-describedby="{{ $id }}-help" @endif>
                                        <option value="">Ikuti .env{{ filled($envPlaceholder) ? ' (' . $envPlaceholder . ')' : '' }}</option>
                                        @foreach($options as $val => $optLabel)
                                            <option value="{{ $val }}">{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'bool')
                                    <label class="flex items-center gap-2 text-sm text-fg">
                                        <input id="{{ $id }}" type="checkbox" wire:model="values.{{ $key }}"
                                            class="rounded border-line text-ok focus:ring-fg"
                                            @if($help) aria-describedby="{{ $id }}-help" @endif>
                                        Aktif
                                    </label>
                                @else
                                    <input id="{{ $id }}" type="{{ $inputType[$type] ?? 'text' }}" wire:model="values.{{ $key }}"
                                        @if($type === 'int') inputmode="numeric" @endif
                                        @if(in_array($type, ['email', 'url'], true)) autocomplete="off" spellcheck="false" @endif
                                        placeholder="{{ filled($envPlaceholder) ? $envPlaceholder : 'Ikuti .env' }}"
                                        @class(['input', 'border-danger' => $errors->has($errorKey)])
                                        @if($help) aria-describedby="{{ $id }}-help" @endif>
                                @endif

                                @if($help)
                                    <p id="{{ $id }}-help" class="mt-1.5 text-xs text-muted">{{ $help }}</p>
                                @endif
                                @if($type === 'image')
                                    @if($uploadError) <p id="{{ $id }}-error" class="field-error">{{ $uploadError }}</p> @endif
                                @else
                                    @error($errorKey) <p class="field-error">{{ $message }}</p> @enderror
                                @endif
                                @if($type === 'secret')
                                    @error("clear.{$key}") <p class="field-error">{{ $message }}</p> @enderror
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- Bar aksi menempel di bawah layar: focal point halaman --}}
            <div class="sticky bottom-4 z-10">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-surface/90 px-4 py-3 shadow-lg backdrop-blur">
                    <p class="text-xs text-muted">
                        <span wire:dirty wire:target="values,secrets,clear,uploads">Ada perubahan yang belum disimpan.</span>
                        <span wire:dirty.remove wire:target="values,secrets,clear,uploads">Secret yang dikosongkan tidak diubah.</span>
                    </p>
                    <button type="submit" class="shrink-0 btn-primary transition focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="save,uploads">
                        <span wire:loading.remove wire:target="save">Simpan pengaturan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </form>
        </div>
    @endif
</div>
