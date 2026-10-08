<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"support_flow.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$companyUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='company' ORDER BY id LIMIT 1")->fetchColumn();
$professionalUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='professional' ORDER BY id LIMIT 1")->fetchColumn();
$adminUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
if(!$companyUserId||!$professionalUserId||!$adminUserId) throw new RuntimeException('Support test users missing.');

$companyCategories=Data::supportCategories('company');
$professionalCategories=Data::supportCategories('professional');
if(!isset($companyCategories['finance'],$companyCategories['professionals'])) throw new RuntimeException('Company support categories incomplete.');
if(!isset($professionalCategories['payment'],$professionalCategories['reputation'])) throw new RuntimeException('Professional support categories incomplete.');
if(isset($professionalCategories['professionals'])) throw new RuntimeException('Support segments are not differentiated.');

$companyTicket=Data::createSupportTicket($companyUserId,'company',[
    'category'=>'finance',
    'subject'=>'Dúvida de cobrança CI',
    'context_ref'=>'pagamento CI-001',
    'message'=>'Precisamos conferir uma cobrança utilizada no teste de integração.',
]);
$professionalTicket=Data::createSupportTicket($professionalUserId,'professional',[
    'category'=>'payment',
    'subject'=>'Pagamento de turno CI',
    'context_ref'=>'turno CI-002',
    'message'=>'Preciso de ajuda para entender o status do pagamento deste turno.',
]);
if($companyTicket<=0||$professionalTicket<=0) throw new RuntimeException('Support tickets were not created.');

$companyDashboard=Data::supportDashboard($companyUserId,'company');
if(!array_filter($companyDashboard['tickets'],fn($t)=>(int)$t['id']===$companyTicket)) throw new RuntimeException('Company cannot see its support ticket.');
$professionalDashboard=Data::supportDashboard($professionalUserId,'professional');
if(!array_filter($professionalDashboard['tickets'],fn($t)=>(int)$t['id']===$professionalTicket)) throw new RuntimeException('Professional cannot see its support ticket.');

$adminDashboard=Data::supportDashboard($adminUserId,'admin');
$ids=array_map(fn($t)=>(int)$t['id'],$adminDashboard['tickets']);
if(!in_array($companyTicket,$ids,true)||!in_array($professionalTicket,$ids,true)) throw new RuntimeException('Admin support queue is incomplete.');

$denied=false;
try{ Data::supportTicket($professionalUserId,'professional',$companyTicket); }
catch(RuntimeException $e){ $denied=true; }
if(!$denied) throw new RuntimeException('Support ticket ownership isolation failed.');

$before=Data::supportTicket($professionalUserId,'professional',$professionalTicket);
$lastBefore=(int)end($before['messages'])['id'];

Data::addSupportMessage($adminUserId,'admin',$professionalTicket,'Resposta do suporte no teste de integração.');
$updates=Data::supportTicketUpdates($professionalUserId,'professional',$professionalTicket,$lastBefore);
if(($updates['status']??'')!=='answered') throw new RuntimeException('Realtime support status did not report the admin reply.');
if(count($updates['messages']??[])!==1) throw new RuntimeException('Realtime support endpoint did not return exactly the new admin message.');
if(($updates['messages'][0]['body']??'')!=='Resposta do suporte no teste de integração.') throw new RuntimeException('Realtime support returned the wrong message.');
if(empty($updates['messages'][0]['from_support'])) throw new RuntimeException('Realtime support did not identify the support author.');

$detail=Data::supportTicket($professionalUserId,'professional',$professionalTicket);
if(($detail['ticket']['status']??'')!=='answered') throw new RuntimeException('Admin reply did not update ticket status.');
if(count($detail['messages'])<2) throw new RuntimeException('Support conversation did not store the admin reply.');

$deniedUpdates=false;
try{ Data::supportTicketUpdates($companyUserId,'company',$professionalTicket,0); }
catch(RuntimeException $e){ $deniedUpdates=true; }
if(!$deniedUpdates) throw new RuntimeException('Realtime support endpoint ownership isolation failed.');

Data::addSupportMessage($professionalUserId,'professional',$professionalTicket,'Obrigado. Estou respondendo ao suporte pelo chamado.');
$detail=Data::supportTicket($adminUserId,'admin',$professionalTicket);
if(($detail['ticket']['status']??'')!=='open') throw new RuntimeException('Requester reply did not reopen the support queue.');

Data::setSupportTicketStatus($adminUserId,$professionalTicket,'closed');
$detail=Data::supportTicket($professionalUserId,'professional',$professionalTicket);
if(($detail['ticket']['status']??'')!=='closed') throw new RuntimeException('Admin could not close support ticket.');

echo "Segmented support flow: PASS\n";
