<x-layouts::auth title="Masuk ke akun">
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200" role="status">
            {{ session('status') }}
        </div>
    @endif

    @error('google')
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" role="alert">
            {{ $message }}
        </div>
    @enderror

    <x-google-button remember-from="remember" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="input @error('email') border-rose-400 @enderror" aria-describedby="email-err">
            @error('email') <p id="email-err" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="input @error('password') border-rose-400 @enderror" aria-describedby="password-err">
            @error('password') <p id="password-err" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" id="remember" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                Ingat saya
            </label>
            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="link text-sm">Lupa sandi?</a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full">Masuk</button>
    </form>

    @if(Route::has('register'))
        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
            Belum punya akun? <a href="{{ route('register') }}" class="link">Daftar gratis</a>
        </p>
    @endif
</x-layouts::auth>
