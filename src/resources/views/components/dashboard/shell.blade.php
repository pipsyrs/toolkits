@props([
    'title' => null,
    'documentTitle',
    // list of ['heading' => ?string, 'items' => list of ['label', 'route', 'pattern', 'icon', 'permission' => null]]
    'nav' => [],
    'navLabel',
    'area' => null,
])

{{-- Kerangka dashboard (admin & client): sidebar kaca yang bisa diciutkan, topbar, latar pegboard. --}}
<!DOCTYPE html>
<html lang="id" class="group/root h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.theme-head')
    {{-- Sidebar diciutkan sebelum render pertama agar tidak berkedip --}}
    <script>try { if (localStorage.getItem('tk.sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch (e) {}</script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|bricolage-grotesque:600,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>@view-transition { navigation: auto; }</style>
</head>
<body class="min-h-dvh text-fg" x-data="{
        sidebar: false,
        collapsed: document.documentElement.dataset.sidebar === 'collapsed',
        openSidebar() { this.sidebar = true; document.body.classList.add('overflow-hidden'); this.$nextTick(() => this.$refs.sidebarClose?.focus()); },
        closeSidebar(restoreFocus = true) {
            if (! this.sidebar) return;
            this.sidebar = false;
            document.body.classList.remove('overflow-hidden');
            if (restoreFocus) this.$nextTick(() => this.$refs.sidebarToggle?.focus());
        },
        toggleCollapsed() {
            this.collapsed = ! this.collapsed;
            if (this.collapsed) document.documentElement.dataset.sidebar = 'collapsed';
            else delete document.documentElement.dataset.sidebar;
            try { localStorage.setItem('tk.sidebar', this.collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        },
    }"
    @keydown.escape.window="closeSidebar()"
    @resize.window.debounce.150ms="if (window.innerWidth >= 1024) closeSidebar(false)">
    <div class="dash-backdrop" aria-hidden="true"></div>
    <a href="#main" class="sr-only z-50 rounded-md bg-tool px-3 py-2 text-sm font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-3">Lewati ke konten</a>

    <aside id="app-sidebar" aria-label="Sidebar" style="view-transition-name: dash-sidebar"
        class="glass-strong fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] -translate-x-full flex-col rounded-r-2xl transition-[transform,visibility,width] duration-300 ease-[cubic-bezier(0.2,0.7,0.2,1)] max-lg:invisible motion-reduce:transition-none
            lg:inset-y-3 lg:left-3 lg:w-64 lg:translate-x-0 lg:rounded-2xl lg:group-data-[sidebar=collapsed]/root:w-[4.5rem]"
        :class="sidebar && 'translate-x-0! visible!'">
        <div class="flex h-16 shrink-0 items-center gap-2 px-4 lg:group-data-[sidebar=collapsed]/root:justify-center lg:group-data-[sidebar=collapsed]/root:px-0">
            <a href="{{ route('home') }}" aria-label="ToolKits, beranda" class="min-w-0 rounded-md">
                <x-wordmark class="[&>span]:lg:group-data-[sidebar=collapsed]/root:sr-only" />
            </a>
            @if($area)
                <span class="badge badge-pro ml-auto max-w-24 truncate lg:group-data-[sidebar=collapsed]/root:hidden">{{ $area }}</span>
            @endif
            <button type="button" x-ref="sidebarClose" @click="closeSidebar()" @class(['btn-ghost btn-sm lg:hidden', 'ml-auto' => ! $area]) aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto overscroll-contain px-3 py-2" aria-label="{{ $navLabel }}" @click="$event.target.closest('a') && closeSidebar(false)">
            @php($shownGroups = 0)
            @foreach($nav as $group)
                @php($items = array_filter($group['items'], fn ($item) => empty($item['permission']) || auth()->user()?->can($item['permission'])))
                {{-- Groups without any permitted item are hidden entirely --}}
                @continue($items === [])
                @php($groupId = 'nav-group-'.$loop->index)
                <div @class(['flex flex-col gap-1', 'mt-4' => $shownGroups++ > 0]) role="group" @if(! empty($group['heading'])) aria-labelledby="{{ $groupId }}" @endif>
                    @if(! empty($group['heading']))
                        <p id="{{ $groupId }}" class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted lg:group-data-[sidebar=collapsed]/root:sr-only">{{ $group['heading'] }}</p>
                        <hr class="mx-2 mb-1 hidden border-glass-line lg:group-data-[sidebar=collapsed]/root:block" aria-hidden="true">
                    @endif
                    @foreach($items as $item)
                        @php($active = request()->routeIs($item['pattern']))
                        <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @if($active) aria-current="page" @endif
                            class="nav-item lg:group-data-[sidebar=collapsed]/root:justify-center lg:group-data-[sidebar=collapsed]/root:px-0">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                            <span class="truncate lg:group-data-[sidebar=collapsed]/root:sr-only">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach

            {{ $sidebarExtra ?? '' }}
        </nav>

        <div class="shrink-0 border-t border-glass-line p-3">
            <div class="mb-3 sm:hidden"><x-theme-toggle /></div>
            <div class="flex items-center gap-3 lg:group-data-[sidebar=collapsed]/root:flex-col">
                <a href="{{ route('dashboard.profile') }}" class="shrink-0 rounded-full" title="Profil" aria-label="Profil">
                    <x-avatar :user="auth()->user()" size="h-9 w-9" />
                </a>
                <div class="min-w-0 flex-1 lg:group-data-[sidebar=collapsed]/root:hidden">
                    <p class="truncate text-sm font-semibold text-fg">{{ auth()->user()?->name }}</p>
                    <p class="truncate text-xs text-muted">{{ auth()->user()?->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm px-2" title="Keluar" aria-label="Keluar">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                    </button>
                </form>
            </div>
            <button type="button" @click="toggleCollapsed()" :aria-expanded="(! collapsed).toString()" aria-controls="app-sidebar"
                class="btn-ghost btn-sm mt-2 hidden w-full justify-start gap-3 px-3 lg:flex lg:group-data-[sidebar=collapsed]/root:justify-center lg:group-data-[sidebar=collapsed]/root:px-0">
                <svg class="h-[18px] w-[18px] shrink-0 transition-transform duration-300 lg:group-data-[sidebar=collapsed]/root:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"/></svg>
                <span class="lg:group-data-[sidebar=collapsed]/root:sr-only" x-text="collapsed ? 'Lebarkan menu' : 'Ciutkan menu'">Ciutkan menu</span>
            </button>
        </div>
    </aside>

    <div x-show="sidebar" x-cloak x-transition.opacity.duration.200ms @click="closeSidebar()" class="fixed inset-0 z-30 bg-ink/40 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    <div class="flex min-h-dvh min-w-0 flex-col transition-[padding] duration-300 ease-[cubic-bezier(0.2,0.7,0.2,1)] motion-reduce:transition-none lg:pl-[17.5rem] lg:group-data-[sidebar=collapsed]/root:pl-[6rem]">
        <header style="view-transition-name: dash-topbar"
            class="glass-strong sticky top-0 z-20 mx-0 flex h-16 items-center gap-3 rounded-b-xl border-t-0 px-4 sm:px-5 lg:top-3 lg:mx-3 lg:mt-3 lg:rounded-xl lg:border-t">
            <button type="button" x-ref="sidebarToggle" @click="openSidebar()" class="btn-ghost btn-sm -ml-1 px-2 lg:hidden" aria-label="Buka menu"
                aria-controls="app-sidebar" :aria-expanded="sidebar.toString()" aria-expanded="false">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10"/></svg>
            </button>

            <h1 class="truncate font-display text-base font-bold tracking-tight text-fg sm:text-lg">{{ $title }}</h1>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                {{ $actions ?? '' }}
                <div class="hidden sm:block"><x-theme-toggle /></div>
            </div>
        </header>

        <main id="main" class="w-full min-w-0 flex-1 px-4 pb-10 pt-6 sm:px-6 lg:px-8 lg:pt-8">
            <div class="animate-rise">
                <x-flash />
            </div>
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
