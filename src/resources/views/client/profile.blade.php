@php
    $user = auth()->user();
    $hasPassword = (bool) $user?->hasPassword();
@endphp

<x-layouts::client title="Profil">
    <div class="mx-auto max-w-2xl space-y-6">
        @if(session('status') === 'profile-information-updated')
            <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">Profil diperbarui.</div>
        @endif
        @if(session('status') === 'password-updated')
            <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">Kata sandi diperbarui.</div>
        @endif

        @error('google')
            <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
        @enderror

        {{-- Informasi profil --}}
        <section class="glass p-6">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-fg">Informasi profil</h2>
                @if($user?->google_id)
                    <span class="badge border border-line bg-surface text-muted">Terhubung dengan Google</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted">Perbarui nama dan email akunmu.</p>

            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="label">Nama</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required autocomplete="name"
                        class="input @error('name', 'updateProfileInformation') border-danger @enderror">
                    @error('name', 'updateProfileInformation') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required autocomplete="username"
                        class="input @error('email', 'updateProfileInformation') border-danger @enderror">
                    @error('email', 'updateProfileInformation') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </section>

        {{-- Ubah kata sandi --}}
        <section class="glass p-6">
            <h2 class="text-lg font-semibold text-fg">{{ $hasPassword ? 'Ubah kata sandi' : 'Atur kata sandi' }}</h2>
            <p class="mt-1 text-sm text-muted">
                {{ $hasPassword ? 'Gunakan kata sandi yang panjang dan unik.' : 'Akunmu masuk lewat Google. Atur kata sandi agar bisa masuk dengan email juga.' }}
            </p>

            <form method="POST" action="{{ route('user-password.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                @if($hasPassword)
                    <div>
                        <label for="current_password" class="label">Kata sandi saat ini</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                            class="input @error('current_password', 'updatePassword') border-danger @enderror">
                        @error('current_password', 'updatePassword') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="new_password" class="label">Kata sandi baru</label>
                    <input id="new_password" name="password" type="password" autocomplete="new-password"
                        class="input @error('password', 'updatePassword') border-danger @enderror">
                    @error('password', 'updatePassword') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="label">Ulangi kata sandi baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="input">
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn-primary">{{ $hasPassword ? 'Perbarui kata sandi' : 'Atur kata sandi' }}</button>
                </div>
            </form>
        </section>

        {{-- Autentikasi dua langkah (Fortify) --}}
        @include('client.partials.two-factor')
    </div>
</x-layouts::client>
