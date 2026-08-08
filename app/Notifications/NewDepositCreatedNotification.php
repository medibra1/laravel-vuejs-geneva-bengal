<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDepositCreatedNotification extends Notification implements ShouldQueue
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
        $amount = number_format($this->deposit->amount / 100, 2);

        return (new MailMessage)
            ->subject('Nouvelle réservation — Geneva Bengal')
            ->line("Nouvelle réservation de {$this->deposit->name} ({$amount} {$this->deposit->currency}).")
            ->line($this->catLine());
    }

    /**
     * Consumed by NotificationBell.vue via HandleInertiaRequests' shared
     * prop — see CLAUDE.md's notification spec. No `show` route exists for
     * a single deposit, so the link goes to the list.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'deposit_created',
            'title' => 'Nouvelle réservation',
            'message' => "{$this->deposit->name} — {$this->catLine()}",
            'url' => route('admin.deposits.index'),
        ];
    }

    private function catLine(): string
    {
        return $this->deposit->cat_id !== null
            ? "Concerne {$this->deposit->cat->name}."
            : "Inscription en liste d'attente.";
    }
}
