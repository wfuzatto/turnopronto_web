<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

date_default_timezone_set(app_config('timezone') ?: 'America/Sao_Paulo');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(app_config('session_name') ?: 'turnopronto_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path' => base_path() ?: '/',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Data.php';
require_once __DIR__ . '/View.php';

require_once __DIR__ . '/Api.php';
require_once __DIR__ . '/Web.php';
