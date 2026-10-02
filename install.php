<?php
declare(strict_types=1);
$projectDir=__DIR__;
$message=''; $error='';
$defaults=['host'=>'127.0.0.1','port'=>'3306','name'=>'turnopronto','user'=>'root','pass'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $demoPassword=(string)($_POST['demo_password']??'');
    if(strlen($demoPassword)<8){ $error='Defina uma senha de demonstração com pelo menos 8 caracteres.'; }
    $cfg=[
      'host'=>trim($_POST['host']??'127.0.0.1'),'port'=>(int)($_POST['port']??3306),'name'=>preg_replace('/[^a-zA-Z0-9_]/','',$_POST['name']??'turnopronto'),
      'user'=>trim($_POST['user']??'root'),'pass'=>(string)($_POST['pass']??'')
    ];
    try{
      if($error!=='') throw new RuntimeException($error);
      $pdo=new PDO("mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4",$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo->exec("USE `{$cfg['name']}`");
      $schema=file_get_contents($projectDir.'/database/schema.sql');
      foreach(preg_split('/;\s*(?:\r?\n|$)/',$schema) as $statement){ $statement=trim($statement); if($statement!=='')$pdo->exec($statement); }

      // Upgrade leve para bancos já existentes: CREATE TABLE IF NOT EXISTS não adiciona colunas novas.
      $hasAcceptance=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tp_shifts' AND COLUMN_NAME='acceptance_mode'")->fetchColumn();
      if(!$hasAcceptance){
          $pdo->exec("ALTER TABLE tp_shifts ADD COLUMN acceptance_mode VARCHAR(20) NOT NULL DEFAULT 'automatic' AFTER checkin_pin");
      }

      $documentColumns=[
          'file_path'=>"VARCHAR(500) NULL",
          'original_name'=>"VARCHAR(255) NULL",
          'mime_type'=>"VARCHAR(100) NULL",
          'rejection_reason'=>"VARCHAR(500) NULL"
      ];
      foreach($documentColumns as $column=>$definition){
          $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tp_documents' AND COLUMN_NAME=?");
          $st->execute([$column]);
          if(!(int)$st->fetchColumn()) $pdo->exec("ALTER TABLE tp_documents ADD COLUMN `".$column."` ".$definition);
      }

      $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
      foreach(['tp_notifications','tp_reputation_events','tp_reviews','tp_ledger','tp_assignments','tp_shift_applications','tp_shifts','tp_professional_categories','tp_documents','tp_company_members','tp_professionals','tp_companies','tp_api_tokens','tp_audit_logs','tp_users','tp_job_categories'] as $table){ $pdo->exec('TRUNCATE TABLE '.$table); }
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
      $pdo->beginTransaction();
      $categories=['Garçom'=>'garcom','Recepcionista'=>'recepcionista','Aux. Cozinha'=>'aux-cozinha','Aux. Limpeza'=>'aux-limpeza','Bartender'=>'bartender','Camareira'=>'camareira','Promotor'=>'promotor','Aux. Eventos'=>'aux-eventos'];
      $st=$pdo->prepare('INSERT INTO tp_job_categories (name,slug) VALUES (?,?)');
      foreach($categories as $n=>$s)$st->execute([$n,$s]);
      $catIds=[]; foreach($pdo->query('SELECT id,slug FROM tp_job_categories')->fetchAll(PDO::FETCH_ASSOC) as $r)$catIds[$r['slug']]=$r['id'];

      $hash=password_hash($demoPassword,PASSWORD_DEFAULT);
      $u=$pdo->prepare('INSERT INTO tp_users (name,email,password_hash,role,phone,status) VALUES (?,?,?,?,?,"active")');
      $u->execute(['Hotel Vale Eventos','empresa@turnopronto.local',$hash,'company','(35) 99999-1000']); $companyUser=(int)$pdo->lastInsertId();
      $u->execute(['Juliana Alves','juliana@turnopronto.local',$hash,'professional','(35) 99999-2000']); $julianaUser=(int)$pdo->lastInsertId();
      $u->execute(['Rafael Lima','rafael@turnopronto.local',$hash,'professional','(35) 99999-3000']); $rafaelUser=(int)$pdo->lastInsertId();
      $u->execute(['Beatriz Moura','beatriz@turnopronto.local',$hash,'professional','(35) 99999-4000']); $beatrizUser=(int)$pdo->lastInsertId();
      $u->execute(['Administrador TurnoPronto','admin@turnopronto.local',$hash,'admin',null]); $adminUser=(int)$pdo->lastInsertId();

      $pdo->prepare('INSERT INTO tp_companies (legal_name,trade_name,cnpj,address,city,state,latitude,longitude,rating,reliability_score,status) VALUES (?,?,?,?,?,?,?,?,4.90,98,"verified")')
          ->execute(['Hotel Vale Eventos Ltda','Hotel Vale Eventos','12.345.678/0001-90','Av. das Nações Unidas, 12551','São Paulo','SP',-23.5928,-46.6887]);
      $companyId=(int)$pdo->lastInsertId();
      $pdo->prepare('INSERT INTO tp_company_members (company_id,user_id,member_role) VALUES (?,? ,"owner")')->execute([$companyId,$companyUser]);

      $p=$pdo->prepare('INSERT INTO tp_professionals (user_id,cpf,headline,city,state,reliability_score,punctuality_score,attendance_score,rating,completed_shifts,status) VALUES (?,?,?,?,?,?,?,?,?,?,"verified")');
      $p->execute([$julianaUser,'123.456.789-09','Garçom • Recepcionista','São Paulo','SP',97,98,98,4.9,42]); $julianaId=(int)$pdo->lastInsertId();
      $p->execute([$rafaelUser,'987.654.321-00','Aux. Cozinha • Aux. Limpeza','São Paulo','SP',96,96,96,4.8,31]); $rafaelId=(int)$pdo->lastInsertId();
      $p->execute([$beatrizUser,'111.222.333-44','Recepcionista • Atendimento','São Paulo','SP',99,99,99,4.9,57]); $beatrizId=(int)$pdo->lastInsertId();
      foreach([[$julianaId,'garcom'],[$julianaId,'recepcionista'],[$rafaelId,'aux-cozinha'],[$rafaelId,'aux-limpeza'],[$beatrizId,'recepcionista']] as [$pid,$slug])
          $pdo->prepare('INSERT INTO tp_professional_categories (professional_id,category_id,experience_level) VALUES (?,?,"experienced")')->execute([$pid,$catIds[$slug]]);

      $doc=$pdo->prepare('INSERT INTO tp_documents (professional_id,type,label,status,verified_at) VALUES (?,?,?,"verified",NOW())');
      foreach([['identity','Documento de identidade'],['cpf','CPF'],['address','Comprovante de residência'],['food','Certificado de manipulação de alimentos']] as [$type,$label])$doc->execute([$julianaId,$type,$label]);

      $shift=$pdo->prepare('INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,dress_code,notes,checkin_pin,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
      $today=new DateTime('today');
      $make=function(int $add,string $start,string $end,string $slug,string $title,float $value,int $qty,string $status='published')use($shift,$companyId,$catIds,$today,$pdo){
          $d=(clone $today)->modify("+$add day"); $startAt=$d->format('Y-m-d').' '.$start.':00';
          $ed=(clone $d); if($end<=$start)$ed->modify('+1 day'); $endAt=$ed->format('Y-m-d').' '.$end.':00';
          $shift->execute([$companyId,$catIds[$slug],$title,'Oportunidade TurnoPronto para reforço operacional.',$startAt,$endAt,$value,$qty,'Av. das Nações Unidas, 12551','São Paulo','SP','Calça preta, camisa branca e sapato social preto.','Seja pontual e compareça com documento de identificação.',sprintf('%06d', random_int(0, 999999)),$status]);
          return (int)$pdo->lastInsertId();
      };
      $s1=$make(1,'18:00','02:00','garcom','Garçom',160,3,'published');
      $s2=$make(2,'14:00','22:00','recepcionista','Recepcionista',180,2,'published');
      $s3=$make(3,'08:00','16:00','aux-cozinha','Aux. Cozinha',150,2,'filling');
      $s4=$make(4,'12:00','20:00','garcom','Garçom',150,4,'published');
      $s5=$make(5,'08:00','16:00','aux-limpeza','Aux. Limpeza',140,2,'published');
      $sToday=$make(0,'18:00','23:59','garcom','Garçom',160,2,'confirmed');

      // Deixa duas vagas em aprovação manual para validar o fluxo de candidatos no painel da empresa.
      $pdo->prepare('UPDATE tp_shifts SET acceptance_mode="manual" WHERE id IN (?,?)')->execute([$s1,$s2]);

      $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,?,"accepted",NOW())')->execute([$sToday,$julianaId]);
      $pdo->prepare('INSERT INTO tp_assignments (shift_id,professional_id,status,agreed_value,confirmed_at) VALUES (?,? ,"confirmed",160,NOW())')->execute([$sToday,$julianaId]);
      $assignmentToday=(int)$pdo->lastInsertId();
      foreach([[$s1,$rafaelId],[$s2,$beatrizId]] as [$sid,$pid])$pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,? ,"applied",NOW())')->execute([$sid,$pid]);

      $ledger=$pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"credit",?,"shift_payment","paid",?)');
      foreach([[980,'-3 months'],[1250,'-2 months'],[1850,'-1 month'],[2340,'now']] as [$amount,$mod])$ledger->execute([$companyId,$julianaId,$assignmentToday,$amount,date('Y-m-d H:i:s',strtotime($mod))]);
      // Lançamento de custo de demonstração para o painel financeiro da empresa.
      $pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"debit",160,"shift_cost","settled",NOW())')->execute([$companyId,$julianaId,$assignmentToday]);
      $pdo->prepare('INSERT INTO tp_reputation_events (professional_id,event_type,severity,points_delta,description,occurred_at) VALUES (?,"completed_shift","positive",1,"Turno concluído com pontualidade.",DATE_SUB(NOW(),INTERVAL 7 DAY))')->execute([$julianaId]);
      $pdo->commit();

      $local="<?php\nreturn ".var_export(['db'=>['host'=>$cfg['host'],'port'=>$cfg['port'],'name'=>$cfg['name'],'user'=>$cfg['user'],'pass'=>$cfg['pass'],'charset'=>'utf8mb4']],true).";\n";
      file_put_contents($projectDir.'/config/config.local.php',$local);
      $message='Instalação concluída. Use os usuários de demonstração abaixo.';
    }catch(Throwable $e){ if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack(); $error=$e->getMessage(); }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar TurnoPronto</title>
