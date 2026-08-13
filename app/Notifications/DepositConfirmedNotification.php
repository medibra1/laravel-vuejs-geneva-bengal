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
            ->subject(__('emails.deposit_confirmed.subject'))
            ->greeting(__('emails.deposit_confirmed.greeting', ['name' => $this->deposit->name]))
            ->line(__('emails.deposit_confirmed.line_received', ['amount' => $amount, 'currency' => $this->deposit->currency]));

        if ($this->deposit->cat_id !== null) {
            $message->line(__('emails.deposit_confirmed.line_cat', ['cat' => $this->deposit->cat->name]));
        } else {
            $message->line(__('emails.deposit_confirmed.line_waiting_list'));
        }

        return $message->line(__('emails.deposit_confirmed.line_closing'));
    }
}
