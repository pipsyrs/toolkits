<x-layouts::auth title="Verifikasi email">
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Terima kasih sudah mendaftar. Sebelum mulai, mohon verifikasi emailmu lewat tautan yang baru kami kirim. Tidak menerima? Kami bisa kirim ulang.
    </p>

    @if(session('status') === 'verification-link-sent')
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200" role="status">
            Tautan verifikasi baru telah dikirim ke emailmu.
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-primary w-full">Kirim ulang tautan verifikasi</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost w-full">Keluar</button>
        </form>
    </div>
</x-layouts::auth>
