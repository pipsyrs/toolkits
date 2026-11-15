<x-layouts::auth title="Verifikasi dua langkah">
    {{-- Toggle kode/recovery: vanilla JS, layout auth tanpa Alpine --}}
    @php($useRecovery = $errors->has('recovery_code'))

    <div data-tf-panel="code" @if($useRecovery) hidden @endif>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Buka aplikasi authenticator dan masukkan kode 6 digit untuk akunmu.</p>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="label">Kode autentikasi</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                    placeholder="123456" @unless($useRecovery) autofocus @endunless
                    class="input text-center font-mono text-lg tracking-[0.4em] @error('code') border-rose-400 @enderror">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full">Verifikasi</button>
        </form>
    </div>

    <div data-tf-panel="recovery" @unless($useRecovery) hidden @endunless>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Masukkan salah satu kode pemulihan yang kamu simpan. Kode hanya berlaku sekali.</p>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="recovery_code" class="label">Kode pemulihan</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" autocapitalize="off" spellcheck="false"
                    placeholder="xxxxxxxxxx-xxxxxxxxxx" @if($useRecovery) autofocus @endif
                    class="input font-mono @error('recovery_code') border-rose-400 @enderror">
                @error('recovery_code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full">Verifikasi</button>
        </form>
    </div>

    <button type="button" data-tf-toggle class="link mt-5 block w-full text-center text-sm"
        data-label-code="Pakai kode pemulihan" data-label-recovery="Pakai kode authenticator">
        {{ $useRecovery ? 'Pakai kode authenticator' : 'Pakai kode pemulihan' }}
    </button>

    <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
        <a href="{{ route('login') }}" class="hover:text-slate-700 dark:hover:text-slate-300">Kembali ke halaman masuk</a>
    </p>
</x-layouts::auth>
