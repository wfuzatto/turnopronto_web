<?php
// Run each render in a subprocess because View::render terminates the request.
if (isset($argv[1])) {
    require __DIR__ . '/../app/helpers.php';
    require __DIR__ . '/../app/View.php';
    final class Auth {
        public static function check(): bool { return true; }
        public static function dashboardPath(array $user): string { return 'empresa/dashboard'; }
    }
    final class Data {
        public static function companyProfile(int $id): array { return ['trade_name'=>'Empresa Diagnóstico']; }
        public static function notificationMenu(int $id,int $limit=6): array { return ['unread'=>0,'items'=>[]]; }
    }
    $_SESSION = [];
    $role = $argv[1];
    $payload = $role === 'company'
        ? ['company'=>['trade_name'=>'Empresa Diagnóstico','status'=>'verified'],
           'kpis'=>['open'=>7,'today'=>2,'fill_rate'=>80,'spend'=>123.45], 'shifts'=>[], 'professionals'=>[]]
        : ['profile'=>['name'=>'Profissional Diagnóstico','status'=>'verified','headline'=>'Atendimento',
                      'attendance_score'=>98,'punctuality_score'=>99],
           'onboarding'=>[
               'can_apply'=>true,'application_ready'=>true,'profile_verified'=>true,'identity_submitted'=>true,'progress'=>100,'next_step'=>'done',
               'steps'=>['account'=>true,'whatsapp'=>true,'interests'=>true,'identity_data'=>true,'location'=>true,'payment'=>true,'identity_document'=>true]
           ],
           'kpis'=>['week'=>7,'earnings'=>123.45,'reliability'=>98,'punctuality'=>99],
           'opportunities'=>[], 'assignments'=>[], 'documents'=>[]];
    View::render($role === 'company' ? 'company_dashboard' : 'professional_dashboard', [
        'title'=>'Diagnóstico', 'data'=>$payload, 'user'=>['id'=>1,'role'=>$role,'name'=>'Diagnóstico']
    ]);
}
foreach (['company', 'professional'] as $role) {
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=24567', __FILE__, $role],
        [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    $html = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || $errors !== '') throw new RuntimeException("$role render failed: $errors");
    foreach (['<!doctype html>', '<html', 'app-shell', 'sidebar', 'topbar', 'main-content',
              'logo.svg', 'css/app.css', 'kpi-grid', 'tp-table', 'R$ 123,45', '</html>',
              $role === 'company' ? 'Empresa Diagnóstico' : 'Profissional Diagnóstico'] as $marker) {
        if (!str_contains($html, $marker)) throw new RuntimeException("$role missing $marker");
    }
    echo "$role dashboard: PASS\n";
}
