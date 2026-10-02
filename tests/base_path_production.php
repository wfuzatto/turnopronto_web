<?php
$_SERVER['HTTP_HOST'] = 'turnopronto1.hospedagemdesites.ws';
$_SERVER['SCRIPT_NAME'] = '/empresa/dashboard';
$_SERVER['DOCUMENT_ROOT'] = '/home/fake/public_html';

require dirname(__DIR__) . '/app/helpers.php';

if (base_path() !== '') {
    fwrite(STDERR, "Produção deve usar a raiz pública do domínio. Obtido: " . base_path() . "\n");
    exit(1);
}

if (asset('css/app.css') !== '/public/assets/css/app.css') {
    fwrite(STDERR, "CSS de produção calculado incorretamente: " . asset('css/app.css') . "\n");
    exit(1);
}

if (url('empresa/dashboard') !== '/empresa/dashboard') {
    fwrite(STDERR, "URL de produção calculada incorretamente: " . url('empresa/dashboard') . "\n");
    exit(1);
}

echo "base_path production regression test OK\n";
