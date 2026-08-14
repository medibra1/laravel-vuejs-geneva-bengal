<?php

namespace App\Notifications;

use App\Models\CheckoutHold;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by ReconcileCheckouts when Stripe itself can't be reached/errors out
 * while checking the status of an expired CheckoutHold's PaymentIntent —
 * see CLAUDE.md. A technical problem that needs a quick look, not
 * necessarily urgent: the hold stays in place (neither released nor
 * resolved into a Deposit) until the next run tries again.
 *
 * No Deposit exists at this point — CheckoutHold only carries cat_id and
 * payment_intent_id, not the visitor's name/email (those only reach us
 * once the PaymentIntent's own metadata is actually readable, which is
 * exactly what failed here).
 */
class StripeReconciliationIssueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CheckoutHold $checkoutHold,
        public string $errorMessage,
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
        return (new MailMessage)
            ->subject('Problème technique lors de la vérification d\'un paiement Stripe — Geneva Bengal')
            ->line("La vérification automatique du PaymentIntent {$this->checkoutHold->payment_intent_id} (chat #{$this->checkoutHold->cat_id}) auprès de Stripe a échoué.")
            ->line("Erreur : {$this->errorMessage}");
    }

    /**
     * Consumed by NotificationBell.vue via HandleInertiaRequests' shared
     * prop. No `show` route exists for a single checkout hold, so the
     * link goes to the deposits list — the closest equivalent screen once
     * (if) this PaymentIntent eventually resolves into one.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'stripe_issue',
            'title' => 'Problème technique lors de la vérification d\'un paiement Stripe',
            'message' => "PaymentIntent {$this->checkoutHold->payment_intent_id} : {$this->errorMessage}",
            'url' => route('admin.deposits.index'),
        ];
    }
}
