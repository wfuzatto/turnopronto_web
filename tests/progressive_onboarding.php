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

$cpf='52998224725';
$draft=Registration::prepareProfessionalDraft([
    'name'=>'Profissional Cadastro Progressivo',
    'cpf'=>$cpf,
    'password'=>$password,
    'password_confirm'=>$password,
    'categories'=>[$categoryId],
    'legal_accepted'=>'1',
]);

$pending=Registration::startProfessionalFromDraft($draft,'+5535999990001');
if(($pending['role']??'')!=='professional') throw new RuntimeException('Pending role is not professional.');
if(empty($pending['registration_id'])) throw new RuntimeException('Registration request was not created.');

$verified=Registration::verify((string)$pending['registration_id'],(string)($pending['development_code']??'000111'));
$userId=(int)($verified['user']['id']??0);
if(!$userId) throw new RuntimeException('Professional account was not created.');

$user=$pdo->query("SELECT * FROM tp_users WHERE id=".$userId)->fetch();
$professional=$pdo->query("SELECT * FROM tp_professionals WHERE user_id=".$userId)->fetch();
if(!$user||!$professional) throw new RuntimeException('Professional records missing.');
if($user['email']!==null && $user['email']!=='') throw new RuntimeException('Initial professional account unexpectedly requires email.');
if(empty($user['phone_verified_at'])) throw new RuntimeException('WhatsApp was not marked verified.');
if((string)$professional['cpf']!==$cpf) throw new RuntimeException('CPF was not stored as login identity.');
foreach(['rg','birth_date','address','postal_code','city','state','pix_key','pix_key_type','pix_holder_name','pix_holder_document'] as $field){
    if(!empty($professional[$field])) throw new RuntimeException('Progressive field was collected too early: '.$field);
}

$categorySaved=$pdo->prepare('SELECT COUNT(*) FROM tp_professional_categories WHERE professional_id=? AND category_id=?');
$categorySaved->execute([(int)$professional['id'],$categoryId]);
if((int)$categorySaved->fetchColumn()!==1) throw new RuntimeException('Interest category was not saved.');

$state=Data::professionalOnboardingState($userId);
if($state['can_apply']!==false || $state['next_step']!=='identity') throw new RuntimeException('Initial onboarding state is incorrect.');

Data::updateProfessionalOnboardingStep($userId,'identity',[
    'rg'=>'MG12345678',
    'birth_date'=>'1990-05-10',
    'email'=>'progressive-ci@turnopronto.local',
    'headline'=>'Garçom',
]);
$state=Data::professionalOnboardingState($userId);
if(!$state['identity_complete'] || $state['next_step']!=='location') throw new RuntimeException('Identity step did not advance.');

Data::updateProfessionalOnboardingStep($userId,'location',[
    'postal_code'=>'37410000',
    'address'=>'Rua Teste, 100',
    'city'=>'Três Corações',
    'state'=>'MG',
]);
$state=Data::professionalOnboardingState($userId);
if(!$state['location_complete'] || $state['next_step']!=='payment') throw new RuntimeException('Location step did not advance.');

Data::updateProfessionalOnboardingStep($userId,'payment',[
    'pix_key_type'=>'cpf',
    'pix_key'=>$cpf,
    'pix_holder_name'=>'Profissional Cadastro Progressivo',
    'pix_holder_document'=>$cpf,
]);
$state=Data::professionalOnboardingState($userId);
if(!$state['payment_complete'] || $state['next_step']!=='document') throw new RuntimeException('Payment step did not advance.');

$pdo->prepare('INSERT INTO tp_documents (professional_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,"identity","Documento oficial com foto","pending",NULL,"identidade-ci.pdf","application/pdf",NOW())')
    ->execute([(int)$professional['id']]);
$documentId=(int)$pdo->lastInsertId();

$state=Data::professionalOnboardingState($userId);
if(!$state['can_apply'] || $state['identity_verified']) throw new RuntimeException('Professional should be able to apply while identity is pending.');

$opportunities=Data::opportunities($userId,20);
$matching=array_values(array_filter($opportunities,fn($shift)=>(int)$shift['category_id']===$categoryId));
if(!$matching) throw new RuntimeException('Interest-filtered opportunity was not returned.');
$shiftId=(int)$matching[0]['id'];

$result=Data::acceptShift($userId,$shiftId);
if(($result['status']??'')!=='verification_pending' || !empty($result['assignment_id'])) throw new RuntimeException('Unverified professional must only submit interest.');

$application=$pdo->prepare('SELECT id FROM tp_shift_applications WHERE shift_id=? AND professional_id=? LIMIT 1');
$application->execute([$shiftId,(int)$professional['id']]);
$applicationId=(int)$application->fetchColumn();
if(!$applicationId) throw new RuntimeException('Application was not stored.');

$blocked=false;
try{
    Data::approveApplication($companyUserId,$shiftId,$applicationId);
}catch(RuntimeException $e){
    $blocked=str_contains($e->getMessage(),'verificação');
}
if(!$blocked) throw new RuntimeException('Company was able to confirm an unverified professional.');

$pdo->prepare('UPDATE tp_documents SET status="verified",verified_at=NOW() WHERE id=?')->execute([$documentId]);
Data::setProfessionalVerification($adminId,(int)$professional['id'],'verified');
$state=Data::professionalOnboardingState($userId);
if(!$state['profile_verified'] || !$state['identity_verified']) throw new RuntimeException('Final verification did not unlock the professional.');

$assignmentId=Data::approveApplication($companyUserId,$shiftId,$applicationId);
if($assignmentId<=0) throw new RuntimeException('Verified professional could not be confirmed.');

echo "Progressive professional onboarding: PASS\n";
