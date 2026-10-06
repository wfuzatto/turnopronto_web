<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"admin_ops_flow.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$adminId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
$companyId=(int)$pdo->query("SELECT id FROM tp_companies ORDER BY id LIMIT 1")->fetchColumn();
$professional=$pdo->query("SELECT p.id,p.user_id FROM tp_professionals p ORDER BY p.id LIMIT 1")->fetch();
if(!$adminId||!$companyId||!$professional) throw new RuntimeException('Demo records missing.');

Data::adminUpdateVerificationField($adminId,'company',$companyId,'company_email','operacao-ci@turnopronto.local');
$companyEmail=$pdo->query('SELECT company_email FROM tp_companies WHERE id='.$companyId)->fetchColumn();
if($companyEmail!=='operacao-ci@turnopronto.local') throw new RuntimeException('Admin inline company edit failed.');

Data::adminUpdateVerificationField($adminId,'professional',(int)$professional['id'],'headline','Profissional CI atualizado');
$headline=$pdo->query('SELECT headline FROM tp_professionals WHERE id='.(int)$professional['id'])->fetchColumn();
if($headline!=='Profissional CI atualizado') throw new RuntimeException('Admin inline professional edit failed.');

if(Data::auditActionLabel('verification.company_document_reviewed')!=='Documento da empresa revisado'){
    throw new RuntimeException('Audit translation missing.');
}

// Garante bloqueio real de check-out nos primeiros 15 minutos.
$assignment=$pdo->query("SELECT a.id,a.professional_id,p.user_id
                         FROM tp_assignments a
                         JOIN tp_professionals p ON p.id=a.professional_id
                         ORDER BY a.id LIMIT 1")->fetch();
if($assignment){
    $pdo->prepare('UPDATE tp_assignments SET status="checked_in",checkin_at=NOW(),checkout_at=NULL WHERE id=?')->execute([(int)$assignment['id']]);
    $blocked=false;
    try{ Data::checkOut((int)$assignment['user_id'],(int)$assignment['id']); }
    catch(RuntimeException $e){ $blocked=str_contains($e->getMessage(),'15 minutos'); }
    if(!$blocked) throw new RuntimeException('Checkout minimum interval was not enforced.');

    $pdo->prepare('UPDATE tp_assignments SET checkin_at=DATE_SUB(NOW(),INTERVAL 16 MINUTE) WHERE id=?')->execute([(int)$assignment['id']]);
    Data::checkOut((int)$assignment['user_id'],(int)$assignment['id']);
    $status=$pdo->query('SELECT status FROM tp_assignments WHERE id='.(int)$assignment['id'])->fetchColumn();
    if($status!=='completed') throw new RuntimeException('Checkout should be allowed after 15 minutes.');
}

// Sem provider configurado, análise automática deve cair com segurança para revisão manual.
$tmp=tempnam(sys_get_temp_dir(),'tp-doc-');
file_put_contents($tmp,'not-a-real-image');
$result=DocumentRecognition::analyze($tmp,'image/jpeg','Pessoa Teste','52998224725');
@unlink($tmp);
if(($result['status']??'')!=='manual') throw new RuntimeException('Document recognition fallback must be manual without provider.');

echo "Admin operations enhancements: PASS\n";
