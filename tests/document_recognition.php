<?php
declare(strict_types=1);

require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/DocumentRecognition.php';

$expectedCpf='52998224725';

$verified=DocumentRecognition::evaluateResponse([
    'detected_document_type'=>'cin',
    'fields'=>['name'=>'LETICIA REIS','cpf'=>$expectedCpf],
    'name_validation'=>['status'=>'match','extracted'=>'LETICIA REIS','score'=>99],
],$expectedCpf);
if(($verified['decision']??'')!=='verified') throw new RuntimeException('Matching name/CPF should auto-verify.');

$cpfMismatch=DocumentRecognition::evaluateResponse([
    'detected_document_type'=>'cnh',
    'fields'=>['name'=>'LETICIA REIS','cpf'=>'12345678909'],
    'name_validation'=>['status'=>'match','extracted'=>'LETICIA REIS','score'=>99],
],$expectedCpf);
if(($cpfMismatch['decision']??'')!=='manual' || !empty($cpfMismatch['cpf_match'])) throw new RuntimeException('CPF divergence must require manual review.');

$nameReview=DocumentRecognition::evaluateResponse([
    'detected_document_type'=>'rg',
    'fields'=>['name'=>'LETICIA R.','cpf'=>$expectedCpf],
    'name_validation'=>['status'=>'review','extracted'=>'LETICIA R.','score'=>78],
],$expectedCpf);
if(($nameReview['decision']??'')!=='manual' || !empty($nameReview['name_match'])) throw new RuntimeException('Uncertain name must require manual review.');

$unknown=DocumentRecognition::evaluateResponse([
    'detected_document_type'=>'unknown',
    'fields'=>['name'=>'LETICIA REIS','cpf'=>$expectedCpf],
    'name_validation'=>['status'=>'match','extracted'=>'LETICIA REIS','score'=>99],
],$expectedCpf);
if(($unknown['decision']??'')!=='manual') throw new RuntimeException('Unknown document type must require manual review.');

echo "Document recognition decision policy: PASS\n";
