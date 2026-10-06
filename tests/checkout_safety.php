<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"checkout_safety.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$userId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='juliana@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalId=(int)$pdo->query("SELECT id FROM tp_professionals WHERE user_id=".$userId." LIMIT 1")->fetchColumn();
$st=$pdo->prepare("SELECT a.id FROM tp_assignments a WHERE a.professional_id=? AND a.status IN ('confirmed','checked_in') ORDER BY a.id LIMIT 1");
$st->execute([$professionalId]);
$assignmentId=(int)$st->fetchColumn();
if(!$userId||!$professionalId||!$assignmentId) throw new RuntimeException('Checkout safety fixture missing.');

$pdo->prepare('UPDATE tp_assignments SET status="checked_in",checkin_at=NOW(),checkout_at=NULL WHERE id=?')->execute([$assignmentId]);

$blocked=false;
try{
    Data::checkOut($userId,$assignmentId);
}catch(RuntimeException $e){
    $blocked=str_contains($e->getMessage(),'15 minutos');
}
if(!$blocked) throw new RuntimeException('Checkout was not blocked immediately after check-in.');

$pdo->prepare('UPDATE tp_assignments SET checkin_at=DATE_SUB(NOW(),INTERVAL 16 MINUTE) WHERE id=?')->execute([$assignmentId]);
Data::checkOut($userId,$assignmentId);

$status=$pdo->prepare('SELECT status FROM tp_assignments WHERE id=?');
$status->execute([$assignmentId]);
if((string)$status->fetchColumn()!=='completed') throw new RuntimeException('Checkout did not unlock after 15 minutes.');

echo "Checkout 15-minute safety: PASS\n";
