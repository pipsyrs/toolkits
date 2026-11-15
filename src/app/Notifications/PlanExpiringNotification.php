<?php

namespace App\Notifications;

use App\Services\Tier\PlanLimits;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $plan,
        public readonly CarbonInterface $planUntil,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = app(PlanLimits::class)->label($this->plan);
        $until = $this->planUntil->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i');

        return (new MailMessage)
            ->subject('Paket '.$plan.' Anda segera berakhir')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Paket '.$plan.' Anda akan berakhir pada '.$until.' WIB.')
            ->line('Perpanjang sekarang agar fitur Anda tidak terputus.')
            ->action('Perpanjang paket', route('dashboard.billing'));
    }
}
