
    <div class="space-y-4" x-data="{ open: false, userId: null, userEmail: '', reason: '' }"
        @keydown.escape.window="open = false">

        @if(session('status'))
            <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ session('error') }}</div>
        @endif

        {{-- Kontrol --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email…" class="input pl-9" aria-label="Cari pengguna">
            </div>
            <label for="status" class="sr-only">Filter status</label>
            <select id="status" wire:model.live="status" class="input py-2 text-sm sm:w-48">
                <option value="all">Semua status</option>
                <option value="active">Aktif</option>
                <option value="blocked">Diblokir</option>
                <option value="unverified">Belum verifikasi</option>
            </select>
        </div>

        {{-- Tabel --}}
        <div class="rounded-2xl border border-line bg-surface">
            @if($users && $users->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-medium">Pengguna</th>
                                <th scope="col" class="px-5 py-3 font-medium">Role</th>
                                <th scope="col" class="px-5 py-3 font-medium">Status</th>
                                <th scope="col" class="px-5 py-3 font-medium">Terdaftar</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @php
                                $actor = auth()->user();
                                $canBlock = $actor?->can('users.block') ?? false;
                            @endphp
                            @foreach($users as $user)
                                @php
                                    $isSuper = $user->isSuperadmin();
                                    $isSelf = $user->id === $actor?->id;
                                @endphp
                                <tr wire:key="user-{{ $user->id }}">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-fg">{{ $user->name }}</span>
                                            @if($user->two_factor_confirmed_at)
                                                <span class="badge bg-link/10 text-link" title="Autentikasi dua langkah aktif">2FA</span>
                                            @endif
                                            @if($isSuper)<span class="badge bg-ok/10 text-ok">Superadmin</span>@endif
                                        </div>
                                        <p class="text-xs text-muted">{{ $user->email }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($user->roles as $role)
                                                <span class="badge bg-fg/6 font-mono text-fg">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-xs text-muted">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($user->blocked_at)
                                            <span class="badge bg-danger/10 text-danger">Diblokir</span>
                                        @elseif(! $user->email_verified_at)
                                            <span class="badge bg-warn/10 text-warn">Belum verifikasi</span>
                                        @else
                                            <span class="badge bg-ok/10 text-ok">Aktif</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-muted">{{ $user->created_at?->translatedFormat('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                        @if(! $isSelf && ($roleEditable[$user->id] ?? false))
                                            <button type="button" wire:click="editRoles('{{ $user->id }}')" class="btn-secondary btn-sm"
                                                wire:loading.attr="disabled" wire:target="editRoles('{{ $user->id }}')">Atur role</button>
                                        @endif
                                        @if($canBlock && ! ($isSuper || $isSelf))
                                            @if($user->blocked_at)
                                                <x-confirm-button :action="'unblock(\''.$user->id.'\')'" title="Buka blokir pengguna?" :message="$user->email.' bisa masuk kembali.'"
                                                    confirm="Buka blokir" variant="primary"
                                                    class="btn-secondary btn-sm">Buka blokir</x-confirm-button>
                                            @else
                                                <button type="button" @click="userId = '{{ $user->id }}'; userEmail = @js($user->email); reason = ''; open = true"
                                                    class="btn-danger-soft btn-sm">Blokir</button>
                                            @endif
                                        @elseif($isSelf)
                                            <span class="text-xs text-muted">Anda</span>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-5 py-14 text-center text-sm text-muted">Tidak ada pengguna yang cocok.</div>
            @endif
        </div>

        @if($users)
            <div>{{ $users->links() }}</div>
        @endif

        {{-- Modal atur role --}}
        @php
            $editing = $editingUserId ? $users?->firstWhere('id', $editingUserId) : null;
            $assignable = collect($assignableRoles ?? []);
            $fixedRoles = $editing ? $editing->roles->reject(fn ($r) => $assignable->contains('id', $r->id)) : collect();
        @endphp
        <x-dashboard.modal wire:model="rolesOpen" submit="saveRoles" submit-label="Simpan role" title="Atur role"
            :description="$editing ? 'Untuk '.$editing->email.'. Anda hanya bisa memberi role yang permission-nya Anda miliki.' : null">
            <fieldset class="space-y-2">
                <legend class="sr-only">Role</legend>
                @forelse($assignable as $role)
                    <label wire:key="assign-role-{{ $role->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-3 py-2.5 text-sm hover:border-ok">
                        <input type="checkbox" value="{{ $role->id }}" wire:model="roleSelection"
                            class="rounded border-line bg-fg/6 text-ok focus:ring-fg">
                        <span class="font-mono text-fg">{{ $role->name }}</span>
                    </label>
                @empty
                    <p class="rounded-lg border border-dashed border-line px-3 py-4 text-center text-sm text-muted">Tidak ada role yang bisa Anda berikan.</p>
                @endforelse
            </fieldset>

            @if($fixedRoles->isNotEmpty())
                <p class="text-xs text-muted">Tetap (di luar wewenang Anda):
                    @foreach($fixedRoles as $role)<span class="badge ml-1 bg-fg/6 font-mono text-muted">{{ $role->name }}</span>@endforeach
                </p>
            @endif

            @error('roles') <p class="field-error">{{ $message }}</p> @enderror
            @error('roles.*') <p class="field-error">{{ $message }}</p> @enderror
        </x-dashboard.modal>

        {{-- Modal alasan blokir (state Alpine: open, userId, userEmail, reason) --}}
        <x-dashboard.modal title="Blokir pengguna">
            <p class="text-sm text-muted">Blokir <span class="font-medium text-fg" x-text="userEmail"></span>. Sesi aktif akan dihentikan.</p>
            <div>
                <label for="block-reason" class="label text-fg">Alasan</label>
                <textarea id="block-reason" x-model="reason" rows="3" class="input" placeholder="Contoh: pelanggaran ketentuan layanan"></textarea>
            </div>
            <x-slot:footer>
                <button type="button" class="btn-secondary" @click="open = false">Batal</button>
                <button type="button" class="btn-danger" @click="$wire.block(userId, reason); open = false">Blokir sekarang</button>
            </x-slot:footer>
        </x-dashboard.modal>
    </div>
