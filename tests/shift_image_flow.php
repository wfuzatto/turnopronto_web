<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('TP_TEST_DB')!=='tp_ci'){
    fwrite(STDERR,"shift_image_flow.php is CI-only\n");
    exit(1);
}

require __DIR__.'/../app/bootstrap.php';

$pdo=Database::connection();
$companyUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='empresa@turnopronto.local' LIMIT 1")->fetchColumn();
$professionalUserId=(int)$pdo->query("SELECT id FROM tp_users WHERE email='juliana@turnopronto.local' LIMIT 1")->fetchColumn();

if(!$companyUserId || !$professionalUserId){
    throw new RuntimeException('Demo users required for shift image test are missing.');
}

$shift=$pdo->query("SELECT id FROM tp_shifts WHERE starts_at>NOW() ORDER BY id ASC LIMIT 1")->fetch();
if(!$shift) throw new RuntimeException('A future shift is required for shift image test.');

$shiftId=(int)$shift['id'];
$companyShift=Data::companyShift($companyUserId,$shiftId);
if(!$companyShift) throw new RuntimeException('Company shift lookup failed.');

$filename=str_repeat('a',40).'.png';
$relative='storage/uploads/shift_images/'.$filename;
$dir=dirname(__DIR__).'/storage/uploads/shift_images';
if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)){
    throw new RuntimeException('Could not create shift image test directory.');
}

$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl5mJ0AAAAASUVORK5CYII=');
file_put_contents($dir.'/'.$filename,$png);

$pdo->prepare('UPDATE tp_shifts SET image_path=? WHERE id=?')->execute([$relative,$shiftId]);

$companyShift=Data::companyShift($companyUserId,$shiftId);
if(($companyShift['image_url']??'')!=='/media/vagas/'.$filename){
    throw new RuntimeException('Company shift did not expose its image URL.');
}

$opportunities=Data::opportunities($professionalUserId,100);
$professionalMatch=array_values(array_filter($opportunities,fn($row)=>(int)$row['id']===$shiftId));
if($professionalMatch && ($professionalMatch[0]['image_url']??'')!=='/media/vagas/'.$filename){
    throw new RuntimeException('Professional opportunity did not expose its image URL.');
}

$public=Data::publicOpportunities(100);
$publicMatch=array_values(array_filter($public,fn($row)=>(int)$row['id']===$shiftId));
if($publicMatch && ($publicMatch[0]['image_url']??'')!=='/media/vagas/'.$filename){
    throw new RuntimeException('Public opportunity did not expose its image URL.');
}

echo "Shift image flow: PASS\n";
