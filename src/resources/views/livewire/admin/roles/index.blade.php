@php
    // Kontrak: $roles (withCount users, permissions), $editable [id => bool], delete(id); form modal: $editing, $catalog, $grantable, $sensitive, $formLocked.
    $groupLabels = [
        'admin' => 'Area admin', 'users' => 'Pengguna', 'links' => 'Link', 'plans' => 'Plan',
        'audit' => 'Audit log', 'roles' => 'Role', 'settings' => 'Pengaturan', 'payments' => 'Pembayaran', 'tools' => 'Alat', 'support' => 'Customer service',
    ];
@endphp

<div class="space-y-5" x-data="{ open: false, roleId: null, roleName: '' }" @keydown.escape.window="open = false">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-fg">Role & permission</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">Role menentukan menu dan aksi admin yang boleh dipakai. Anda hanya bisa memberi permission yang Anda miliki.</p>
        </div>
        <button type="button" class="shrink-0 btn-primary" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
            + Role baru
        </button>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ session('error') }}</div>
    @endif
    @error('role')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
    @enderror
    @error('list')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="glass" wire:loading.class="opacity-60" wire:target="delete">
        @if($roles->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Role</th>
                            <th scope="col" class="px-5 py-3 font-medium">Pengguna</th>
                            <th scope="col" class="px-5 py-3 font-medium">Permission</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($roles as $role)
                            @php
                                $locked = $role->name === 'superadmin';
                                $canEdit = ! $locked && ($editable[$role->id] ?? false);
                                $usersCount = (int) ($role->users_count ?? 0);
                            @endphp
                            <tr wire:key="role-{{ $role->id }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-medium text-fg">{{ $role->name }}</span>
                                        @unless($canEdit)
                                            <span class="badge bg-warn/10 text-warn">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                Terkunci
                                            </span>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-5 py-3 tabular-nums text-muted">{{ $usersCount }}</td>
                                <td class="px-5 py-3 tabular-nums text-muted">
                                    {{ $locked ? 'Semua' : (int) ($role->permissions_count ?? 0) }}
                                </td>
                                <td class="px-5 py-3">
                                    @if($canEdit)
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn-secondary btn-sm" wire:click="openEdit('{{ $role->id }}')">Edit</button>
                                            @if($usersCount > 0)
                                                <button type="button" class="btn-ghost btn-sm" disabled
                                                    title="Masih dipakai {{ $usersCount }} pengguna">Hapus</button>
                                            @else
                                                <button type="button" class="btn-sm rounded-lg px-3 py-1.5 font-semibold text-danger hover:bg-danger/10"
                                                    @click="roleId = @js($role->id); roleName = @js($role->name); open = true">Hapus</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="block text-right text-xs text-muted">{{ $locked ? 'Akses penuh' : 'Di luar wewenang Anda' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-5 py-14 text-center">
                <p class="font-medium text-fg">Belum ada role.</p>
                <p class="mt-1 text-sm text-muted">Buat role untuk membagi akses admin, misalnya moderator link.</p>
            </div>
        @endif
    </div>

    {{-- Konfirmasi hapus (state Alpine: open, roleId, roleName) --}}
    <x-dashboard.modal title="Hapus role">
        <p class="text-sm text-muted">Role <span class="font-mono font-medium text-fg" x-text="roleName"></span> akan dihapus permanen. Tindakan ini dicatat di audit log.</p>
        <x-slot:footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="button" class="btn-danger" @click="$wire.delete(roleId); open = false">Hapus role</button>
        </x-slot:footer>
    </x-dashboard.modal>

    {{-- Form buat / edit role --}}
    <x-dashboard.modal wire:model="formOpen" size="xl" :submit="$formLocked ? null : 'save'" :submit-label="$editing ? 'Simpan role' : 'Buat role'"
        :title="$editing ? 'Edit role' : 'Role baru'"
        description="Permission yang tidak Anda miliki tidak bisa diberikan. Memilih permission admin apa pun otomatis menyertakan Akses panel admin.">
        <div class="space-y-4"
            x-data
            @change="
                const t = $event.target;
                if (t.name !== 'perm' || !t.checked || t.value === 'admin.access') return;
                const a = $root.querySelector('input[name=perm][value=\'admin.access\']');
                if (a && !a.checked && !a.disabled) { a.checked = true; a.dispatchEvent(new Event('change', { bubbles: false })); }
            ">
            @if($formLocked)
                <div class="flex gap-3 rounded-xl border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn" role="note">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    @if($name === 'superadmin')
                        <p>Role <strong class="font-mono">superadmin</strong> terkunci dan selalu memiliki semua permission.</p>
                    @else
                        <p>Role ini tidak bisa Anda ubah: Anda memegang role ini, atau role berisi permission yang tidak Anda miliki.</p>
                    @endif
                </div>
            @endif

            <div>
                <label for="role-name" class="label">Nama role</label>
                <input id="role-name" type="text" wire:model="name" maxlength="40" autocomplete="off" spellcheck="false"
                    placeholder="contoh: moderator" aria-describedby="role-name-help @error('name') role-name-error @enderror"
                    @disabled($formLocked)
                    @class(['input font-mono', 'border-danger' => $errors->has('name')])>
                <p id="role-name-help" class="mt-1.5 text-xs text-muted">3–40 karakter: huruf kecil, angka, <code>-</code> atau <code>_</code>.</p>
                @error('name') <p id="role-name-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <section class="rounded-xl border border-line" aria-labelledby="perm-title">
                <header class="border-b border-line px-4 py-3">
                    <h3 id="perm-title" class="font-semibold text-fg">Permission</h3>
                    @error('selected') <p class="field-error">{{ $message }}</p> @enderror
                </header>

                <div class="divide-y divide-line">
                    @foreach($catalog as $group => $permissions)
                        <fieldset class="grid gap-3 px-4 py-4 sm:grid-cols-[10rem_1fr]" wire:key="perm-group-{{ $group }}">
                            <legend class="contents">
                                <span class="text-sm font-medium text-fg">{{ $groupLabels[$group] ?? ucfirst($group) }}</span>
                            </legend>
                            <div class="space-y-2">
                                @foreach($permissions as $value => $label)
                                    @php
                                        $canGrant = ! $formLocked && in_array($value, $grantable, true);
                                        $permId = 'perm-' . str_replace('.', '-', $value);
                                    @endphp
                                    <label for="{{ $permId }}" wire:key="perm-{{ $value }}"
                                        @class([
                                            'flex items-start gap-3 rounded-lg border px-3 py-2.5 text-sm transition',
                                            'border-line hover:border-ok cursor-pointer' => $canGrant,
                                            'border-dashed border-line opacity-60 cursor-not-allowed' => ! $canGrant,
                                        ])
                                        @unless($canGrant) title="{{ $formLocked ? 'Role terkunci' : 'Anda tidak memiliki permission ini' }}" @endunless>
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
                                                @unless($canGrant || $formLocked)
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
            </section>
        </div>
    </x-dashboard.modal>
</div>
