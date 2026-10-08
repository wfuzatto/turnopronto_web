<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"shift_following.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$companyUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='empresa@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='juliana@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalId=(int)$pdo->query("SELECT id FROM tp_professionals WHERE user_id=".$professionalUserId." LIMIT 1")->fetchColumn();
$categoryId=(int)$pdo->query("SELECT id FROM tp_job_categories WHERE slug='garcom' LIMIT 1")->fetchColumn();

if(!$companyUserId||!$professionalUserId||!$professionalId||!$categoryId){
    throw new RuntimeException('Demo records required for shift-following test are missing.');
}

$payload=[
    'category_id'=>$categoryId,
    'title'=>'Garçom · Teste acompanhar vaga',
    'description'=>'Teste do interesse sem compromisso.',
    'date'=>date('Y-m-d',strtotime('+4 days')),
    'start_time'=>'18:00',
    'end_time'=>'23:00',
    'value'=>'180.00',
    'required_workers'=>'2',
    'address'=>'Rua de Teste, 200',
    'city'=>'São Paulo',
    'state'=>'SP',
    'dress_code'=>'Social',
    'notes'=>'',
    'acceptance_mode'=>'manual',
];

$shiftId=Data::createShift($companyUserId,$payload);
Data::followShift($professionalUserId,$shiftId);

if(!Data::isFollowingShift($professionalUserId,$shiftId)){
    throw new RuntimeException('Professional should be following the shift.');
}

$followers=Data::companyShiftFollowers($companyUserId,$shiftId);
$match=array_values(array_filter($followers,fn($row)=>(int)$row['professional_id']===$professionalId));
if(!$match){
    throw new RuntimeException('Company cannot see the interested professional.');
}

$payload['value']='195.00';
$payload['notes']='Orientação alterada pelo teste.';
Data::updateShift($companyUserId,$shiftId,$payload);

$notifications=Data::notifications($professionalUserId,50);
$updates=array_values(array_filter(
    $notifications,
    fn($row)=>$row['type']==='shift_updated' && ($row['action_url']??'')==='profissional/vagas/'.$shiftId
));
if(!$updates){
    throw new RuntimeException('Following professional did not receive shift update notification.');
}

Data::unfollowShift($professionalUserId,$shiftId);
if(Data::isFollowingShift($professionalUserId,$shiftId)){
    throw new RuntimeException('Professional should no longer be following the shift.');
}

echo "Shift following: PASS\n";
