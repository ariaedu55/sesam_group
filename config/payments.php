<?php

declare(strict_types=1);

use Sesam\Core\Env;

return [
    'gateway' => Env::get('PAYMENT_GATEWAY', 'naboopay'),
    'naboopay' => [
        'api_key' => Env::get('NABOOPAY_API_KEY', ''),
        'webhook_secret' => Env::get('NABOOPAY_WEBHOOK_SECRET', ''),
        'api_base' => rtrim(Env::get('NABOOPAY_API_BASE', 'https://api.naboopay.com'), '/'),
        'signature_header' => Env::get('NABOOPAY_SIGNATURE_HEADER', 'X-Signature'),
        // Live uniquement si clé présente ET NABOOPAY_LIVE=1 (évite les débits accidentels / casse les tests sandbox).
        'live' => Env::get('NABOOPAY_LIVE', '') === '1',
    ],
];
