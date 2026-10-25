<?php

declare(strict_types=1);

namespace Sesam\Core\Payment;

use Sesam\Core\HttpException;
use Sesam\Core\Payment\Gateway\NabooPayGateway;

final class PaymentGatewayFactory
{
    /**
     * @param array{
     *     gateway: string,
     *     naboopay: array{api_key: string, webhook_secret: string, api_base: string, signature_header: string, live?: bool}
     * } $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $appUrl,
    ) {
    }

    public function make(): PaymentGatewayInterface
    {
        $slug = $this->config['gateway'];
        if ($slug === 'naboopay') {
            return new NabooPayGateway($this->config['naboopay'], $this->appUrl);
        }

        throw new HttpException(500, 'Passerelle de paiement non configurée.');
    }
}
