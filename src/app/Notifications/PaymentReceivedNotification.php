<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\Tier\PlanLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = app(PlanLimits::class)->label($this->payment->plan);
        $until = $notifiable->plan_until?->copy()->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i');

        return (new MailMessage)
            ->subject('Pembayaran diterima — '.$this->payment->order_id)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pembayaran Anda untuk paket '.$plan.' ('.$this->payment->period->label().') sebesar Rp'.number_format($this->payment->amount, 0, ',', '.').' telah kami terima.')
            ->lineIf($until !== null, 'Paket Anda aktif hingga '.$until.' WIB.')
            ->action('Lihat langganan', route('dashboard.billing'))
            ->line('Terima kasih telah berlangganan.');
    }
}
