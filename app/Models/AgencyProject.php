<?php

declare(strict_types=1);

namespace Sesam\Models;

use Sesam\Services\Agency\AgencyCatalog;

final class AgencyProject extends Model
{
    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function prospectId(): int
    {
        return (int) $this->get('prospect_id', 0);
    }

    public function quoteId(): int
    {
        return (int) $this->get('quote_id', 0);
    }

    public function title(): string
    {
        return (string) $this->get('title', '');
    }

    public function offerLabel(): string
    {
        $offer = (string) $this->get('offer', '');

        return AgencyCatalog::OFFERS[$offer]['label'] ?? $offer;
    }

    public function amount(): int
    {
        return (int) $this->get('amount', 0);
    }

    public function status(): string
    {
        return (string) $this->get('status', '');
    }

    public function statusLabel(): string
    {
        return AgencyCatalog::PROJECT_STATUSES[$this->status()] ?? $this->status();
    }

    public function startMonth(): ?string
    {
        $value = $this->get('start_month');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function startedOn(): ?string
    {
        $value = $this->get('started_on');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function deliveredOn(): ?string
    {
        $value = $this->get('delivered_on');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function siteUrl(): string
    {
        return (string) $this->get('site_url', '');
    }

    public function clientName(): string
    {
        $business = (string) $this->get('business_name', '');

        return $business !== '' ? $business : (string) $this->get('full_name', '');
    }

    public function invoicedAmount(): int
    {
        return (int) $this->get('invoiced_amount', 0);
    }

    public function paidAmount(): int
    {
        return (int) $this->get('paid_amount', 0);
    }
}
