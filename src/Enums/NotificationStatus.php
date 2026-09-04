<?php

namespace Esanj\NotificationClient\Enums;

use Esanj\NotificationClient\Enums\Concerns\NormalizesInput;

enum NotificationStatus: string
{
    use NormalizesInput;

    case PENDING = 'pending';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case FAILED = 'failed';
    case DELIVERED = 'delivered';
    case UNDELIVERED = 'undelivered';
}
