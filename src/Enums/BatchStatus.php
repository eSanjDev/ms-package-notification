<?php

namespace Esanj\NotificationClient\Enums;

enum BatchStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case CANCELED = 'canceled';
    case COMPLETED = 'completed';
}
