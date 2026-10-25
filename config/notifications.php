<?php

declare(strict_types=1);

use Sesam\Core\Env;

$mode = strtolower(trim(Env::get('NOTIFICATIONS_MODE', 'mock')));

return [
    'mode' => in_array($mode, ['mock', 'live'], true) ? $mode : 'mock',
    'mail' => [
        'from' => Env::get('MAIL_FROM', ''),
        'smtp_host' => Env::get('SMTP_HOST', ''),
        'smtp_port' => Env::get('SMTP_PORT', '587'),
        'smtp_user' => Env::get('SMTP_USER', ''),
        'smtp_password' => Env::get('SMTP_PASSWORD', ''),
    ],
    'whatsapp' => [
        'api_url' => rtrim(Env::get('WHATSAPP_API_URL', ''), '/'),
        'api_token' => Env::get('WHATSAPP_API_TOKEN', ''),
    ],
];
