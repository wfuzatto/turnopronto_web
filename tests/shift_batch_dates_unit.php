<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';

$base=(new DateTimeImmutable('today'))->modify('+5 days');
$end=$base->modify('+3 days');
$from=$base->format('Y-m-d');
$to=$end->format('Y-m-d');
$excluded=$base->modify('+1 day')->format('Y-m-d');
$dates=Data::shiftBatchDates([
    'date_from'=>$from,'date_to'=>$to,
    'weekdays'=>['0','1','2','3','4','5','6'],
    'weekdays_present'=>'1','excluded_dates'=>$excluded,
]);
$expected=[$from,$base->modify('+2 days')->format('Y-m-d'),$to];
if($dates!==$expected) throw new RuntimeException('Intervalo inclusivo/exclusões inválidos: '.json_encode($dates));

$onlyWeekday=(int)$base->format('w');
$one=Data::shiftBatchDates([
    'date_from'=>$from,'date_to'=>$to,'weekdays'=>[(string)$onlyWeekday],
    'weekdays_present'=>'1',
]);
if($one!==[$from]) throw new RuntimeException('Filtro por dia da semana falhou.');

foreach([
    ['date_from'=>'2026-02-30','date_to'=>'2026-03-01'],
    ['date_from'=>$to,'date_to'=>$from],
    ['date_from'=>$from,'date_to'=>$base->modify('+90 days')->format('Y-m-d')],
    ['date_from'=>$from,'date_to'=>$to,'weekdays'=>[],'weekdays_present'=>'1'],
    ['date_from'=>$from,'date_to'=>$to,'weekdays'=>['9'],'weekdays_present'=>'1'],
    ['date_from'=>$from,'date_to'=>$from,'excluded_dates'=>$from],
] as $bad){
    try{ Data::shiftBatchDates($bad); }
    catch(InvalidArgumentException $e){ continue; }
    throw new RuntimeException('Intervalo inválido aceito: '.json_encode($bad));
}

// Formulário enxuto envia apenas duas datas; sem filtros, publica todos os dias inclusivos.
$sequential=Data::shiftBatchDates(['date_from'=>$from,'date_to'=>$to]);
if($sequential!==[$from,$base->modify('+1 day')->format('Y-m-d'),
    $base->modify('+2 days')->format('Y-m-d'),$to]){
    throw new RuntimeException('Intervalo simples sem filtros não gerou todos os dias.');
}

echo "Shift batch date unit: PASS\n";
