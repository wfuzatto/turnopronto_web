<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"notification_flow.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();

$companyUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='empresa@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='juliana@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalId=(int)$pdo->query("SELECT id FROM tp_professionals WHERE user_id=".$professionalUserId." LIMIT 1")->fetchColumn();
$categoryId=(int)$pdo->query("SELECT id FROM tp_job_categories WHERE slug='garcom' LIMIT 1")->fetchColumn();

if(!$companyUserId||!$professionalUserId||!$professionalId||!$categoryId){
    throw new RuntimeException('Demo records required for notification integration test are missing.');
}

// Registra um token sintético para verificar a fila, sem contatar o Firebase.
Push::register($professionalUserId, str_repeat('f', 88), 'android', true);

$shiftId=Data::createShift($companyUserId,[
    'category_id'=>$categoryId,
    'title'=>'Garçom · Teste de notificações',
    'description'=>'Vaga criada pelo teste de integração das notificações.',
    'date'=>date('Y-m-d',strtotime('+2 days')),
    'start_time'=>'18:00',
    'end_time'=>'23:00',
    'value'=>'180.00',
    'required_workers'=>'1',
    'address'=>'Rua de Teste, 100',
    'city'=>'São Paulo',
    'state'=>'SP',
    'dress_code'=>'Social',
    'notes'=>'',
    'acceptance_mode'=>'manual',
]);

$menu=Data::notificationMenu($professionalUserId,20);
$matching=array_values(array_filter($menu['items'],fn($n)=>$n['type']==='matching_shift' && ($n['action_url']??'')==='profissional/vagas/'.$shiftId));
if(!$matching) throw new RuntimeException('Matching-shift notification was not created.');

$queued=$pdo->prepare("SELECT COUNT(*) FROM tp_push_deliveries q JOIN tp_notifications n ON n.id=q.notification_id JOIN tp_push_devices d ON d.id=q.device_id WHERE n.user_id=? AND d.user_id=? AND n.type='matching_shift' AND n.action_url=? AND q.status='pending'");
$queued->execute([$professionalUserId,$professionalUserId,'profissional/vagas/'.$shiftId]);
if((int)$queued->fetchColumn()!==1) throw new RuntimeException('Matching shift was not enqueued exactly once for the device.');

Data::inviteProfessional($companyUserId,$professionalId,$shiftId);
$menu=Data::notificationMenu($professionalUserId,20);
$invites=array_values(array_filter($menu['items'],fn($n)=>$n['type']==='invitation' && ($n['action_url']??'')==='profissional/vagas/'.$shiftId));
if(!$invites) throw new RuntimeException('Invitation notification was not created.');

$inviteId=(int)$invites[0]['id'];
$target=Data::openNotification($professionalUserId,$inviteId);
if($target!=='profissional/vagas/'.$shiftId) throw new RuntimeException('Notification action target is invalid.');
$menu=Data::notificationMenu($professionalUserId,20);
$opened=array_values(array_filter($menu['items'],fn($n)=>(int)$n['id']===$inviteId));
if(!$opened || empty($opened[0]['read_at'])) throw new RuntimeException('Opening a notification did not mark it as read.');

Data::markAllNotificationsRead($professionalUserId);
$menu=Data::notificationMenu($professionalUserId,20);
if((int)$menu['unread']!==0) throw new RuntimeException('Mark-all-read did not clear the unread count.');


// Regressão: o filtro só envia dentro do raio selecionado e deve negar vagas sem coordenadas.
if(!Push::withinDistance(null,null,null,null,null)) throw new RuntimeException('Legacy notifications should be unfiltered.');
if(!Push::withinDistance(5,-23.5505,-46.6333,-23.5505,-46.6333)) throw new RuntimeException('Same point must be within radius.');
if(Push::withinDistance(5,-23.5505,-46.6333,-22.9068,-43.1729)) throw new RuntimeException('Distant vacancy must be excluded.');
if(Push::withinDistance(5,-23.5505,-46.6333,null,null)) throw new RuntimeException('Unknown vacancy must be excluded when radius is set.');
Push::savePreferences($professionalUserId,5,-23.5505,-46.6333);
$distanceSettings=Push::preferences($professionalUserId);
if($distanceSettings['radius_km']!==5) throw new RuntimeException('Radius was not persisted.');
$remoteShift=Data::createShift($companyUserId,[
    'category_id'=>$categoryId,
    'title'=>'Distância · Fora do raio',
    'description'=>'Teste de distância de push',
    'date'=>date('Y-m-d',strtotime('+3 days')),
    'start_time'=>'18:00',
    'end_time'=>'23:00',
    'value'=>'100.00',
    'required_workers'=>'1',
    'address'=>'Av. Atlântica, 1',
    'city'=>'Rio de Janeiro',
    'state'=>'RJ',
    'latitude'=>-22.9068,
    'longitude'=>-43.1729,
    'acceptance_mode'=>'manual',
]);
$rows=Data::notificationMenu($professionalUserId,20);
foreach($rows['items'] as $n){
    if(($n['type']??'')==='matching_shift' && ($n['action_url']??'')==='profissional/vagas/'.$remoteShift)
        throw new RuntimeException('Distant vacancy should not create a matching notification.');
}

echo "Notification flow: PASS\n";
