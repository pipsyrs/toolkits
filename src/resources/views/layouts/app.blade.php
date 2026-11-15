@php
    // Tier dari backend (view composer) bila ada; fallback ke TierResolver | satu sumber aturan.
    $tier ??= app(\App\Services\Tier\TierResolver::class)->forUser(auth()->user());
    // Iklan mengikuti limit "ads" per paket (diatur admin), hanya tampil saat operasi tool.
    $showAds = (bool) (app(\App\Services\Tier\PlanLimits::class)->for($tier)['ads'] ?? ! $tier->isPaid());
    $nav = [['Alat', 'tools.index', 'tools.*'], ['Harga', 'pricing', 'pricing']];
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.theme-head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' | ToolKits' : 'ToolKits | Alat online gratis' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|bricolage-grotesque:600,800" rel="stylesheet">
    @stack('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-full flex-col">
    <a href="#main" class="sr-only z-50 rounded-md bg-tool px-3 py-2 text-sm font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-3">Lewati ke konten</a>

    <header class="relative border-b border-line bg-canvas">
        <nav class="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-4 sm:px-6" aria-label="Navigasi utama" x-data="{ open: false }">
            <a href="{{ route('home') }}" aria-label="ToolKits, beranda"><x-wordmark /></a>

            <div class="hidden items-center gap-5 text-sm font-medium sm:flex">
                @foreach($nav as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" @if(request()->routeIs($pattern)) aria-current="page" @endif
                        class="py-1 text-muted transition-colors hover:text-fg aria-[current=page]:text-fg aria-[current=page]:shadow-[inset_0_-2px_0_var(--color-tool)]">{{ $label }}</a>
                @endforeach
            </div>

            <div class="ml-auto hidden items-center gap-2 sm:flex">
                <x-theme-toggle class="mr-2" />
                @auth
                    @can('admin.access')
                        <a href="{{ route('admin.dashboard') }}" class="btn-ghost btn-sm">Admin</a>
                    @endcan
                    <a href="{{ route('dashboard') }}" class="btn-secondary btn-sm">
                        Dashboard
                        <span class="badge bg-sunken text-muted">{{ $tier->label() }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost btn-sm">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-primary btn-sm">Daftar gratis</a>
                @endauth
            </div>

            {{-- Menu mobile --}}
            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="mobile-menu"
                class="btn-ghost btn-sm ml-auto sm:hidden" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"/></svg>
            </button>
            <div id="mobile-menu" x-show="open" x-cloak @click.outside="open = false" @keydown.escape.window="open = false"
                class="absolute inset-x-0 top-16 z-40 border-b border-line bg-canvas px-4 pb-5 pt-2 sm:hidden">
                <div class="flex flex-col text-base font-medium">
                    @foreach($nav as [$label, $route, $pattern])
                        <a href="{{ route($route) }}" class="border-b border-line py-3">{{ $label }}</a>
                    @endforeach
                    @auth
                        <a href="{{ route('dashboard') }}" class="border-b border-line py-3">Dashboard <span class="text-sm font-normal text-muted">({{ $tier->label() }})</span></a>
                        @can('admin.access')
                            <a href="{{ route('admin.dashboard') }}" class="border-b border-line py-3">Admin</a>
                        @endcan
                    @else
                        <a href="{{ route('login') }}" class="border-b border-line py-3">Masuk</a>
                    @endauth
                </div>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <x-theme-toggle />
                    @guest
                        <a href="{{ route('register') }}" class="btn-primary">Daftar gratis</a>
                    @endguest
                </div>
            </div>
        </nav>
    </header>

    <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 sm:py-14">
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-10 text-sm text-muted sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div>
                <x-wordmark class="text-base" />
                <p class="mt-2 max-w-[38ch]">Pendekkan link, buat QR, olah gambar dan PDF langsung dari browser.</p>
            </div>
            <nav class="grid grid-cols-2 gap-x-10 gap-y-2" aria-label="Tautan footer">
                <a href="{{ route('tools.index') }}" class="hover:text-fg">Alat</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-fg">Ketentuan</a>
                <a href="{{ route('pricing') }}" class="hover:text-fg">Harga</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-fg">Privasi</a>
            </nav>
        </div>
        <p class="mx-auto w-full max-w-6xl px-4 pb-8 text-xs text-muted sm:px-6">&copy; {{ date('Y') }} ToolKits</p>
    </footer>

    @if($showAds)
        <x-ad-gate :seconds="config('toolkits.ad_gate_seconds', 5)" />
    @endif

    @livewireScripts
</body>
</html>
