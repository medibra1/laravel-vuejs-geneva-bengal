<?php

namespace Tests\Doubles;

use App\Notifications\DepositConfirmedNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Simulates an SMTP failure (e.g. a MAIL_FROM_ADDRESS the Infomaniak
 * anti-spoofing filter rejects — see CLAUDE.md) for exactly one
 * notification class, while every other 'mail' channel send still goes
 * through normally via the real MailChannel. Bound in place of MailChannel
 * itself (see ChannelManager::createMailDriver(), which resolves it from
 * the container) rather than the whole notification, so
 * DepositPaymentProcessor::sendConfirmationNotifications()'s own
 * try/catch is exercised for real, not mocked away.
 */
class FailingMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        if ($notification instanceof DepositConfirmedNotification) {
            throw new RuntimeException('Simulated SMTP failure (e.g. MAIL_FROM_ADDRESS rejected).');
        }

        return parent::send($notifiable, $notification);
    }
}
