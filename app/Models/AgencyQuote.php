<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Services\Agency\AgencyCatalog;

final class AgencyQuote extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function prospectId(): int
    {
        return (int) $this->get('prospect_id', 0);
    }

    public function reference(): string
    {
        return (string) $this->get('reference', '');
    }

    public function offer(): string
    {
        return (string) $this->get('offer', '');
    }

    public function offerLabel(): string
    {
        return AgencyCatalog::OFFERS[$this->offer()]['label'] ?? $this->offer();
    }

    public function sitePath(): string
    {
        return (string) $this->get('site_path', '');
    }

    public function sitePathLabel(): string
    {
        return AgencyCatalog::SITE_PATHS[$this->sitePath()] ?? $this->sitePath();
    }

    public function amount(): int
    {
        return (int) $this->get('amount', 0);
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function statusLabel(): string
    {
        return AgencyCatalog::QUOTE_STATUSES[$this->status()] ?? $this->status();
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}
