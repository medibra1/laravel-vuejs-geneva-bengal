<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by ReconcileCheckouts once a paid Deposit's confirmation email has
 * failed CONFIRMATION_MAX_ATTEMPTS times (see CLAUDE.md) — the retry loop
 * stops there rather than trying forever every 15 minutes against what is
 * very likely a bad address. Staff only, no client-facing equivalent: the
 * client already didn't get an email, that's the whole problem — the
 * client has been paid for and is owed a call, not another automated
 * attempt.
 */
class DepositConfirmationUndeliveredNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contactLine = $this->deposit->phone !== null
            ? "Contact : {$this->deposit->email} / {$this->deposit->phone}."
            : "Contact : {$this->deposit->email}.";

        return (new MailMessage)
            ->subject('Email de confirmation non délivré — Geneva Bengal')
            ->line("L'email de confirmation d'acompte de {$this->deposit->name} n'a pas pu être envoyé après {$this->deposit->confirmation_attempts} tentatives.")
            ->line('Le paiement a bien été reçu — merci de contacter le client directement pour confirmer sa réservation.')
            ->line($contactLine);
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'confirmation_undelivered',
            'title' => 'Email de confirmation non délivré',
            'message' => "{$this->deposit->name} — paiement reçu mais email non délivré, à recontacter directement.",
            'url' => route('admin.deposits.index'),
        ];
    }
}
