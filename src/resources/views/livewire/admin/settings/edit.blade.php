@php
    // Kontrak: $groups = [group => [key => [label, type, options, help, source(db|env|empty), masked, envPlaceholder]]].
    // Binding: values.{key} (non-secret), secrets.{key} (selalu kosong), clear.{key} (bool). Nilai secret tidak pernah dirender.
    $groups = $groups ?? [];
    $groupMeta = [
        'general' => ['Umum', 'Identitas situs dan kontak dukungan.', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        'mail' => ['Email / SMTP', 'Server pengirim untuk verifikasi, reset sandi, dan notifikasi.', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        'google' => ['Login Google', 'Kredensial OAuth dari Google Cloud Console.', 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
        'xendit' => ['Payment gateway', 'Pembayaran checkout dan verifikasi webhook.', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ];
    $sourceBadge = [
        'db' => ['Database', 'bg-ok/10 text-ok'],
        'env' => ['.env', 'bg-link/10 text-link'],
        'empty' => ['Kosong', 'bg-fg/6 text-muted'],
    ];
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
        @if(count($groups) > 1)
            <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Grup pengaturan">
                @foreach(array_keys($groups) as $group)
                    <a href="#settings-{{ $group }}" class="badge shrink-0 border border-line bg-surface px-3 py-1.5 text-muted hover:border-ok hover:text-ok">
                        {{ $groupMeta[$group][0] ?? ucfirst($group) }}
                    </a>
                @endforeach
            </nav>
        @endif

        <form wire:submit="save" class="space-y-6" novalidate>
            @foreach($groups as $group => $fields)
                @php
                    $meta = $groupMeta[$group] ?? [ucfirst($group), null, 'M4 6h16M4 12h16M4 18h16'];
                @endphp
                <section id="settings-{{ $group }}" class="glass scroll-mt-20" aria-labelledby="settings-{{ $group }}-title" wire:key="group-{{ $group }}">
                    <header class="flex items-start gap-3 border-b border-line px-5 py-4">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-ok/10 text-ok">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta[2] }}"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="settings-{{ $group }}-title" class="font-semibold text-fg">{{ $meta[0] }}</h3>
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
                                $badge = $sourceBadge[$source] ?? $sourceBadge['empty'];
                                $errorKey = $type === 'secret' ? "secrets.{$key}" : "values.{$key}";
                                $wide = in_array($type, ['secret', 'url'], true);
                            @endphp

                            <div wire:key="field-{{ $key }}" @class(['sm:col-span-2' => $wide])>
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <label for="{{ $id }}" class="text-sm font-medium text-fg">{{ $label }}</label>
                                    <span class="badge {{ $badge[1] }}" title="Sumber nilai aktif">{{ $badge[0] }}</span>
                                </div>

                                @if($type === 'secret')
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
                                @error($errorKey) <p class="field-error">{{ $message }}</p> @enderror
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
                        <span wire:dirty wire:target="values,secrets,clear">Ada perubahan yang belum disimpan.</span>
                        <span wire:dirty.remove wire:target="values,secrets,clear">Secret yang dikosongkan tidak diubah.</span>
                    </p>
                    <button type="submit" class="shrink-0 btn-primary transition focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Simpan pengaturan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
