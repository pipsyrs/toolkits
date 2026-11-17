<?php

namespace App\Enums;

use LogicException;

// Permission catalog: one permission per admin module and action, plus panel access. Gate::before grants superadmin only these.
enum AdminResource: string
{
    // Entry to the admin panel (dashboard); implied by every other admin permission.
    public const PANEL_ACCESS = 'admin.access';

    case Users = 'users';
    case Links = 'links';
    case Plans = 'plans';
    case Payments = 'payments';
    case PaymentMethods = 'payment_methods';
    case PaymentMethodTypes = 'payment_method_types';
    case PaymentChannels = 'payment_channels';
    case Tools = 'tools';
    case ToolCategories = 'tool_categories';
    case Reports = 'reports';
    case Audit = 'audit';
    case Roles = 'roles';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Pengguna',
            self::Links => 'Link',
            self::Plans => 'Paket',
            self::Payments => 'Transaksi',
            self::PaymentMethods => 'Metode bayar',
            self::PaymentMethodTypes => 'Tipe metode bayar',
            self::PaymentChannels => 'Payment channel',
            self::Tools => 'Alat',
            self::ToolCategories => 'Kategori alat',
            self::Reports => 'Laporan pengguna (CS)',
            self::Audit => 'Audit log',
            self::Roles => 'Role',
            self::Settings => 'Pengaturan',
        };
    }

    // Grouping key for the role-permission UI; several resources share one group.
    public function group(): string
    {
        return match ($this) {
            self::Payments, self::PaymentMethods, self::PaymentMethodTypes, self::PaymentChannels => 'payments',
            self::Tools, self::ToolCategories => 'tools',
            self::Reports => 'support',
            default => $this->value,
        };
    }

    /**
     * Actions the module actually has; no permission exists for the others.
     *
     * @return list<PermissionAction>
     */
    public function actions(): array
    {
        $crud = [PermissionAction::ViewAny, PermissionAction::Create, PermissionAction::Update, PermissionAction::Delete];

        return match ($this) {
            self::Plans, self::PaymentMethods, self::PaymentMethodTypes, self::PaymentChannels, self::ToolCategories, self::Roles => $crud,
            self::Tools, self::Reports => [PermissionAction::ViewAny, PermissionAction::View, PermissionAction::Update],
            self::Users, self::Links, self::Settings => [PermissionAction::ViewAny, PermissionAction::Update],
            self::Payments, self::Audit => [PermissionAction::ViewAny],
        };
    }

    // Unknown pairs fail loudly: a permission that is never granted would lock the feature for everyone.
    public function permission(PermissionAction $action): string
    {
        if (! in_array($action, $this->actions(), true)) {
            throw new LogicException("Permission {$this->value}.{$action->value} is not in the catalog.");
        }

        return $this->value.'.'.$action->value;
    }

    /**
     * Every catalog permission name.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        $names = [self::PANEL_ACCESS];

        foreach (self::cases() as $resource) {
            foreach ($resource->actions() as $action) {
                $names[] = $resource->permission($action);
            }
        }

        return $names;
    }

    public static function isPermission(string $name): bool
    {
        return in_array($name, self::permissions(), true);
    }

    /**
     * Mutating permissions (create/update/delete) across every resource; flagged as "sensitive" in the UI.
     *
     * @return list<string>
     */
    public static function mutatingPermissions(): array
    {
        $mutating = [PermissionAction::Create, PermissionAction::Update, PermissionAction::Delete];
        $names = [];

        foreach (self::cases() as $resource) {
            foreach ($resource->actions() as $action) {
                if (in_array($action, $mutating, true)) {
                    $names[] = $resource->permission($action);
                }
            }
        }

        return $names;
    }
}
