<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"admin_inline_edit.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$adminId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
$companyId=(int)$pdo->query("SELECT id FROM tp_companies ORDER BY id LIMIT 1")->fetchColumn();
$professionalId=(int)$pdo->query("SELECT id FROM tp_professionals ORDER BY id LIMIT 1")->fetchColumn();
if(!$adminId||!$companyId||!$professionalId) throw new RuntimeException('Admin inline edit fixture missing.');

Data::adminUpdateVerificationField($adminId,'company',$companyId,'company_email','financeiro-ci@empresa.local');
$company=$pdo->query('SELECT company_email FROM tp_companies WHERE id='.$companyId)->fetch();
if(($company['company_email']??'')!=='financeiro-ci@empresa.local') throw new RuntimeException('Company inline field was not saved.');

Data::adminUpdateVerificationField($adminId,'professional',$professionalId,'headline','Atendimento CI');
$professional=$pdo->query('SELECT headline FROM tp_professionals WHERE id='.$professionalId)->fetch();
if(($professional['headline']??'')!=='Atendimento CI') throw new RuntimeException('Professional inline field was not saved.');

$audit=Data::adminAuditLogs(10);
$entry=null;
foreach($audit as $row){
    if(($row['action']??'')==='admin.verification_field_updated'){ $entry=$row; break; }
}
if(!$entry) throw new RuntimeException('Inline field audit was not recorded.');
if(($entry['action_label']??'')!=='Dado cadastral corrigido pelo administrador') throw new RuntimeException('Audit label was not translated.');

echo "Admin inline verification editing: PASS\n";
