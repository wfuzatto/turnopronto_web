<?php
// Only the isolated CI database may be recreated by this installer.
if (PHP_SAPI !== 'cli' || getenv('TP_TEST_DB') !== 'tp_ci') exit(1);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['host'=>'127.0.0.1','port'=>3306,'name'=>'tp_ci','user'=>'root',
    'pass'=>getenv('TP_TEST_DB_PASSWORD'),'demo_password'=>getenv('TP_TEST_PASSWORD')];
ob_start();
require __DIR__ . '/../install.php';
ob_end_clean();
if ($error !== '') throw new RuntimeException('CI database setup failed');
