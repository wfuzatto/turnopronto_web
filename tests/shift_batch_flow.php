<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"shift_batch_flow.php is CI-only\n");
    exit(1);
}
require __DIR__.'/../app/bootstrap.php';
$pdo=Database::connection();
$companyId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='empresa@turnopronto.local' LIMIT 1")->fetchColumn();
$categoryId=(int)$pdo->query("SELECT id FROM tp_job_categories WHERE slug='garcom' LIMIT 1")->fetchColumn();
if(!$companyId || !$categoryId) throw new RuntimeException('Missing demo company/category.');

$start=(new DateTimeImmutable('today'))->modify('+10 days');
$dates=[$start->format('Y-m-d'),$start->modify('+1 day')->format('Y-m-d'),$start->modify('+2 days')->format('Y-m-d')];
$payload=[
    'category_id'=>$categoryId,'title'=>'Teste de lote noturno','description'=>'Turnos independentes por dia',
    'date_from'=>$dates[0],'date_to'=>$dates[2],
    'weekdays'=>['0','1','2','3','4','5','6'],'weekdays_present'=>'1',
    'excluded_dates'=>$dates[1],
    'start_time'=>'18:00','end_time'=>'02:00','value'=>'175.50','required_workers'=>'2',
    'address'=>'Rua Teste 100','city'=>'São Paulo','state'=>'SP','acceptance_mode'=>'manual',
];
$ids=Data::createShiftsBatch($companyId,$payload);
if(count($ids)!==2) throw new RuntimeException('Expected two shifts, got '.count($ids));
$st=$pdo->prepare('SELECT starts_at,ends_at,shift_value,required_workers,status FROM tp_shifts WHERE id IN (?,?) ORDER BY starts_at');
$st->execute($ids);
$rows=$st->fetchAll();
foreach($rows as $i=>$row){
    $expectedStart=[$dates[0],$dates[2]][$i].' 18:00:00';
    $expectedEnd=(new DateTimeImmutable($expectedStart))->modify('+8 hours')->format('Y-m-d H:i:s');
    if($row['starts_at']!==$expectedStart || $row['ends_at']!==$expectedEnd || $row['status']!=='published'){
        throw new RuntimeException('Batch shift values/overnight date mismatch: '.json_encode($row));
    }
    if((float)$row['shift_value']!==175.5 || (int)$row['required_workers']!==2) throw new RuntimeException('Shared properties not retained.');
}
$past=$pdo->prepare('INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,checkin_pin,status)
                    SELECT id,?,"Turno antigo sem preencher","Teste de expiração",?,?,100,2,"Rua Teste","São Paulo","SP","654321","filling"
                    FROM tp_companies WHERE user_id=? LIMIT 1');
$past->execute([$categoryId,date('Y-m-d H:i:s',strtotime('-2 days')),date('Y-m-d H:i:s',strtotime('-1 hour')),$companyId]);
$expiredId=(int)$pdo->lastInsertId();
if(!$expiredId) throw new RuntimeException('Could not create expired test fixture.');

$full=$pdo->prepare('INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,checkin_pin,status)
                    SELECT id,?,"Turno antigo preenchido","Teste de expiração",?,?,100,1,"Rua Teste","São Paulo","SP","654321","confirmed"
                    FROM tp_companies WHERE user_id=? LIMIT 1');
$full->execute([$categoryId,date('Y-m-d H:i:s',strtotime('-2 days')),date('Y-m-d H:i:s',strtotime('-1 hour')),$companyId]);
$confirmedId=(int)$pdo->lastInsertId();
Data::closeExpiredShifts();
$verify=$pdo->prepare('SELECT status FROM tp_shifts WHERE id=?');
$verify->execute([$expiredId]);
if($verify->fetchColumn()!=='expired') throw new RuntimeException('Unfilled past shift not marked as expired.');
$verify->execute([$confirmedId]);
if($verify->fetchColumn()!=='confirmed') throw new RuntimeException('Already filled shift should stay confirmed.');
$detail=Data::companyShift($companyId,$expiredId);
if(($detail['status']??'')!=='expired') throw new RuntimeException('Company detail did not show expired.');
if(Data::publicShift($expiredId)!==null) throw new RuntimeException('Expired shift exposed to public.');
echo "Shift batch integration: PASS\n";
