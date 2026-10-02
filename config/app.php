<?php
return [
    'app_name' => 'TurnoPronto',
    'tagline' => 'Nós cuidamos do extra que você precisa.',
    'timezone' => 'America/Sao_Paulo',
    'session_name' => 'turnopronto_session',

    // null = autodetect (ideal para XAMPP em /turnopronto_web)
    // ''   = aplicação publicada na raiz do domínio
    'public_base_path' => null,

    'debug' => true,

    'legal' => [
        'terms_version' => '2026-10-02',
        'privacy_version' => '2026-10-02',
    ],

    // Configure em config.local.php. Nunca versionar tokens reais.
    'whatsapp' => [
        'driver' => 'disabled', // disabled | meta_cloud | webhook | debug
        'meta_api_version' => 'v23.0',
        'meta_phone_number_id' => '',
        'meta_access_token' => '',
        'meta_template_name' => '',
        'meta_template_language' => 'pt_BR',
        'webhook_url' => '',
        'webhook_token' => '',
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'turnopronto',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
