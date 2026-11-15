<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Superadmin holds no explicit permissions; access comes from Gate::before (ADR-006).
        Role::findOrCreate('superadmin', 'web')->syncPermissions([]);

        $registrar->forgetCachedPermissions();
    }
}
