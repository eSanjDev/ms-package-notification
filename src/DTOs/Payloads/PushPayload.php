<?php

namespace Esanj\NotificationClient\DTOs\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class PushPayload implements PayloadInterface
{
    private ?string $title = null;
    private ?string $body = null;
    private ?string $url = null;
    private ?TemplatePayload $template = null;
    private array $data = [];

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;
        return $clone;
    }

    public function body(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function template(TemplatePayload $template): self
    {
        $clone = clone $this;
        $clone->template = $template;
        return $clone;
    }

    public function url(string $url): self
    {
        $clone = clone $this;
        $clone->url = $url;
        return $clone;
    }

    public function data(array $data): self
    {
        $clone = clone $this;
        $clone->data = $data;
        return $clone;
    }

    public function toArray(): array
    {
        $payload = array_filter([
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
            'data'  => $this->data ?: null,
        ], fn($v) => $v !== null);

        return $this->template === null
            ? $payload
            : array_merge($payload, $this->template->toArray());
    }
}
