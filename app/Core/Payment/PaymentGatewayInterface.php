<?php

declare(strict_types=1);

namespace Sesam\Core\Payment;

use Sesam\Models\Payment;

interface PaymentGatewayInterface
{
    public function slug(): string;

    /**
     * @param array{methods?: list<string>, success_url?: string, error_url?: string} $options
     * @return array{mode: string, checkout_url: string, external_id: ?string}
     */
    public function createCheckout(Payment $payment, string $returnUrl, array $options = []): array;

    public function isLive(): bool;
}
