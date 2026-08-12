<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by DepositPaymentProcessor::confirmPaid() — the moment a public
 * visitor's payment is actually confirmed (webhook, reconciliation job, or
 * admin "mark paid"), distinct from NewDepositCreatedNotification which
 * fires when the Deposit row is first created (before any money has moved,
 * see reserve()'s own docblock). Staff only — the client already gets
 * DepositConfirmedNotification from the same call site.
 */
class DepositPaidNotification extends Notification implements ShouldQueue
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
            ->subject('Acompte reçu — Geneva Bengal')
            ->line("L'acompte de {$this->deposit->name} ({$amount} {$this->deposit->currency}) a été confirmé.")
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
            'type' => 'deposit_paid',
            'title' => 'Acompte reçu',
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
