<?php
declare(strict_types=1);

// Renderiza o formulário isoladamente para proteger a disposição enxuta dos campos.
// Não conecta à base de dados ou modifica dados de produção.
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path): string { return '/'.ltrim($path,'/'); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="test">'; }
function icon(string $name, int $size=16): string { return '<span data-icon="'.e($name).'"></span>'; }

function renderDateMode(array $post): string
{
    $_POST=$post;
    $shift=null;
    $categories=[['id'=>1,'name'=>'Garçom']];
    $batch_token='fixture-token';
    ob_start();
    include __DIR__.'/../app/views/company_shift_form.php';
    return ob_get_clean();
}

$single=renderDateMode([]);
$range=renderDateMode([
    'date_mode'=>'range',
    'date_from'=>'2026-10-21',
    'date_to'=>'2026-10-27',
    'date'=>'2026-10-21',
]);

foreach([$single,$range] as $html){
    foreach(['name="date"','name="date_from"','name="date_to"','name="start_time"',
             'name="end_time"','name="date_mode"','data-shift-date-grid',
             'data-range-preview','data-shift-submit'] as $required){
        if(!str_contains($html,$required)) throw new RuntimeException('Campo ausente: '.$required);
    }
    foreach(['data-range-calendar','data-range-weekday','data-range-excluded',
             'shift-range-box','date-chips','shift-range-weekdays'] as $removed){
        if(str_contains($html,$removed)) throw new RuntimeException('Interface antiga ainda aparece: '.$removed);
    }
    if(substr_count($html,'data-shift-range')!==2) throw new RuntimeException('Esperados dois campos de datas do intervalo.');
}

if(!str_contains($single,'class="form-grid three shift-date-fields"'))
    throw new RuntimeException('Modo individual perdeu a grade de três colunas.');
if(!str_contains($range,'class="form-grid three shift-date-fields is-range"'))
    throw new RuntimeException('Modo lote perdeu a grade de duas colunas.');
if(!preg_match('/name="date"[^>]*disabled/',$range))
    throw new RuntimeException('Data individual deve ficar desabilitada no modo lote.');
if(!preg_match('/name="date_from"[^>]*required/',$range) ||
   !preg_match('/name="date_to"[^>]*required/',$range))
    throw new RuntimeException('As datas do intervalo devem ser obrigatórias no modo lote.');
if(!preg_match('/name="date_from"[^>]*disabled/',$single) ||
   !preg_match('/name="date_to"[^>]*disabled/',$single))
    throw new RuntimeException('Campos de lote devem ficar desabilitados no modo único.');

echo "Shift batch simple layout: PASS\n";
