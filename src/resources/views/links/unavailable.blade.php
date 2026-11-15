@php($reason = $reason ?? 'not_found')
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-head')
    <meta name="robots" content="noindex,nofollow">
    <title>Tautan tidak tersedia</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-10 dark:bg-slate-950">
    <div class="w-full max-w-md text-center">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-2 font-bold text-slate-900 dark:text-white">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm text-white">Tk</span>
            Toolkits
        </a>

        <div class="card p-8">
            <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1M3 3l18 18"/></svg>
            </div>

            @if($reason === 'expired')
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Tautan sudah kedaluwarsa</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Masa aktif tautan ini telah berakhir dan tidak lagi dapat diakses.</p>
            @else
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Tautan tidak tersedia</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tautan yang kamu tuju tidak ditemukan atau telah dinonaktifkan.</p>
            @endif

            <a href="{{ route('home') }}" class="btn-primary mt-6 w-full">Kembali ke beranda</a>
        </div>
    </div>
</body>
</html>
