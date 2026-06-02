<?php

namespace Esanj\NotificationClient\Contracts;

use Esanj\NotificationClient\Auth\Token;

interface TokenManagerInterface
{
    public function getToken(): Token;

    public function refresh(): Token;

    public function invalidate(): void;
}