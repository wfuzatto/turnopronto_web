<?php
require __DIR__ . '/app/bootstrap.php';
$path = request_path();
$method = request_method();
if (str_starts_with($path, '/api/v1')) Api::handle($path,$method);
Web::handle($path,$method);
