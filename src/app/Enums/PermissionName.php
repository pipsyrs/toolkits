<?php

namespace App\Enums;

// Permission catalog; Gate::before grants superadmin only these abilities.
enum PermissionName: string
{
    case AdminAccess = 'admin.access';
    case UsersView = 'users.view';
    case UsersBlock = 'users.block';
    case UsersRoles = 'users.roles';
    case LinksView = 'links.view';
    case LinksModerate = 'links.moderate';
    case PlansManage = 'plans.manage';
    case AuditView = 'audit.view';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case PaymentsView = 'payments.view';
    case PaymentMethodsManage = 'payment_methods.manage';
    case ToolsManage = 'tools.manage';
    case ReportsView = 'reports.view';
    case ReportsManage = 'reports.manage';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Akses panel admin',
            self::UsersView => 'Lihat pengguna',
            self::UsersBlock => 'Blokir / buka blokir pengguna',
            self::UsersRoles => 'Atur role pengguna',
            self::LinksView => 'Lihat link',
            self::LinksModerate => 'Moderasi link (nonaktifkan / aktifkan)',
            self::PlansManage => 'Kelola paket',
            self::AuditView => 'Lihat audit log',
            self::RolesManage => 'Kelola role & permission',
            self::SettingsManage => 'Kelola pengaturan aplikasi',
            self::PaymentsView => 'Lihat transaksi pembayaran',
            self::PaymentMethodsManage => 'Kelola metode pembayaran',
            self::ToolsManage => 'Kelola alat (status & kategori)',
            self::ReportsView => 'Lihat laporan pengguna (CS)',
            self::ReportsManage => 'Tanggapi laporan pengguna (CS)',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AdminAccess => 'admin',
            self::UsersView, self::UsersBlock, self::UsersRoles => 'users',
            self::LinksView, self::LinksModerate => 'links',
            self::PlansManage => 'plans',
            self::AuditView => 'audit',
            self::RolesManage => 'roles',
            self::SettingsManage => 'settings',
            self::PaymentsView, self::PaymentMethodsManage => 'payments',
            self::ToolsManage => 'tools',
            self::ReportsView, self::ReportsManage => 'support',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
