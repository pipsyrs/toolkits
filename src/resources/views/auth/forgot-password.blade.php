<x-layouts::auth title="Lupa kata sandi">
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Masukkan emailmu. Kami akan mengirim tautan untuk mengatur ulang kata sandi.
    </p>

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200" role="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="input @error('email') border-rose-400 @enderror">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full">Kirim tautan reset</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('login') }}" class="link">Kembali ke masuk</a>
    </p>
</x-layouts::auth>