<style>body{font-family:Inter,Arial,sans-serif;background:#f4f8ff;color:#10213d;margin:0}.wrap{max-width:760px;margin:48px auto;padding:0 20px}.card{background:white;border:1px solid #e4ebf5;border-radius:20px;padding:30px;box-shadow:0 18px 50px #173c7014}h1{margin-top:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}input{width:100%;box-sizing:border-box;border:1px solid #d7e0ed;border-radius:10px;padding:12px}.full{grid-column:1/-1}button,a.btn{border:0;border-radius:10px;background:#0c63ff;color:#fff;padding:13px 18px;font-weight:700;text-decoration:none;display:inline-block}.ok{background:#e8fbef;color:#13783b;padding:12px;border-radius:10px}.err{background:#fff1f1;color:#a22;padding:12px;border-radius:10px}.demo{background:#f7f9fc;border-radius:12px;padding:15px;margin-top:18px;line-height:1.8}@media(max-width:600px){.grid{grid-template-columns:1fr}}</style></head><body><div class="wrap"><div class="card"><h1>TurnoPronto • Instalação XAMPP</h1><p>Cria o banco MariaDB, tabelas e dados de demonstração. O padrão do XAMPP costuma ser <b>root</b> sem senha.</p>
<?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><div class="demo"><b>Empresa:</b> empresa@turnopronto.local<br><b>Profissional:</b> juliana@turnopronto.local<br><b>Admin:</b> admin@turnopronto.local<br><small>Use a senha de demonstração definida por você nesta instalação.</small></div><p><a class="btn" href="./login">Abrir o TurnoPronto</a></p><?php else:?>
<?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="post"><div class="grid"><div><label>Host</label><input name="host" value="<?=htmlspecialchars($_POST['host']??$defaults['host'])?>"></div><div><label>Porta</label><input name="port" value="<?=htmlspecialchars($_POST['port']??$defaults['port'])?>"></div><div><label>Banco</label><input name="name" value="<?=htmlspecialchars($_POST['name']??$defaults['name'])?>"></div><div><label>Usuário</label><input name="user" value="<?=htmlspecialchars($_POST['user']??$defaults['user'])?>"></div><div class="full"><label>Senha do banco</label><input type="password" name="pass" value=""></div><div class="full"><label>Senha dos usuários de demonstração *</label><input type="password" name="demo_password" minlength="8" required autocomplete="new-password" placeholder="Defina uma senha somente para este ambiente"></div><div class="full"><button>Instalar / recriar ambiente de demonstração</button></div></div></form><?php endif;?></div></div></body></html>
