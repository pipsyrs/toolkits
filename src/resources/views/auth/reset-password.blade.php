<x-layouts::auth title="Atur ulang kata sandi">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                class="input @error('email') border-rose-400 @enderror">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="label">Kata sandi baru</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="input @error('password') border-rose-400 @enderror">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Ulangi kata sandi baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="input">
        </div>

        <button type="submit" class="btn-primary w-full">Simpan kata sandi</button>
    </form>
</x-layouts::auth>
