<?php
return [
    'debug' => true,
    'registration' => [
        'development_whatsapp_bypass' => true,
        'development_code' => '000111',
    ],
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
        'host' => 'turnopronto.mysql.dbaas.com.br',
        'port' => 3306,
        'name' => 'turnopronto',
        'user' => 'turnopronto',
        'pass' => 'DEFINA_A_SENHA_SOMENTE_NO_SERVIDOR',
        'charset' => 'utf8mb4',
    ],
];
