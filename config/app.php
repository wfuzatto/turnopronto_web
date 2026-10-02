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
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'turnopronto',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
