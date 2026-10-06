<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"progressive_onboarding.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$password=(string)getenv('TP_TEST_PASSWORD');
if(strlen($password)<8) throw new RuntimeException('TP_TEST_PASSWORD missing.');

$categoryId=(int)$pdo->query("SELECT id FROM tp_job_categories WHERE slug='garcom' LIMIT 1")->fetchColumn();
$adminId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='admin' LIMIT 1")->fetchColumn();
$companyUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE role='company' LIMIT 1")->fetchColumn();
if(!$categoryId||!$adminId||!$companyUserId) throw new RuntimeException('Demo dependencies missing.');

$shiftId=(int)$pdo->query("SELECT id FROM tp_shifts WHERE category_id={$categoryId} AND status IN ('published','filling') AND starts_at>NOW() ORDER BY starts_at LIMIT 1")->fetchColumn();
if(!$shiftId) throw new RuntimeException('Matching public shift missing.');

$cpf='52998224725';
$draft=Registration::prepareProfessionalApplicationDraft([
    'name'=>'Profissional Primeira Candidatura',
    'cpf'=>$cpf,
    'birth_date'=>'1990-05-10',
    'password'=>$password,
    'password_confirm'=>$password,
    'legal_accepted'=>'1',
],$categoryId);

if(($draft['categories'][0]??0)!==$categoryId) throw new RuntimeException('Vacancy category was not inherited by application onboarding.');

$pending=Registration::startProfessionalFromDraft($draft,'+5535999990001','first-application-ci@turnopronto.local');
if(($pending['role']??'')!=='professional') throw new RuntimeException('Pending role is not professional.');
if(empty($pending['registration_id'])) throw new RuntimeException('Registration request was not created.');

$verified=Registration::verify((string)$pending['registration_id'],(string)($pending['development_code']??'000111'));
$userId=(int)($verified['user']['id']??0);
if(!$userId) throw new RuntimeException('Professional account was not created.');

$user=$pdo->query("SELECT * FROM tp_users WHERE id=".$userId)->fetch();
$professional=$pdo->query("SELECT * FROM tp_professionals WHERE user_id=".$userId)->fetch();
if(!$user||!$professional) throw new RuntimeException('Professional records missing.');
if((string)$user['email']!=='first-application-ci@turnopronto.local') throw new RuntimeException('Contact email was not stored.');
if(empty($user['phone_verified_at'])) throw new RuntimeException('WhatsApp was not marked verified.');
if((string)$professional['cpf']!==$cpf) throw new RuntimeException('CPF was not stored.');
if((string)$professional['birth_date']!=='1990-05-10') throw new RuntimeException('Birth date from basic step was not stored.');
foreach(['rg','address','postal_code','city','state','pix_key','pix_key_type','pix_holder_name','pix_holder_document'] as $field){
    if(!empty($professional[$field])) throw new RuntimeException('Field was collected before its intended stage: '.$field);
}

$state=Data::professionalOnboardingState($userId);
if($state['application_ready']!==false || $state['payment_complete']!==false) throw new RuntimeException('Application must wait only for payment after contact validation.');

Data::updateProfessionalOnboardingStep($userId,'payment',[
    'pix_key_type'=>'cpf',
    'pix_key'=>$cpf,
    'pix_holder_name'=>'Profissional Primeira Candidatura',
    'pix_holder_document'=>$cpf,
]);

$state=Data::professionalOnboardingState($userId);
if(!$state['application_ready'] || !$state['can_apply']) throw new RuntimeException('Three lightweight steps should unlock application.');
if($state['identity_submitted']) throw new RuntimeException('Identity document must not be required before first application.');

$result=Data::acceptShift($userId,$shiftId);
if(($result['status']??'')!=='verification_pending' || !empty($result['assignment_id'])){
    throw new RuntimeException('First application should be stored before identity verification, without confirming the turn.');
}

$application=$pdo->prepare('SELECT id FROM tp_shift_applications WHERE shift_id=? AND professional_id=? LIMIT 1');
$application->execute([$shiftId,(int)$professional['id']]);
$applicationId=(int)$application->fetchColumn();
if(!$applicationId) throw new RuntimeException('First application was not stored.');

$blocked=false;
try{ Data::approveApplication($companyUserId,$shiftId,$applicationId); }
catch(RuntimeException $e){ $blocked=str_contains($e->getMessage(),'verificação'); }
if(!$blocked) throw new RuntimeException('Company confirmed a professional before TurnoPronto verification.');

Data::updateProfessionalOnboardingStep($userId,'identity',[
    'rg'=>'MG12345678',
    'birth_date'=>'1990-05-10',
    'email'=>'first-application-ci@turnopronto.local',
    'headline'=>'Garçom',
]);
Data::updateProfessionalOnboardingStep($userId,'location',[
    'postal_code'=>'37410000',
    'address'=>'Rua Teste, 100',
    'city'=>'Três Corações',
    'state'=>'MG',
]);

$pdo->prepare('INSERT INTO tp_documents (professional_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,"identity","Documento oficial com foto","verified",NULL,"identidade-ci.pdf","application/pdf",NOW())')
    ->execute([(int)$professional['id']]);

$state=Data::professionalOnboardingState($userId);
if(!$state['verification_data_complete'] || !$state['identity_verified']) throw new RuntimeException('Post-application verification was not completed.');

Data::setProfessionalVerification($adminId,(int)$professional['id'],'verified');
$state=Data::professionalOnboardingState($userId);
if(!$state['profile_verified']) throw new RuntimeException('Final verification did not unlock the professional.');

$assignmentId=Data::approveApplication($companyUserId,$shiftId,$applicationId);
if($assignmentId<=0) throw new RuntimeException('Verified professional could not be confirmed.');

echo "First-application progressive onboarding: PASS\n";
