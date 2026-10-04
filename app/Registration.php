<?php
final class Registration
{
    private static function hasColumn(string $table,string $column): bool
    {
        $st=Database::connection()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$table,$column]);
        return (int)$st->fetchColumn()>0;
    }

    public static function ensureSchema(): void
    {
        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_registration_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id CHAR(32) NOT NULL UNIQUE,
            role VARCHAR(30) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            document VARCHAR(30) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            sent_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
            last_sent_at DATETIME NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            INDEX idx_reg_email_status (email,status),
            INDEX idx_reg_phone_status (phone,status),
            INDEX idx_reg_expiry (status,code_expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_consents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            consent_type VARCHAR(50) NOT NULL,
            version VARCHAR(40) NOT NULL,
            accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            UNIQUE KEY uq_user_consent_version (user_id,consent_type,version),
            CONSTRAINT fk_consents_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns=[
            ['tp_users','phone_verified_at','DATETIME NULL AFTER phone'],
            ['tp_professionals','rg','VARCHAR(30) NULL AFTER cpf'],
            ['tp_professionals','birth_date','DATE NULL AFTER rg'],
            ['tp_professionals','address','VARCHAR(255) NULL AFTER bio'],
            ['tp_professionals','postal_code','VARCHAR(12) NULL AFTER address'],
            ['tp_professionals','pix_key_type','VARCHAR(30) NULL AFTER pix_key'],
            ['tp_professionals','pix_holder_name','VARCHAR(190) NULL AFTER pix_key_type'],
            ['tp_professionals','pix_holder_document','VARCHAR(30) NULL AFTER pix_holder_name'],
            ['tp_companies','responsible_cpf','VARCHAR(20) NULL AFTER cnpj'],
            ['tp_companies','postal_code','VARCHAR(12) NULL AFTER address'],
            ['tp_companies','pix_key_type','VARCHAR(30) NULL AFTER state'],
            ['tp_companies','pix_key','VARCHAR(190) NULL AFTER pix_key_type'],
            ['tp_companies','pix_holder_name','VARCHAR(190) NULL AFTER pix_key'],
            ['tp_companies','pix_holder_document','VARCHAR(30) NULL AFTER pix_holder_name'],
        ];
        foreach($columns as [$table,$column,$definition]){
            if(!self::hasColumn($table,$column)){
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    public static function start(array $input,string $role): array
    {
        self::ensureSchema();
        if(!in_array($role,['company','professional'],true)) throw new InvalidArgumentException('Tipo de cadastro inválido.');

        $payload=self::validatePayload($input,$role);
        $pdo=Database::connection();

        $st=$pdo->prepare('SELECT id FROM tp_users WHERE email=? LIMIT 1');
        $st->execute([$payload['email']]);
        if($st->fetchColumn()) throw new RuntimeException('Já existe uma conta com este e-mail.');

        if($role==='professional'){
            $st=$pdo->prepare('SELECT id FROM tp_professionals WHERE cpf=? LIMIT 1');
            $st->execute([$payload['cpf']]);
            if($st->fetchColumn()) throw new RuntimeException('Já existe uma conta com este CPF.');
        }else{
            $st=$pdo->prepare('SELECT id FROM tp_companies WHERE cnpj=? LIMIT 1');
            $st->execute([$payload['cnpj']]);
            if($st->fetchColumn()) throw new RuntimeException('Já existe uma empresa com este CNPJ.');
        }

        $pdo->prepare("UPDATE tp_registration_requests SET status='superseded' WHERE email=? AND status IN ('pending','delivery_failed')")
            ->execute([$payload['email']]);

        $publicId=bin2hex(random_bytes(16));
        $developmentBypass=self::developmentWhatsappBypass();
        $code=$developmentBypass?self::developmentCode():(string)random_int(100000,999999);
        $hash=password_hash($code,PASSWORD_DEFAULT);
        $expires=date('Y-m-d H:i:s',time()+600);
        $document=$role==='professional'?$payload['cpf']:$payload['cnpj'];

        $st=$pdo->prepare("INSERT INTO tp_registration_requests
            (public_id,role,email,phone,document,payload_json,code_hash,code_expires_at,attempts,sent_count,last_sent_at,status,created_at)
            VALUES (?,?,?,?,?,?,?, ?,0,1,NOW(),'pending',NOW())");
        $st->execute([
            $publicId,$role,$payload['email'],$payload['phone'],$document,
            json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            $hash,$expires
        ]);

        if(!$developmentBypass){
            try{
                WhatsApp::sendVerificationCode($payload['phone'],$code);
            }catch(Throwable $e){
                $pdo->prepare("UPDATE tp_registration_requests SET status='delivery_failed' WHERE public_id=?")->execute([$publicId]);
                throw $e;
            }
        }

        $result=[
            'registration_id'=>$publicId,
            'phone_masked'=>self::maskPhone($payload['phone']),
            'expires_in'=>600,
            'development_bypass'=>$developmentBypass,
        ];
        if($developmentBypass) $result['development_code']=self::developmentCode();
        return $result;
    }

    public static function resend(string $publicId): array
    {
        self::ensureSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT * FROM tp_registration_requests WHERE public_id=? AND status IN ('pending','delivery_failed') LIMIT 1");
        $st->execute([$publicId]);
        $row=$st->fetch();
        if(!$row) throw new RuntimeException('Cadastro pendente não encontrado.');
        if((int)$row['sent_count']>=5) throw new RuntimeException('Limite de reenvios atingido. Inicie o cadastro novamente.');
        $last=strtotime((string)$row['last_sent_at']);
        if($last && time()-$last<60) throw new RuntimeException('Aguarde 60 segundos antes de reenviar o código.');

        $developmentBypass=self::developmentWhatsappBypass();
        $code=$developmentBypass?self::developmentCode():(string)random_int(100000,999999);
        $hash=password_hash($code,PASSWORD_DEFAULT);
        if(!$developmentBypass) WhatsApp::sendVerificationCode((string)$row['phone'],$code);
        $pdo->prepare("UPDATE tp_registration_requests SET code_hash=?,code_expires_at=?,attempts=0,sent_count=sent_count+1,last_sent_at=NOW(),status='pending' WHERE id=?")
            ->execute([$hash,date('Y-m-d H:i:s',time()+600),(int)$row['id']]);

        $result=['registration_id'=>$publicId,'phone_masked'=>self::maskPhone((string)$row['phone']),'expires_in'=>600,'development_bypass'=>$developmentBypass];
        if($developmentBypass) $result['development_code']=self::developmentCode();
        return $result;
    }

    public static function verify(string $publicId,string $code): array
    {
        self::ensureSchema();
        $code=preg_replace('/\D+/','',$code);
        if(strlen($code)!==6) throw new InvalidArgumentException('Informe o código de 6 dígitos.');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare("SELECT * FROM tp_registration_requests WHERE public_id=? FOR UPDATE");
            $st->execute([$publicId]);
            $row=$st->fetch();
            if(!$row || $row['status']!=='pending') throw new RuntimeException('Cadastro pendente não encontrado.');
            if(strtotime((string)$row['code_expires_at'])<time()) throw new RuntimeException('Código expirado. Solicite um novo código.');
            if((int)$row['attempts']>=5) throw new RuntimeException('Limite de tentativas atingido. Solicite um novo código.');

            if(!password_verify($code,(string)$row['code_hash'])){
                $attempts=(int)$row['attempts']+1;
                $pdo->prepare('UPDATE tp_registration_requests SET attempts=? WHERE id=?')->execute([$attempts,(int)$row['id']]);
                $pdo->commit();
                throw new RuntimeException('Código inválido.');
            }

            $payload=json_decode((string)$row['payload_json'],true);
            if(!is_array($payload)) throw new RuntimeException('Dados de cadastro inválidos.');

            $userId=self::createAccount($pdo,$payload,(string)$row['role']);
            $pdo->prepare("UPDATE tp_registration_requests SET status='verified',verified_at=NOW(),user_id=? WHERE id=?")
                ->execute([$userId,(int)$row['id']]);
            $pdo->commit();

            $token=Data::createApiToken($userId);
            $st=$pdo->prepare('SELECT id,name,email,role,phone,status,phone_verified_at FROM tp_users WHERE id=?');
            $st->execute([$userId]);
            return ['user'=>$st->fetch(),'token'=>$token];
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function createAccount(PDO $pdo,array $p,string $role): int
    {
        $st=$pdo->prepare('SELECT id FROM tp_users WHERE email=? LIMIT 1');
        $st->execute([$p['email']]);
        if($st->fetchColumn()) throw new RuntimeException('Já existe uma conta com este e-mail.');

        $st=$pdo->prepare('INSERT INTO tp_users (name,email,password_hash,role,phone,phone_verified_at,status,created_at) VALUES (?,?,?,?,?,NOW(),"active",NOW())');
        $st->execute([$p['name'],$p['email'],password_hash($p['password'],PASSWORD_DEFAULT),$role,$p['phone']]);
        $userId=(int)$pdo->lastInsertId();

        if($role==='professional'){
            $st=$pdo->prepare('INSERT INTO tp_professionals
                (user_id,cpf,rg,birth_date,headline,address,postal_code,city,state,pix_key,pix_key_type,pix_holder_name,pix_holder_document,status,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"pending",NOW())');
            $st->execute([
                $userId,$p['cpf'],$p['rg'],$p['birth_date'],$p['headline'],$p['address'],$p['postal_code'],$p['city'],$p['state'],
                $p['pix_key'],$p['pix_key_type'],$p['pix_holder_name'],$p['pix_holder_document']
            ]);
            $professionalId=(int)$pdo->lastInsertId();
            $cat=$pdo->prepare('INSERT IGNORE INTO tp_professional_categories (professional_id,category_id,experience_level) SELECT ?,id,"initial" FROM tp_job_categories WHERE id=? AND active=1');
            foreach($p['categories'] as $categoryId)$cat->execute([$professionalId,$categoryId]);
        }else{
            $st=$pdo->prepare('INSERT INTO tp_companies
                (legal_name,trade_name,cnpj,responsible_cpf,address,postal_code,city,state,pix_key_type,pix_key,pix_holder_name,pix_holder_document,status,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"pending",NOW())');
            $st->execute([
                $p['legal_name'],$p['trade_name'],$p['cnpj'],$p['responsible_cpf'],$p['address'],$p['postal_code'],$p['city'],$p['state'],
                $p['pix_key_type'],$p['pix_key'],$p['pix_holder_name'],$p['pix_holder_document']
            ]);
            $companyId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO tp_company_members (company_id,user_id,member_role,created_at) VALUES (?,?,"owner",NOW())')
                ->execute([$companyId,$userId]);
        }

        $consent=$pdo->prepare('INSERT INTO tp_consents (user_id,consent_type,version,accepted_at,ip_address,user_agent) VALUES (?,?,?,NOW(),?,?)');
        $terms=(string)(app_config('legal.terms_version') ?? '2026-10-02');
        $privacy=(string)(app_config('legal.privacy_version') ?? '2026-10-02');
        foreach([['terms',$terms],['privacy',$privacy],['whatsapp_transactional','1']] as [$type,$version]){
            $consent->execute([$userId,$type,$version,$_SERVER['REMOTE_ADDR']??null,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
        }

        return $userId;
    }

    private static function validatePayload(array $d,string $role): array
    {
        $name=trim((string)($d['name']??''));
        $email=mb_strtolower(trim((string)($d['email']??'')));
        $password=(string)($d['password']??'');
        $confirm=(string)($d['password_confirm']??'');
        $phone=self::normalizePhone((string)($d['phone']??''));
        $address=trim((string)($d['address']??''));
        $postal=preg_replace('/\D+/','',(string)($d['postal_code']??''));
        $city=trim((string)($d['city']??''));
        $state=mb_strtoupper(trim((string)($d['state']??'')));
        $pixType=trim((string)($d['pix_key_type']??''));
        $pixKey=trim((string)($d['pix_key']??''));
        $pixHolder=trim((string)($d['pix_holder_name']??''));
        $pixDoc=preg_replace('/\D+/','',(string)($d['pix_holder_document']??''));

        if(mb_strlen($name)<3) throw new InvalidArgumentException('Informe o nome completo do responsável.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
        if(strlen($password)<8) throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        if($password!==$confirm) throw new InvalidArgumentException('As senhas não coincidem.');
        if($address===''||$city===''||strlen($state)!==2||strlen($postal)!==8) throw new InvalidArgumentException('Informe endereço, CEP, cidade e UF.');
        if(!in_array($pixType,['cpf','cnpj','email','phone','random'],true)||$pixKey===''||$pixHolder===''||$pixDoc==='') throw new InvalidArgumentException('Preencha os dados completos da chave Pix.');
        if(strlen($pixDoc)===11 && !self::validCpf($pixDoc)) throw new InvalidArgumentException('CPF do titular do Pix inválido.');
        if(strlen($pixDoc)===14 && !self::validCnpj($pixDoc)) throw new InvalidArgumentException('CNPJ do titular do Pix inválido.');
        if(!in_array(strlen($pixDoc),[11,14],true)) throw new InvalidArgumentException('Documento do titular do Pix inválido.');
        if(!self::truthy($d['terms_accepted']??false)||!self::truthy($d['privacy_accepted']??false)) throw new InvalidArgumentException('É necessário aceitar os Termos de Uso e a Política de Privacidade.');
        if(!self::truthy($d['whatsapp_consent']??false)) throw new InvalidArgumentException('Autorize mensagens transacionais no WhatsApp para validar o telefone e receber contatos sobre vagas.');

        $base=[
            'name'=>$name,'email'=>$email,'password'=>$password,'phone'=>$phone,
            'address'=>$address,'postal_code'=>$postal,'city'=>$city,'state'=>$state,
            'pix_key_type'=>$pixType,'pix_key'=>$pixKey,'pix_holder_name'=>$pixHolder,'pix_holder_document'=>$pixDoc,
        ];

        if($role==='professional'){
            $cpf=preg_replace('/\D+/','',(string)($d['cpf']??''));
            if(!self::validCpf($cpf)) throw new InvalidArgumentException('CPF inválido.');
            $rg=mb_strtoupper(preg_replace('/[^0-9A-Za-z]+/','',(string)($d['rg']??'')));
            if(strlen($rg)<7||strlen($rg)>12) throw new InvalidArgumentException('Informe um RG válido.');
            $birth=trim((string)($d['birth_date']??''));
            $date=DateTime::createFromFormat('Y-m-d',$birth);
            if(!$date || $date->format('Y-m-d')!==$birth) throw new InvalidArgumentException('Informe uma data de nascimento válida.');
            $today=new DateTime('today');
            $age=$today->diff($date)->y;
            if($date>$today || $age<18) throw new InvalidArgumentException('O profissional deve ter pelo menos 18 anos.');
            $headline=trim((string)($d['headline']??''));
            if($headline==='') throw new InvalidArgumentException('Informe sua atividade principal.');
            $categories=array_values(array_unique(array_filter(array_map('intval',(array)($d['categories']??[])))));
            if(!$categories) throw new InvalidArgumentException('Selecione pelo menos uma função de interesse.');
            return $base+['cpf'=>$cpf,'rg'=>$rg,'birth_date'=>$birth,'headline'=>$headline,'categories'=>$categories];
        }

        $cnpj=preg_replace('/\D+/','',(string)($d['cnpj']??''));
        $responsibleCpf=preg_replace('/\D+/','',(string)($d['responsible_cpf']??''));
        $legal=trim((string)($d['legal_name']??''));
        $trade=trim((string)($d['trade_name']??''));
        if(!self::validCnpj($cnpj)) throw new InvalidArgumentException('CNPJ inválido.');
        if(!self::validCpf($responsibleCpf)) throw new InvalidArgumentException('CPF do responsável inválido.');
        if($legal===''||$trade==='') throw new InvalidArgumentException('Informe razão social e nome fantasia.');
        return $base+['cnpj'=>$cnpj,'responsible_cpf'=>$responsibleCpf,'legal_name'=>$legal,'trade_name'=>$trade];
    }

    private static function developmentWhatsappBypass(): bool
    {
        return (bool)app_config('debug') && (bool)app_config('registration.development_whatsapp_bypass');
    }

    private static function developmentCode(): string
    {
        $code=preg_replace('/\D+/','',(string)(app_config('registration.development_code') ?? '000111'));
        return strlen($code)===6?$code:'000111';
    }

    private static function normalizePhone(string $value): string
    {
        $digits=preg_replace('/\D+/','',$value);
        if(strlen($digits)===10||strlen($digits)===11) $digits='55'.$digits;
        if(!preg_match('/^55\d{10,11}$/',$digits)) throw new InvalidArgumentException('Informe um WhatsApp brasileiro válido com DDD.');
        return '+'.$digits;
    }

    private static function maskPhone(string $phone): string
    {
        $d=preg_replace('/\D+/','',$phone);
        if(strlen($d)<6) return $phone;
        return '+'.substr($d,0,4).'*****'.substr($d,-4);
    }

    private static function truthy(mixed $v): bool
    {
        return in_array($v,[true,1,'1','on','yes','true'],true);
    }

    private static function validCpf(string $cpf): bool
    {
        $cpf=preg_replace('/\D+/','',$cpf);
        if(strlen($cpf)!==11||preg_match('/^(\d)\1{10}$/',$cpf)) return false;
        for($t=9;$t<11;$t++){
            $sum=0;
            for($i=0;$i<$t;$i++) $sum+=(int)$cpf[$i]*(($t+1)-$i);
            $digit=((10*$sum)%11)%10;
            if((int)$cpf[$t]!==$digit) return false;
        }
        return true;
    }

    private static function validCnpj(string $cnpj): bool
    {
        $cnpj=preg_replace('/\D+/','',$cnpj);
        if(strlen($cnpj)!==14||preg_match('/^(\d)\1{13}$/',$cnpj)) return false;
        $calc=function(string $base,array $weights): int{
            $sum=0;
            foreach($weights as $i=>$w)$sum+=(int)$base[$i]*$w;
            $r=$sum%11;
            return $r<2?0:11-$r;
        };
        $d1=$calc($cnpj,[5,4,3,2,9,8,7,6,5,4,3,2]);
        if((int)$cnpj[12]!==$d1) return false;
        $d2=$calc($cnpj,[6,5,4,3,2,9,8,7,6,5,4,3,2]);
        return (int)$cnpj[13]===$d2;
    }
}
