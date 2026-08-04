<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DepositConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deposit $deposit,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->deposit->amount / 100, 2);

        $message = (new MailMessage)
            ->subject('Votre acompte a bien été reçu — Geneva Bengal')
            ->greeting("Bonjour {$this->deposit->name},")
            ->line("Nous avons bien reçu votre acompte de {$amount} {$this->deposit->currency}.");

        if ($this->deposit->cat_id !== null) {
            $message->line("Il concerne votre réservation pour {$this->deposit->cat->name}.");
        } else {
            $message->line("Il vous inscrit sur notre liste d'attente.");
        }

        return $message->line('Nous reviendrons vers vous très prochainement.');
    }
}
