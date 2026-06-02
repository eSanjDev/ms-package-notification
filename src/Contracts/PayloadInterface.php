<?php

namespace Esanj\NotificationClient\Contracts;

interface PayloadInterface
{
    public function toArray(): array;
}