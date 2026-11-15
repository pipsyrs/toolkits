@props(['title' => null])

@php
    $nav = [
        ['label' => 'Ringkasan', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['label' => 'Link', 'route' => 'dashboard.links.index', 'pattern' => 'dashboard.links.*', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
        ['label' => 'File', 'route' => 'dashboard.files.index', 'pattern' => 'dashboard.files.*', 'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
        ['label' => 'Langganan', 'route' => 'dashboard.billing', 'pattern' => 'dashboard.billing', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['label' => 'Profil', 'route' => 'dashboard.profile', 'pattern' => 'dashboard.profile', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
@endphp

<x-dashboard.shell :title="$title ?? 'Dashboard'" :document-title="$title ? $title . ' | ToolKits' : 'Dashboard | ToolKits'"
    :nav="$nav" nav-label="Menu dashboard">
    @can('admin.access')
        <x-slot:sidebarExtra>
            <a href="{{ route('admin.dashboard') }}" title="Area Admin"
                class="nav-item mt-3 border border-dashed border-line lg:group-data-[sidebar=collapsed]/root:justify-center lg:group-data-[sidebar=collapsed]/root:px-0">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="lg:group-data-[sidebar=collapsed]/root:sr-only">Area Admin</span>
            </a>
        </x-slot:sidebarExtra>
    @endcan

    <x-slot:actions>
        <livewire:quota-badge metric="links" />
    </x-slot:actions>

    {{ $slot }}
</x-dashboard.shell>
