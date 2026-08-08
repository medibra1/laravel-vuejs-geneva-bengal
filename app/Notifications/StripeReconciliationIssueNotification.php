<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Covers two distinct situations ReconcilePendingDeposits can hit while
 * polling Stripe for a deposit whose webhook never arrived — see
 * CLAUDE.md. `reason` picks which one this instance is about:
 *
 * - 'error': isCheckoutPaid() itself blew up (network/API error) — a
 *   technical problem that needs a quick look, not necessarily urgent.
 * - 'expired': the deposit's own 24h checkout window passed with no
 *   payment confirmed — informative, the cat it held has already been
 *   released back to available by the caller.
 */
class StripeReconciliationIssueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deposit $deposit,
        public string $reason,
        public ?string $errorMessage = null,
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
        return $this->reason === 'error' ? $this->errorMail() : $this->expiredMail();
    }

    /**
     * Consumed by NotificationBell.vue via HandleInertiaRequests' shared
     * prop — `reason` lets it tell the two apart visually (warning icon
     * for 'error', neutral for 'expired'). No `show` route exists for a
     * single deposit, so the link goes to the list.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'stripe_issue',
            'reason' => $this->reason,
            'title' => $this->reason === 'error'
                ? 'Problème technique lors de la vérification d\'un paiement Stripe'
                : 'Réservation expirée sans paiement confirmé',
            'message' => $this->reason === 'error'
                ? "Dépôt #{$this->deposit->id} : {$this->errorMessage}"
                : "{$this->deposit->name} — {$this->catReleasedLine()}",
            'url' => route('admin.deposits.index'),
        ];
    }

    private function errorMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Problème technique lors de la vérification d\'un paiement Stripe — Geneva Bengal')
            ->line("La vérification automatique du dépôt #{$this->deposit->id} auprès de Stripe a échoué.")
            ->line("Erreur : {$this->errorMessage}");
    }

    private function expiredMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Réservation expirée sans paiement confirmé — Geneva Bengal')
            ->line("La réservation de {$this->deposit->name} a expiré sans confirmation de paiement.")
            ->line($this->catReleasedLine());
    }

    private function catReleasedLine(): string
    {
        return $this->deposit->cat_id !== null
            ? "Le chat {$this->deposit->cat->name} a été libéré et est de nouveau disponible."
            : "Il s'agissait d'une inscription en liste d'attente.";
    }
}
