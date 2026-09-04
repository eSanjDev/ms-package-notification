<?php

namespace Esanj\NotificationClient\Enums;

use Esanj\NotificationClient\Enums\Concerns\NormalizesInput;

enum NotificationChannel: string
{
    use NormalizesInput;

    case SMS = 'sms';
    case EMAIL = 'email';
    case PUSH = 'push';
}
