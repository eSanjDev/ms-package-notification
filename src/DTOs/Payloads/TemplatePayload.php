<?php

namespace Esanj\NotificationClient\DTOs\Payloads;

use Esanj\NotificationClient\Contracts\PayloadInterface;

final class TemplatePayload implements PayloadInterface
{
    private array $variables = [];
    private ?string $language = null;

    private function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function variables(array $variables): self
    {
        $clone = clone $this;
        $clone->variables = $variables;
        return $clone;
    }

    public function language(string $language): self
    {
        $clone = clone $this;
        $clone->language = $language;
        return $clone;
    }

    public function toArray(): array
    {
        $template = [
            'key'       => $this->key,
            'variables' => $this->variables,
        ];

        if ($this->language !== null) {
            $template['language'] = $this->language;
        }

        return ['template' => $template];
    }
}
