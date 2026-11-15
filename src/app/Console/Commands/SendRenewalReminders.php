<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PlanExpiringNotification;
use App\Services\Tier\PlanLimits;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendRenewalReminders extends Command
{
    protected $signature = 'billing:send-renewal-reminders';

    protected $description = 'Email users whose paid plan ends in 3 days (WIB), once per plan_until';

    public function handle(): int
    {
        $day = CarbonImmutable::now('Asia/Jakarta')->addDays(3);
        $from = $day->startOfDay()->utc();
        $to = $day->endOfDay()->utc();
        $sent = 0;

        User::query()
            ->select(['id', 'name', 'email', 'plan', 'plan_until', 'plan_reminder_for'])
            ->whereIn('plan', app(PlanLimits::class)->paidSlugs())
            ->whereBetween('plan_until', [$from, $to])
            ->whereNull('blocked_at')
            ->where(fn ($q) => $q->whereNull('plan_reminder_for')->orWhereColumn('plan_reminder_for', '!=', 'plan_until'))
            ->chunkById(200, function (Collection $users) use (&$sent) {
                foreach ($users as $user) {
                    $user->notify(new PlanExpiringNotification($user->plan, $user->plan_until));

                    // Guard against plan_until changing (payment) mid-run.
                    User::query()->whereKey($user->getKey())
                        ->where('plan_until', $user->plan_until)
                        ->update(['plan_reminder_for' => $user->plan_until]);
                    $sent++;
                }
            });

        $this->info("Reminder terkirim: {$sent}");

        return self::SUCCESS;
    }
}
