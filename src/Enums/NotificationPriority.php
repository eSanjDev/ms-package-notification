<?php

namespace Esanj\NotificationClient\Enums;

use Esanj\NotificationClient\Enums\Concerns\NormalizesInput;

enum NotificationPriority: string
{
    use NormalizesInput;

    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
