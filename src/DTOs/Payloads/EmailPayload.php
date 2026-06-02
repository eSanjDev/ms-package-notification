<?php

namespace Esanj\NotificationClient\DTOs\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class EmailPayload implements PayloadInterface
{
    private ?string $subject = null;
    private ?string $htmlBody = null;
    private ?string $textBody = null;
    private ?string $fromEmail = null;
    private ?string $fromName = null;
    private ?string $replyTo = null;
    private array $cc = [];
    private array $bcc = [];

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public function subject(string $subject): self
    {
        $clone = clone $this;
        $clone->subject = $subject;
        return $clone;
    }

    public function html(string $html): self
    {
        $clone = clone $this;
        $clone->htmlBody = $html;
        return $clone;
    }

    public function text(string $text): self
    {
        $clone = clone $this;
        $clone->textBody = $text;
        return $clone;
    }

    public function from(string $email, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->fromEmail = $email;
        $clone->fromName = $name;
        return $clone;
    }

    public function replyTo(string $email): self
    {
        $clone = clone $this;
        $clone->replyTo = $email;
        return $clone;
    }

    public function cc(array $emails): self
    {
        $clone = clone $this;
        $clone->cc = $emails;
        return $clone;
    }

    public function bcc(array $emails): self
    {
        $clone = clone $this;
        $clone->bcc = $emails;
        return $clone;
    }

    public function toArray(): array
    {
        return array_filter([
            'subject'    => $this->subject,
            'html_body'  => $this->htmlBody,
            'text_body'  => $this->textBody,
            'from_email' => $this->fromEmail,
            'from_name'  => $this->fromName,
            'reply_to'   => $this->replyTo,
            'cc'         => $this->cc ?: null,
            'bcc'        => $this->bcc ?: null,
        ], fn($v) => $v !== null);
    }
}