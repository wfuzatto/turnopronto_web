<?php
declare(strict_types=1);
// Cron: * * * * * /usr/bin/php /CAMINHO/turnopronto/scripts/dispatch_push.php 100
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $stats=Push::dispatch(isset($argv[1])?(int)$argv[1]:100);
    echo json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    error_log('TurnoPronto FCM dispatcher: '.$e->getMessage());
    fwrite(STDERR,"Firebase indisponível: verifique credenciais, extensões PHP e rede.\n");
    exit(1);
}
