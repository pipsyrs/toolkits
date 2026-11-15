@php($seconds = $seconds ?? config('toolkits.interstitial_seconds', 5))
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-head')
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="refresh" content="{{ $seconds }};url={{ $targetUrl }}">
    <title>Mengalihkan…</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-10 dark:bg-slate-950">
    <div class="w-full max-w-lg text-center">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-2 font-bold text-slate-900 dark:text-white">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm text-white">Tk</span>
            Toolkits
        </a>

        <div class="card p-8">
            {{-- Kontrak backend: $displayHost (punycode/ASCII), $isIdn (bool), $unicodeHost (opsional) — cegah homograph --}}
            <p class="text-sm text-slate-500 dark:text-slate-400">Kamu akan dialihkan ke domain:</p>
            <p class="mt-2 break-all font-mono text-lg font-semibold text-slate-900 dark:text-white">{{ $displayHost ?? '' }}</p>
            @if($isIdn ?? false)
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-left text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" role="alert">
                    <p class="font-semibold">Domain memakai karakter internasional</p>
                    @if($unicodeHost ?? null)
                        <p class="mt-0.5 break-all">Tampil sebagai: {{ $unicodeHost }}</p>
                    @endif
                    <p class="mt-0.5">Pastikan ini domain yang kamu maksud sebelum melanjutkan.</p>
                </div>
            @endif

            <div class="my-6 grid place-items-center">
                <div class="grid h-20 w-20 place-items-center rounded-full border-4 border-brand-100 text-2xl font-bold text-brand-600 dark:border-brand-600/30">
                    <span id="count" aria-live="polite">{{ $seconds }}</span>
                </div>
            </div>

            <a href="{{ $targetUrl }}" rel="nofollow noopener" class="btn-primary w-full">Lanjut sekarang</a>
            <a href="{{ route('home') }}" class="mt-2 block text-xs text-slate-500 dark:text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Batal</a>
        </div>

        <x-ad-slot position="interstitial" class="mt-6" />
    </div>

    <script>
        (function () {
            var n = {{ (int) $seconds }};
            var el = document.getElementById('count');
            var target = @js($targetUrl);
            var t = setInterval(function () {
                n -= 1;
                if (el) el.textContent = n < 0 ? 0 : n;
                if (n <= 0) { clearInterval(t); window.location.href = target; }
            }, 1000);
        })();
    </script>
</body>
</html>
