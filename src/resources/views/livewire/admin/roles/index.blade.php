@php
    // Kontrak: $roles (withCount users, permissions), $editable [id => bool], delete(id).
    $roles = $roles ?? collect();
    $editable = $editable ?? [];
@endphp

<div class="space-y-5" x-data="{ open: false, roleId: null, roleName: '' }" @keydown.escape.window="open = false">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-fg">Role & permission</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">Role menentukan menu dan aksi admin yang boleh dipakai. Anda hanya bisa memberi permission yang Anda miliki.</p>
        </div>
        <a href="{{ route('admin.roles.create') }}"
            class="shrink-0 btn-primary">
            + Role baru
        </a>
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
                                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn-secondary btn-sm">Edit</a>
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

    {{-- Konfirmasi hapus --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-role-title">
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-xl" x-trap.noscroll="open">
            <h2 id="delete-role-title" class="text-lg font-semibold text-fg">Hapus role</h2>
            <p class="mt-1 text-sm text-muted">Role <span class="font-mono font-medium text-fg" x-text="roleName"></span> akan dihapus permanen. Tindakan ini dicatat di audit log.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="open = false">Batal</button>
                <button type="button" class="btn-danger" @click="$wire.delete(roleId); open = false">Hapus role</button>
            </div>
        </div>
    </div>
</div>
