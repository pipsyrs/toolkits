<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Server-side recovery only; there is no web route to reset another user's 2FA.
class DisableTwoFactor extends Command
{
    protected $signature = 'users:disable-2fa {email : Email akun} {--force : Tanpa konfirmasi interaktif}';

    protected $description = 'Nonaktifkan 2FA akun (pemulihan bila perangkat & kode pemulihan hilang)';

    public function handle(AuditLogger $audit, SessionRevoker $sessions): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            $this->info('2FA akun ini tidak aktif.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Nonaktifkan 2FA untuk {$user->email} dan keluarkan semua sesinya?")) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $audit, $sessions) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $sessions->revoke($user);
            $audit->log(null, 'user.2fa_reset_cli', $user, [], 'artisan users:disable-2fa');
        });

        $this->info("2FA {$user->email} dinonaktifkan. Semua sesi dihapus.");

        return self::SUCCESS;
    }
}
