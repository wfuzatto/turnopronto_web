<?php
// Case 1: XAMPP/subfolder with rewritten route.
$projectRoot = realpath(dirname(__DIR__));
$documentRoot = dirname($projectRoot);

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SCRIPT_NAME'] = '/profissional/inicio';
$_SERVER['HTTP_HOST'] = 'localhost';

require dirname(__DIR__) . '/app/helpers.php';

$expected = '/' . basename($projectRoot);
$actual = base_path();

if ($actual !== $expected) {
    fwrite(STDERR, "base_path local inválido. Esperado {$expected}, obtido {$actual}\n");
    exit(1);
}

$asset = asset('css/app.css');
$expectedAsset = $expected . '/public/assets/css/app.css?v=';

if (!str_starts_with($asset, $expectedAsset)) {
    fwrite(STDERR, "asset local inválido. Esperado prefixo {$expectedAsset}, obtido {$asset}\n");
    exit(1);
}

echo "base_path local regression test OK\n";
