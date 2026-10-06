<?php
return [
    'app_name' => 'TurnoPronto',
    'tagline' => 'Nós cuidamos do extra que você precisa.',
    'timezone' => 'America/Sao_Paulo',
    'session_name' => 'turnopronto_session',

    // null mantém autodetecção local; em produção o helper fixa a raiz do domínio.
    'public_base_path' => null,

    'debug' => true,

    'legal' => [
        'terms_version' => '2026-10-02',
        'privacy_version' => '2026-10-02',
    ],

    // SOMENTE DESENVOLVIMENTO. O bypass também exige debug=true.
    // Antes do lançamento, mantenha development_whatsapp_bypass=false e debug=false.
    'registration' => [
        'development_whatsapp_bypass' => true,
        'development_code' => '000111',
    ],

    // OCR de documentos via microserviço face_scanner.
    // A URL e a credencial reais ficam somente em config/config.local.php.
    'document_recognition' => [
        'enabled' => false,
        'url' => '',
        'api_key' => '',
        'timeout_seconds' => 25,
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
        'host' => 'turnopronto.mysql.dbaas.com.br',
        'port' => 3306,
        'name' => 'turnopronto',
        'user' => 'turnopronto',
        // Senha somente em config/config.local.php no servidor.
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
