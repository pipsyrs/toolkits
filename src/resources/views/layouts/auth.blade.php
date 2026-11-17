@props(['title' => null])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-head')
    <title>{{ $title ? $title . ' | Toolkits' : 'Toolkits' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-b from-slate-100 to-slate-50 px-4 py-12 dark:from-slate-950 dark:to-slate-900">
    <x-theme-toggle class="fixed right-4 top-4" />

    <div class="w-full max-w-md">
        <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-2 font-bold text-slate-900 dark:text-white">
            @if($logo = \App\Support\Branding::logoUrl())
                <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="40" class="h-10 w-auto max-w-48 object-contain">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-sm text-white">Tk</span>
                <span class="text-lg">Toolkits</span>
            @endif
        </a>

        <div class="card p-6 sm:p-8">
            @if($title)
                <h1 class="mb-6 text-center text-xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
            @endif
            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('legal.terms') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Ketentuan</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('legal.privacy') }}" class="hover:text-slate-600 dark:hover:text-slate-300">Privasi</a>
        </p>
    </div>
</body>
</html>
