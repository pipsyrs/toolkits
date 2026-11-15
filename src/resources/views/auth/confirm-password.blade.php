<x-layouts::auth title="Konfirmasi identitas">
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Ini area aman. Mohon konfirmasi identitasmu untuk melanjutkan.
    </p>

    @error('google')
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if(auth()->user()?->hasPassword())
        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="label">Kata sandi</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                    class="input @error('password') border-rose-400 @enderror">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full">Konfirmasi</button>
        </form>
    @else
        {{-- Akun Google-only: konfirmasi ulang lewat Google --}}
        <x-google-button label="Konfirmasi dengan Google" :href="route('auth.google.confirm')" :divider="false" />
    @endif
</x-layouts::auth>
