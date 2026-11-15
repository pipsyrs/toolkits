<x-layouts::auth title="Buat akun gratis">
    <x-google-button label="Daftar dengan Google" />

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="label">Nama</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="input @error('name') border-rose-400 @enderror">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                class="input @error('email') border-rose-400 @enderror">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="input @error('password') border-rose-400 @enderror">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="input">
        </div>

        <button type="submit" class="btn-primary w-full">Daftar</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Sudah punya akun? <a href="{{ route('login') }}" class="link">Masuk</a>
    </p>
</x-layouts::auth>
