<?php
return [
    'debug' => true,
    'whatsapp' => [
        // Produção recomendada: meta_cloud ou webhook.
        'driver' => 'disabled',
        'meta_api_version' => 'v23.0',
        'meta_phone_number_id' => 'SEU_PHONE_NUMBER_ID',
        'meta_access_token' => 'SEU_TOKEN_FORA_DO_GITHUB',
        'meta_template_name' => 'turnopronto_codigo_verificacao',
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
