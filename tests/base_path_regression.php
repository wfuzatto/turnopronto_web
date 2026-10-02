<?php
// Regression test: rotas reescritas não podem contaminar o base_path.
$projectRoot = realpath(dirname(__DIR__));
$documentRoot = dirname($projectRoot);

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SCRIPT_NAME'] = '/profissional/inicio';

require dirname(__DIR__) . '/app/helpers.php';

$expected = '/' . basename($projectRoot);
$actual = base_path();

if ($actual !== $expected) {
    fwrite(STDERR, "base_path inválido. Esperado {$expected}, obtido {$actual}\n");
    exit(1);
}

$asset = asset('css/app.css');
$expectedAsset = $expected . '/public/assets/css/app.css';

if ($asset !== $expectedAsset) {
    fwrite(STDERR, "asset() inválido. Esperado {$expectedAsset}, obtido {$asset}\n");
    exit(1);
}

echo "base_path regression test OK\n";
