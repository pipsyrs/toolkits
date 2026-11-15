<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperadminSeeder extends Seeder
{
    // Credentials only from env via config('toolkits.superadmin.*').
    public function run(): void
    {
        $email = Str::lower(trim((string) config('toolkits.superadmin.email')));
        $password = (string) config('toolkits.superadmin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('SUPERADMIN_EMAIL/SUPERADMIN_PASSWORD empty, superadmin skipped.');

            return;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // Never promote or modify an existing account.
            if (! $existing->hasRole('superadmin')) {
                throw new RuntimeException("SuperadminSeeder: {$email} already exists and is not a superadmin; refusing to promote it.");
            }

            $this->command?->info('Superadmin already exists, skipped.');

            return;
        }

        $user = new User;
        $user->forceFill([
            'name' => 'Superadmin',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        $user->assignRole('superadmin');
    }
}
