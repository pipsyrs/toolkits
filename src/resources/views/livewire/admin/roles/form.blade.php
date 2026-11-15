@php
    // Kontrak: $name, $selected (array permission), save(); render $catalog [group => [value => label]], $grantable, $sensitive, $locked, $isEdit.
    $catalog = $catalog ?? [];
    $grantable = collect($grantable ?? [])->all();
    $locked = (bool) ($locked ?? false);
    $isEdit = (bool) ($isEdit ?? false);
    $groupLabels = [
        'admin' => 'Area admin', 'users' => 'Pengguna', 'links' => 'Link', 'plans' => 'Plan',
        'audit' => 'Audit log', 'roles' => 'Role', 'settings' => 'Pengaturan', 'payments' => 'Pembayaran', 'tools' => 'Alat',
    ];
    $sensitive = $sensitive ?? ['settings.manage', 'roles.manage', 'users.roles'];
@endphp

<div class="mx-auto max-w-3xl space-y-6 pb-24">
    <div>
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-muted hover:text-fg">&larr; Semua role</a>
        <h2 class="mt-2 text-xl font-bold text-fg">{{ $isEdit ? 'Edit role' : 'Role baru' }}</h2>
        <p class="mt-1 max-w-prose text-sm text-muted">Permission yang tidak Anda miliki tidak bisa diberikan. Memilih permission admin apa pun otomatis menyertakan "Akses area admin".</p>
    </div>

    @if($locked)
        <div class="flex gap-3 rounded-xl border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn" role="note">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            @if($name === 'superadmin')
                <p>Role <strong class="font-mono">superadmin</strong> terkunci dan selalu memiliki semua permission.</p>
            @else
                <p>Role ini tidak bisa Anda ubah: Anda memegang role ini, atau role berisi permission yang tidak Anda miliki.</p>
            @endif
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form wire:submit="save" class="space-y-6" novalidate
        x-data
        @change="
            const t = $event.target;
            if (t.name !== 'perm' || !t.checked || t.value === 'admin.access') return;
            const a = $root.querySelector('input[name=perm][value=\'admin.access\']');
            if (a && !a.checked && !a.disabled) { a.checked = true; a.dispatchEvent(new Event('change', { bubbles: false })); }
        ">
        <section class="glass p-5">
            <label for="role-name" class="label">Nama role</label>
            <input id="role-name" type="text" wire:model="name" maxlength="40" autocomplete="off" spellcheck="false"
                placeholder="contoh: moderator" aria-describedby="role-name-help"
                @disabled($locked)
                @class(['input font-mono', 'border-danger' => $errors->has('name')])>
            <p id="role-name-help" class="mt-1.5 text-xs text-muted">3–40 karakter: huruf kecil, angka, <code>-</code> atau <code>_</code>.</p>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </section>

        <section class="glass" aria-labelledby="perm-title">
            <header class="border-b border-line px-5 py-4">
                <h3 id="perm-title" class="font-semibold text-fg">Permission</h3>
                @error('permissions') <p class="field-error">{{ $message }}</p> @enderror
                @error('permissions.*') <p class="field-error">{{ $message }}</p> @enderror
                @error('selected') <p class="field-error">{{ $message }}</p> @enderror
                @error('selected.*') <p class="field-error">{{ $message }}</p> @enderror
            </header>

            @if(empty($catalog))
                <p class="px-5 py-10 text-center text-sm text-muted">Katalog permission kosong.</p>
            @else
                <div class="divide-y divide-line">
                    @foreach($catalog as $group => $permissions)
                        <fieldset class="grid gap-3 px-5 py-4 sm:grid-cols-[10rem_1fr]" wire:key="perm-group-{{ $group }}">
                            <legend class="contents">
                                <span class="text-sm font-medium text-fg">{{ $groupLabels[$group] ?? ucfirst($group) }}</span>
                            </legend>
                            <div class="space-y-2">
                                @foreach($permissions as $value => $label)
                                    @php
                                        $canGrant = ! $locked && in_array($value, $grantable, true);
                                        $permId = 'perm-' . str_replace('.', '-', $value);
                                    @endphp
                                    <label for="{{ $permId }}" wire:key="perm-{{ $value }}"
                                        @class([
                                            'flex items-start gap-3 rounded-lg border px-3 py-2.5 text-sm transition',
                                            'border-line hover:border-ok cursor-pointer' => $canGrant,
                                            'border-dashed border-line opacity-60 cursor-not-allowed' => ! $canGrant,
                                        ])
                                        @unless($canGrant) title="{{ $locked ? 'Role terkunci' : 'Anda tidak memiliki permission ini' }}" @endunless>
                                        <input id="{{ $permId }}" type="checkbox" name="perm" value="{{ $value }}" wire:model="selected"
                                            @disabled(! $canGrant)
                                            class="mt-0.5 rounded border-line text-ok focus:ring-fg">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-fg">{{ $label }}</span>
                                                @if(in_array($value, $sensitive, true))
                                                    <span class="badge bg-danger/10 text-danger" title="Setara akses tinggi; berikan hanya ke orang tepercaya">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                        sensitif
                                                    </span>
                                                @endif
                                                @unless($canGrant || $locked)
                                                    <span class="badge bg-fg/6 text-muted">tidak dimiliki</span>
                                                @endunless
                                            </span>
                                            <span class="block font-mono text-xs text-muted">{{ $value }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @endif
        </section>

        @unless($locked)
            <div class="sticky bottom-4 z-10">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-surface/90 px-4 py-3 shadow-lg backdrop-blur">
                    <a href="{{ route('admin.roles.index') }}" class="btn-ghost btn-sm">Batal</a>
                    <button type="submit" class="shrink-0 btn-primary transition disabled:opacity-50"
                        wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Simpan role' : 'Buat role' }}</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </div>
        @endunless
    </form>
</div>
