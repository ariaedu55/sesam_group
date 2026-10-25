<?php

declare(strict_types=1);

namespace Sesam\Models;

final class MessageDelivery extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function channel(): string
    {
        return (string) $this->get('channel', '');
    }

    public function recipient(): string
    {
        return (string) $this->get('recipient', '');
    }

    public function templateSlug(): string
    {
        return (string) $this->get('template_slug', '');
    }

    public function subject(): ?string
    {
        $value = $this->get('subject');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function body(): string
    {
        return (string) $this->get('body', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function errorMessage(): ?string
    {
        $value = $this->get('error_message');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function attempts(): int
    {
        return (int) $this->get('attempts', 0);
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    public function sentAt(): ?string
    {
        $value = $this->get('sent_at');
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
