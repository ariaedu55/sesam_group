<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Services\PaymentService;

final class WebhookController extends Controller
{
    /**
     * @param array{
     *     gateway: string,
     *     naboopay: array{api_key: string, webhook_secret: string, api_base: string, signature_header: string}
     * } $paymentConfig
     */
    public function __construct(
        \Sesam\Core\Response\ViewRenderer $views,
        private readonly PaymentService $payments,
        private readonly array $paymentConfig,
    ) {
        parent::__construct($views);
    }

    public function nabooPay(Request $request): Response
    {
        $header = $this->paymentConfig['naboopay']['signature_header'];
        $signature = $request->header($header);

        try {
            $this->payments->handleNabooWebhook(
                $request->rawBody(),
                $signature,
                $this->paymentConfig,
            );

            return Response::json(json_encode(['ok' => true], JSON_THROW_ON_ERROR));
        } catch (HttpException $exception) {
            return Response::json(
                json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR),
                $exception->status()
            );
        }
    }
}
