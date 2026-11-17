<?php

namespace App\Enums;

// CRUD-style actions; a permission is "{AdminResource}.{PermissionAction}".
enum PermissionAction: string
{
    case ViewAny = 'view_any';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::ViewAny => 'Lihat daftar',
            self::View => 'Lihat detail',
            self::Create => 'Buat',
            self::Update => 'Ubah',
            self::Delete => 'Hapus',
        };
    }
}
