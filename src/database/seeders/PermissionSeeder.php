<?php

namespace Database\Seeders;

use App\Enums\AdminResource;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (AdminResource::permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Drop permissions no longer in the catalog.
        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', AdminResource::permissions())
            ->get()
            ->each->delete();

        $registrar->forgetCachedPermissions();
    }
}
