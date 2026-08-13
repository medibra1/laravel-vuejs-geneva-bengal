<?php

namespace App\Notifications;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by DepositPaymentProcessor::markPaid()'s "lost the race" branch —
 * two visitors held parallel PaymentIntent authorizations for the same
 * cat, this one's lost to another deposit that got captured first. See
 * CLAUDE.md.
 *
 * One class covers both audiences, same pattern as
 * StripeReconciliationIssueNotification's reason-based branching, but
 * split on $notifiable instead: the client (an on-demand
 * AnonymousNotifiable, via Notification::route('mail', ...) — never has a
 * database channel to write to) only ever gets a mail, while staff get the
 * mail+database pair so it also surfaces in NotificationBell.vue.
 *
 * $refunded distinguishes the two ways a losing PaymentIntent can be
 * released (see DepositPaymentProcessor::loseRace()): a card
 * authorization is simply cancelled, so the client was never charged —
 * but TWINT doesn't support capture_method: manual (see
 * StripeGateway::createPaymentIntent()) and auto-captures the instant the
 * client confirms in the app, so by the time this fires it may already
 * have been charged and had to be refunded instead. The wording must not
 * claim "you were never charged" in that case.
 */
class DepositUnavailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deposit $deposit,
        public bool $refunded = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $notifiable instanceof AnonymousNotifiable ? $this->clientMail() : $this->staffMail();
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        $outcome = $this->refunded ? 'débité puis remboursé' : 'non débité';

        return [
            'type' => 'deposit_unavailable',
            'title' => 'Réservation perdue sur un paiement en double',
            'message' => "{$this->deposit->name} — chat concerné : {$this->deposit->cat->name}, client {$outcome}.",
            'url' => route('admin.deposits.index'),
        ];
    }

    private function clientMail(): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('emails.deposit_unavailable.subject'))
            ->greeting(__('emails.deposit_unavailable.greeting', ['name' => $this->deposit->name]))
            ->line(__('emails.deposit_unavailable.line_taken'));

        $message = $this->refunded
            ? $message->line(__('emails.deposit_unavailable.line_refunded'))
            : $message->line(__('emails.deposit_unavailable.line_not_charged'));

        return $message->line(__('emails.deposit_unavailable.line_closing'));
    }

    private function staffMail(): MailMessage
    {
        $outcomeLine = $this->refunded
            ? 'Paiement TWINT (auto-capturé, pas de simple annulation possible) débité puis remboursé.'
            : 'Autorisation carte annulée, aucun débit n\'a eu lieu.';

        return (new MailMessage)
            ->subject('Réservation en double détectée et résolue — Geneva Bengal')
            ->line("Deux dépôts concurrents visaient le même chat ; celui de {$this->deposit->name} (dépôt #{$this->deposit->id}) a perdu la course.")
            ->line("Chat concerné : {$this->deposit->cat->name}. Le client a été informé. {$outcomeLine}");
    }
}
