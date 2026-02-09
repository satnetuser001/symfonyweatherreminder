<?php

namespace App\Message;

/**
 * Task to send the actual email to the user
 */
final readonly class SendEmailNotificationMessage
{
    public function __construct(
        public int $notificationId
    ) {
    }
}
