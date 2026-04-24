<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued e-mail verification. The base `VerifyEmail` notification from
 * Laravel already knows how to build a signed `verification.verify` URL —
 * we inherit that and only customise the queue + subject.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Подтверждение e-mail — DigitalBank')
            ->greeting('Здравствуйте!')
            ->line('Для завершения регистрации в DigitalBank подтвердите свой адрес электронной почты.')
            ->action('Подтвердить e-mail', $url)
            ->line('Если вы не регистрировались в DigitalBank, просто проигнорируйте это письмо.')
            ->salutation('С уважением, команда DigitalBank');
    }
}
