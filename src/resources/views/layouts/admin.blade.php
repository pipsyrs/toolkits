@props(['title' => null])

@php
    $nav = [
        ['label' => 'Ringkasan', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'permission' => 'admin.access', 'icon' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z'],
        ['label' => 'Users', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'permission' => 'users.view', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.5-1.35'],
        ['label' => 'Links', 'route' => 'admin.links.index', 'pattern' => 'admin.links.*', 'permission' => 'links.view', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
        ['label' => 'Plans', 'route' => 'admin.plans.edit', 'pattern' => 'admin.plans.*', 'permission' => 'plans.manage', 'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
        ['label' => 'Transaksi', 'route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'permission' => 'payments.view', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['label' => 'Metode bayar', 'route' => 'admin.payment-methods.index', 'pattern' => 'admin.payment-methods.*', 'permission' => 'payment_methods.manage', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['label' => 'Alat', 'route' => 'admin.tools.index', 'pattern' => 'admin.tools.*', 'permission' => 'tools.manage', 'icon' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437'],
        ['label' => 'Audit log', 'route' => 'admin.audit-logs.index', 'pattern' => 'admin.audit-logs.*', 'permission' => 'audit.view', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'Role', 'route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'permission' => 'roles.manage', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ['label' => 'Pengaturan', 'route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'permission' => 'settings.manage', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ];
@endphp

<x-dashboard.shell :title="$title ?? 'Admin'" :document-title="$title ? $title . ' | Admin' : 'Admin | ToolKits'"
    :nav="$nav" nav-label="Menu admin" :area="Str::headline(auth()->user()?->roles->first()?->name ?? 'Admin')">
    <x-slot:sidebarExtra>
        <div class="mt-3 border-t border-glass-line pt-3 lg:group-data-[sidebar=collapsed]/root:hidden">
            <span class="nav-item cursor-default text-muted/70 hover:bg-transparent hover:text-muted/70" aria-disabled="true">
                Reports <span class="badge badge-muted ml-auto">segera</span>
            </span>
        </div>
    </x-slot:sidebarExtra>

    <x-slot:actions>
        <a href="{{ route('dashboard') }}" class="btn-secondary btn-sm">Area Client</a>
    </x-slot:actions>

    {{ $slot }}
</x-dashboard.shell>
