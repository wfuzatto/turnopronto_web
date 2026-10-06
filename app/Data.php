<?php
final class Data
{
    private static array $columnCache = [];

    private static function hasColumn(string $table, string $column): bool
    {
        $key=$table.'.'.$column;
        if(array_key_exists($key,self::$columnCache)) return self::$columnCache[$key];
        $sql='SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?';
        $st=Database::connection()->prepare($sql);
        $st->execute([$table,$column]);
        return self::$columnCache[$key]=((int)$st->fetchColumn()>0);
    }

    private static function audit(int $userId, string $action, ?string $entityType=null, ?int $entityId=null, array $metadata=[]): void
    {
        try {
            $st=Database::connection()->prepare('INSERT INTO tp_audit_logs (user_id,action,entity_type,entity_id,metadata_json,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())');
            $st->execute([
                $userId,$action,$entityType,$entityId,
                $metadata ? json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                $_SERVER['REMOTE_ADDR']??null
            ]);
        } catch(Throwable $e) {
            // Auditoria não deve derrubar o fluxo principal.
        }
    }

    private static function ensureNotificationSchema(): void
    {
        static $ready=false;
        if($ready) return;
        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            action_url VARCHAR(500) NULL,
            dedupe_key VARCHAR(190) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
            INDEX idx_notification_user (user_id,read_at,created_at),
            UNIQUE KEY uq_notification_dedupe (user_id,dedupe_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if(!self::hasColumn('tp_notifications','action_url')){
            $pdo->exec('ALTER TABLE tp_notifications ADD COLUMN action_url VARCHAR(500) NULL AFTER body');
            self::$columnCache['tp_notifications.action_url']=true;
        }
        if(!self::hasColumn('tp_notifications','dedupe_key')){
            $pdo->exec('ALTER TABLE tp_notifications ADD COLUMN dedupe_key VARCHAR(190) NULL AFTER action_url');
            self::$columnCache['tp_notifications.dedupe_key']=true;
        }

        $idx=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tp_notifications' AND INDEX_NAME='uq_notification_dedupe'");
        $idx->execute();
        if(!(int)$idx->fetchColumn()){
            $pdo->exec('ALTER TABLE tp_notifications ADD UNIQUE KEY uq_notification_dedupe (user_id,dedupe_key)');
        }
        $ready=true;
    }

    private static function notificationActionPath(string $path): string
    {
        $path=ltrim(trim($path),'/');
        if($path==='' || str_contains($path,'://') || str_starts_with($path,'//')) return '';
        if(!preg_match('#^[a-zA-Z0-9/_?&=.%+-]+$#',$path)) return '';
        return mb_substr($path,0,500);
    }

    private static function createNotification(int $userId,string $type,string $title,string $body,string $actionPath='',?string $dedupeKey=null): void
    {
        if($userId<=0) return;
        self::ensureNotificationSchema();
        $action=self::notificationActionPath($actionPath);
        $key=$dedupeKey!==null?mb_substr(trim($dedupeKey),0,190):null;
        $st=Database::connection()->prepare('INSERT IGNORE INTO tp_notifications (user_id,type,title,body,action_url,dedupe_key,created_at) VALUES (?,?,?,?,?,?,NOW())');
        $st->execute([
            $userId,
            mb_substr(trim($type),0,50),
            mb_substr(trim($title),0,190),
            trim($body),
            $action!==''?$action:null,
            $key!==''?$key:null
        ]);
    }

    private static function professionalUserId(int $professionalId): ?int
    {
        $st=Database::connection()->prepare('SELECT user_id FROM tp_professionals WHERE id=? LIMIT 1');
        $st->execute([$professionalId]);
        $value=$st->fetchColumn();
        return $value?(int)$value:null;
    }

    private static function notifyProfessionalsForShift(int $shiftId): void
    {
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT s.id,s.title,s.starts_at,s.city,s.state,s.shift_value,s.category_id,
                                  jc.name category_name,c.trade_name company_name
                           FROM tp_shifts s
                           JOIN tp_job_categories jc ON jc.id=s.category_id
                           JOIN tp_companies c ON c.id=s.company_id
                           WHERE s.id=? AND s.status IN ('published','filling') LIMIT 1");
        $st->execute([$shiftId]);
        $shift=$st->fetch();
        if(!$shift) return;

        $targets=$pdo->prepare("SELECT DISTINCT u.id
                               FROM tp_professional_categories pc
                               JOIN tp_professionals p ON p.id=pc.professional_id
                               JOIN tp_users u ON u.id=p.user_id
                               WHERE pc.category_id=? AND p.status IN ('pending','verified') AND u.status='active'");
        $targets->execute([(int)$shift['category_id']]);
        $when=date('d/m/Y H:i',strtotime((string)$shift['starts_at']));
        $body='Nova vaga de '.$shift['category_name'].' em '.$shift['city'].' - '.$shift['state'].' para '.$when.', por '.money($shift['shift_value']).'. Empresa: '.$shift['company_name'].'.';
        foreach($targets->fetchAll(PDO::FETCH_COLUMN) as $targetUserId){
            self::createNotification(
                (int)$targetUserId,
                'matching_shift',
                'Nova vaga do seu interesse',
                $body,
                'profissional/vagas/'.$shiftId,
                'matching_shift:'.$shiftId
            );
        }
    }

    private static function notifyCompanyMembersOfApplication(int $shiftId,int $professionalId): void
    {
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT s.title,s.company_id,jc.name category_name,u.name professional_name
                           FROM tp_shifts s
                           JOIN tp_job_categories jc ON jc.id=s.category_id
                           JOIN tp_professionals p ON p.id=?
                           JOIN tp_users u ON u.id=p.user_id
                           WHERE s.id=? LIMIT 1");
        $st->execute([$professionalId,$shiftId]);
        $data=$st->fetch();
        if(!$data) return;

        $members=$pdo->prepare("SELECT DISTINCT u.id
                                FROM tp_company_members cm
                                JOIN tp_users u ON u.id=cm.user_id
                                WHERE cm.company_id=? AND u.status='active'");
        $members->execute([(int)$data['company_id']]);
        foreach($members->fetchAll(PDO::FETCH_COLUMN) as $memberUserId){
            self::createNotification(
                (int)$memberUserId,
                'application',
                'Nova candidatura recebida',
                $data['professional_name'].' se candidatou para '.$data['category_name'].' · '.$data['title'].'.',
                'empresa/vagas/'.$shiftId,
                'application:'.$shiftId.':'.$professionalId
            );
        }
    }

    public static function notificationMenu(int $userId,int $limit=6): array
    {
        self::ensureNotificationSchema();
        $pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*) FROM tp_notifications WHERE user_id=? AND read_at IS NULL');
        $count->execute([$userId]);
        $st=$pdo->prepare('SELECT id,type,title,body,action_url,read_at,created_at FROM tp_notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(20,$limit)));
        $st->execute([$userId]);
        return ['unread'=>(int)$count->fetchColumn(),'items'=>$st->fetchAll()];
    }

    public static function notifications(int $userId,int $limit=100): array
    {
        self::ensureNotificationSchema();
        $st=Database::connection()->prepare('SELECT id,type,title,body,action_url,read_at,created_at FROM tp_notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(200,$limit)));
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function openNotification(int $userId,int $notificationId): string
    {
        self::ensureNotificationSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT action_url FROM tp_notifications WHERE id=? AND user_id=? LIMIT 1');
        $st->execute([$notificationId,$userId]);
        $row=$st->fetch();
        if(!$row) throw new RuntimeException('Notificação não encontrada.');
        $pdo->prepare('UPDATE tp_notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?')->execute([$notificationId,$userId]);
        return self::notificationActionPath((string)($row['action_url']??''));
    }

    public static function markAllNotificationsRead(int $userId): void
    {
        self::ensureNotificationSchema();
        Database::connection()->prepare('UPDATE tp_notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?')->execute([$userId]);
    }

    public static function companyIdForUser(int $userId): ?int
    {
        $s=Database::connection()->prepare('SELECT company_id FROM tp_company_members WHERE user_id=? LIMIT 1');
        $s->execute([$userId]);
        $v=$s->fetchColumn();
        return $v ? (int)$v : null;
    }

    public static function professionalIdForUser(int $userId): ?int
    {
        $s=Database::connection()->prepare('SELECT id FROM tp_professionals WHERE user_id=? LIMIT 1');
        $s->execute([$userId]);
        $v=$s->fetchColumn();
        return $v ? (int)$v : null;
    }

    private static function ensureCompanyMapsColumn(): void
    {
        if(self::hasColumn('tp_companies','maps_url')) return;
        Database::connection()->exec('ALTER TABLE tp_companies ADD COLUMN maps_url VARCHAR(1000) NULL AFTER state');
        self::$columnCache['tp_companies.maps_url']=true;
    }

    private static function ensureCompanyEmailColumn(): void
    {
        if(self::hasColumn('tp_companies','company_email')) return;
        $pdo=Database::connection();
        $pdo->exec('ALTER TABLE tp_companies ADD COLUMN company_email VARCHAR(190) NULL AFTER responsible_cpf');
        $pdo->exec("UPDATE tp_companies c
                    JOIN tp_company_members cm ON cm.company_id=c.id
                    JOIN tp_users u ON u.id=cm.user_id
                    SET c.company_email=u.email
                    WHERE c.company_email IS NULL
                      AND u.email IS NOT NULL
                      AND u.email<>''");
        self::$columnCache['tp_companies.company_email']=true;
    }

    private static function normalizeCompanyMapsUrl(string $value): string
    {
        $value=trim($value);
        if($value==='') return '';
        if(mb_strlen($value)>1000) throw new InvalidArgumentException('O link do Google Maps é muito longo.');
        if(!filter_var($value,FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Informe um link válido do Google Maps.');
        $scheme=mb_strtolower((string)parse_url($value,PHP_URL_SCHEME));
        if(!in_array($scheme,['http','https'],true)) throw new InvalidArgumentException('O link do Google Maps precisa começar com http:// ou https://.');
        return $value;
    }

    public static function companyProfile(int $userId): array
    {
        self::ensureCompanyMapsColumn();
        self::ensureCompanyEmailColumn();
        $companyId=self::companyIdForUser($userId);
        $s=Database::connection()->prepare('SELECT * FROM tp_companies WHERE id=?');
        $s->execute([$companyId]);
        return $s->fetch() ?: [];
    }

    private static function ensureCompanyVerificationSchema(): void
    {
        static $ready=false;
        if($ready) return;
        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_company_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(80) NOT NULL,
            label VARCHAR(150) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            file_path VARCHAR(500) NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            rejection_reason VARCHAR(500) NULL,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_company_doc_company FOREIGN KEY (company_id) REFERENCES tp_companies(id) ON DELETE CASCADE,
            INDEX idx_company_doc_status (company_id,type,status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_company_phone_verifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            phone VARCHAR(30) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            last_sent_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            CONSTRAINT fk_company_phone_verification_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
            INDEX idx_company_phone_verification (user_id,status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ready=true;
    }

    private static function companyVerificationDocumentTypes(): array
    {
        return [
            'cnpj'=>'Cartão do CNPJ',
            'responsible_identity'=>'Documento do responsável',
            'address'=>'Comprovante de endereço',
        ];
    }

    private static function normalizeCompanyVerificationPhone(string $value): string
    {
        $digits=preg_replace('/\\D+/','',$value);
        if(strlen($digits)===10||strlen($digits)===11) $digits='55'.$digits;
        if(!preg_match('/^55\\d{10,11}$/',$digits)) throw new InvalidArgumentException('Informe um WhatsApp brasileiro válido com DDD.');
        return '+'.$digits;
    }

    private static function maskCompanyVerificationPhone(string $phone): string
    {
        $digits=preg_replace('/\\D+/','',$phone);
        if(strlen($digits)<7) return $phone;
        return '+'.substr($digits,0,4).'*****'.substr($digits,-4);
    }

    public static function companyVerificationDocuments(int $userId): array
    {
        self::ensureCompanyVerificationSchema();
        $companyId=self::companyIdForUser($userId);
        if(!$companyId) throw new RuntimeException('Empresa não encontrada para este usuário.');
        $st=Database::connection()->prepare('SELECT * FROM tp_company_documents WHERE company_id=? ORDER BY created_at DESC,id DESC');
        $st->execute([$companyId]);
        $latest=[];
        foreach($st->fetchAll() as $doc){
            $type=(string)$doc['type'];
            if(!isset($latest[$type])) $latest[$type]=$doc;
        }
        return $latest;
    }

    public static function companyVerificationSummary(int $userId): array
    {
        self::ensureCompanyVerificationSchema();
        $pdo=Database::connection();
        $company=self::companyProfile($userId);
        if(!$company) throw new RuntimeException('Empresa não encontrada para este usuário.');

        $st=$pdo->prepare('SELECT id,name,email,phone,phone_verified_at,status FROM tp_users WHERE id=? LIMIT 1');
        $st->execute([$userId]);
        $account=$st->fetch();
        if(!$account) throw new RuntimeException('Conta não encontrada.');

        $requiredData=[
            'name'=>['source'=>'user','label'=>'Nome do responsável'],
            'email'=>['source'=>'user','label'=>'E-mail'],
            'phone'=>['source'=>'user','label'=>'WhatsApp'],
            'legal_name'=>['source'=>'company','label'=>'Razão social'],
            'trade_name'=>['source'=>'company','label'=>'Nome fantasia'],
            'cnpj'=>['source'=>'company','label'=>'CNPJ'],
            'responsible_cpf'=>['source'=>'company','label'=>'CPF do responsável'],
            'company_email'=>['source'=>'company','label'=>'E-mail da empresa'],
            'address'=>['source'=>'company','label'=>'Endereço'],
            'postal_code'=>['source'=>'company','label'=>'CEP'],
            'city'=>['source'=>'company','label'=>'Cidade'],
            'state'=>['source'=>'company','label'=>'UF'],
            'pix_key_type'=>['source'=>'company','label'=>'Tipo da chave Pix'],
            'pix_key'=>['source'=>'company','label'=>'Chave Pix'],
            'pix_holder_name'=>['source'=>'company','label'=>'Titular da chave Pix'],
            'pix_holder_document'=>['source'=>'company','label'=>'CPF/CNPJ do titular Pix'],
        ];
        $missing=[];
        foreach($requiredData as $field=>$meta){
            $source=$meta['source']==='user'?$account:$company;
            if(trim((string)($source[$field]??''))==='') $missing[]=$meta['label'];
        }

        $documents=self::companyVerificationDocuments($userId);
        $requiredDocuments=self::companyVerificationDocumentTypes();
        $verifiedDocuments=0;
        foreach($requiredDocuments as $type=>$label){
            if(($documents[$type]['status']??'')==='verified') $verifiedDocuments++;
        }

        $phoneVerified=!empty($account['phone_verified_at']);
        $dataComplete=!$missing;
        $documentsVerified=$verifiedDocuments===count($requiredDocuments);
        $companyVerified=($company['status']??'pending')==='verified';
        $readyForFinal=$phoneVerified&&$dataComplete&&$documentsVerified;

        $challenge=null;
        if(!$phoneVerified){
            $st=$pdo->prepare("SELECT id,phone,expires_at,attempts,last_sent_at,status FROM tp_company_phone_verifications WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
            $st->execute([$userId]);
            $challenge=$st->fetch() ?: null;
            if($challenge) $challenge['expired']=strtotime((string)$challenge['expires_at'])<time();
            if($challenge && (bool)app_config('debug') && (bool)app_config('registration.development_whatsapp_bypass')){
                $challenge['development_code']=preg_replace('/\\D+/','',(string)(app_config('registration.development_code')??'000111')) ?: '000111';
            }
        }

        $total=6;
        $completed=$companyVerified?$total:(($phoneVerified?1:0)+($dataComplete?1:0)+$verifiedDocuments);
        $progress=$companyVerified?100:(int)round(($completed/$total)*100);

        return [
            'account'=>$account,
            'company'=>$company,
            'phone_verified'=>$phoneVerified,
            'phone_masked'=>self::maskCompanyVerificationPhone((string)($account['phone']??'')),
            'phone_challenge'=>$challenge,
            'data_complete'=>$dataComplete,
            'missing_data'=>$missing,
            'required_documents'=>$requiredDocuments,
            'documents'=>$documents,
            'verified_documents'=>$verifiedDocuments,
            'documents_verified'=>$documentsVerified,
            'ready_for_final'=>$readyForFinal,
            'company_verified'=>$companyVerified,
            'progress'=>$progress,
            'completed_steps'=>$completed,
            'total_steps'=>$total,
        ];
    }

    public static function requestCompanyPhoneVerification(int $userId): array
    {
        self::ensureCompanyVerificationSchema();
        $pdo=Database::connection();
        $companyId=self::companyIdForUser($userId);
        if(!$companyId) throw new RuntimeException('Empresa não encontrada para este usuário.');

        $st=$pdo->prepare('SELECT phone,phone_verified_at FROM tp_users WHERE id=? LIMIT 1');
        $st->execute([$userId]);
        $user=$st->fetch();
        if(!$user) throw new RuntimeException('Conta não encontrada.');
        if(!empty($user['phone_verified_at'])) return ['already_verified'=>true];

        $phone=self::normalizeCompanyVerificationPhone((string)($user['phone']??''));
        $last=$pdo->prepare('SELECT last_sent_at FROM tp_company_phone_verifications WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $last->execute([$userId]);
        $lastSent=$last->fetchColumn();
        if($lastSent && time()-strtotime((string)$lastSent)<60) throw new RuntimeException('Aguarde 60 segundos antes de reenviar o código.');

        $count=$pdo->prepare('SELECT COUNT(*) FROM tp_company_phone_verifications WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)');
        $count->execute([$userId]);
        if((int)$count->fetchColumn()>=10) throw new RuntimeException('Limite de códigos atingido nas últimas 24 horas. Tente novamente mais tarde.');

        $developmentBypass=(bool)app_config('debug') && (bool)app_config('registration.development_whatsapp_bypass');
        $developmentCode=preg_replace('/\\D+/','',(string)(app_config('registration.development_code')??'000111'));
        if(strlen($developmentCode)!==6) $developmentCode='000111';
        $code=$developmentBypass?$developmentCode:(string)random_int(100000,999999);

        if(!$developmentBypass) WhatsApp::sendVerificationCode($phone,$code);
        $pdo->prepare("UPDATE tp_company_phone_verifications SET status='superseded' WHERE user_id=? AND status='pending'")->execute([$userId]);
        $expires=date('Y-m-d H:i:s',time()+600);
        $st=$pdo->prepare("INSERT INTO tp_company_phone_verifications (user_id,phone,code_hash,expires_at,attempts,status,last_sent_at,created_at) VALUES (?,?,?,?,0,'pending',NOW(),NOW())");
        $st->execute([$userId,$phone,password_hash($code,PASSWORD_DEFAULT),$expires]);
        self::audit($userId,'verification.company_phone_code_sent','company',$companyId);

        $result=['already_verified'=>false,'phone_masked'=>self::maskCompanyVerificationPhone($phone),'expires_in'=>600,'development_bypass'=>$developmentBypass];
        if($developmentBypass) $result['development_code']=$developmentCode;
        return $result;
    }

    public static function confirmCompanyPhoneVerification(int $userId,string $code): void
    {
        self::ensureCompanyVerificationSchema();
        $code=preg_replace('/\\D+/','',$code);
        if(strlen($code)!==6) throw new InvalidArgumentException('Informe o código de 6 dígitos.');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare("SELECT * FROM tp_company_phone_verifications WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $st->execute([$userId]);
            $challenge=$st->fetch();
            if(!$challenge) throw new RuntimeException('Envie um código antes de confirmar o WhatsApp.');
            if(strtotime((string)$challenge['expires_at'])<time()){
                $pdo->prepare("UPDATE tp_company_phone_verifications SET status='expired' WHERE id=?")->execute([(int)$challenge['id']]);
                throw new RuntimeException('Código expirado. Solicite um novo código.');
            }
            if((int)$challenge['attempts']>=5) throw new RuntimeException('Limite de tentativas atingido. Solicite um novo código.');
            if(!password_verify($code,(string)$challenge['code_hash'])){
                $pdo->prepare('UPDATE tp_company_phone_verifications SET attempts=attempts+1 WHERE id=?')->execute([(int)$challenge['id']]);
                $pdo->commit();
                throw new RuntimeException('Código inválido.');
            }

            $current=$pdo->prepare('SELECT phone FROM tp_users WHERE id=? LIMIT 1');
            $current->execute([$userId]);
            $phone=self::normalizeCompanyVerificationPhone((string)$current->fetchColumn());
            if($phone!==self::normalizeCompanyVerificationPhone((string)$challenge['phone'])) throw new RuntimeException('O WhatsApp da conta foi alterado. Solicite um novo código.');

            $pdo->prepare('UPDATE tp_users SET phone_verified_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$userId]);
            $pdo->prepare("UPDATE tp_company_phone_verifications SET status='verified',verified_at=NOW() WHERE id=?")->execute([(int)$challenge['id']]);
            $companyId=self::companyIdForUser($userId);
            self::audit($userId,'verification.company_phone_verified','company',$companyId);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function uploadCompanyVerificationDocument(int $userId,string $type,array $file): int
    {
        self::ensureCompanyVerificationSchema();
        $companyId=self::companyIdForUser($userId);
        if(!$companyId) throw new RuntimeException('Empresa não encontrada para este usuário.');
        $labels=self::companyVerificationDocumentTypes();
        if(!isset($labels[$type])) throw new InvalidArgumentException('Tipo de documento inválido.');

        $documents=self::companyVerificationDocuments($userId);
        $current=$documents[$type]??null;
        if(($current['status']??'')==='verified') throw new RuntimeException('Este documento já foi verificado.');
        if(($current['status']??'')==='pending') throw new RuntimeException('Este documento já está em análise.');

        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Selecione um arquivo válido.');
        if((int)($file['size']??0)<=0 || (int)$file['size']>5*1024*1024) throw new RuntimeException('O arquivo deve ter no máximo 5 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp)) throw new RuntimeException('Upload não reconhecido pelo servidor.');

        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp) ?: '';
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Envie PDF, JPG ou PNG.');

        $root=dirname(__DIR__);
        $dir=$root.'/storage/uploads/company_documents';
        if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta privada de documentos.');
        $filename=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        $absolute=$dir.'/'.$filename;
        if(!move_uploaded_file($tmp,$absolute)) throw new RuntimeException('Falha ao salvar o documento.');
        @chmod($absolute,0660);
        $relative='storage/uploads/company_documents/'.$filename;

        $st=Database::connection()->prepare('INSERT INTO tp_company_documents (company_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,?,?,"pending",?,?,?,NOW())');
        $st->execute([$companyId,$type,$labels[$type],$relative,mb_substr((string)($file['name']??'documento'),0,255),$mime]);
        $id=(int)Database::connection()->lastInsertId();
        self::audit($userId,'verification.company_document_uploaded','company_document',$id,['type'=>$type,'mime'=>$mime]);
        return $id;
    }

    public static function adminUploadCompanyVerificationDocument(int $adminUserId,int $companyId,string $type,array $file): int
    {
        self::ensureCompanyVerificationSchema();
        $labels=self::companyVerificationDocumentTypes();
        if(!isset($labels[$type])) throw new InvalidArgumentException('Tipo de documento inválido.');

        $pdo=Database::connection();
        $company=$pdo->prepare('SELECT id,trade_name FROM tp_companies WHERE id=? LIMIT 1');
        $company->execute([$companyId]);
        if(!$company->fetch()) throw new RuntimeException('Empresa não encontrada.');

        $current=$pdo->prepare('SELECT status FROM tp_company_documents WHERE company_id=? AND type=? ORDER BY created_at DESC,id DESC LIMIT 1');
        $current->execute([$companyId,$type]);
        $currentStatus=(string)($current->fetchColumn()?:'');
        if($currentStatus==='verified') throw new RuntimeException('Este documento já foi verificado.');
        if($currentStatus==='pending') throw new RuntimeException('Já existe um documento aguardando análise para este item.');

        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Selecione um arquivo válido.');
        if((int)($file['size']??0)<=0 || (int)$file['size']>5*1024*1024) throw new RuntimeException('O arquivo deve ter no máximo 5 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp)) throw new RuntimeException('Upload não reconhecido pelo servidor.');

        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp) ?: '';
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Envie PDF, JPG ou PNG.');

        $dir=dirname(__DIR__).'/storage/uploads/company_documents';
        if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta privada de documentos.');
        $filename=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        $absolute=$dir.'/'.$filename;
        if(!move_uploaded_file($tmp,$absolute)) throw new RuntimeException('Falha ao salvar o documento.');
        @chmod($absolute,0660);
        $relative='storage/uploads/company_documents/'.$filename;

        $st=$pdo->prepare('INSERT INTO tp_company_documents (company_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,?,?,"pending",?,?,?,NOW())');
        $st->execute([$companyId,$type,$labels[$type],$relative,mb_substr((string)($file['name']??'documento'),0,255),$mime]);
        $id=(int)$pdo->lastInsertId();
        self::audit($adminUserId,'verification.company_document_uploaded_by_admin','company_document',$id,[
            'company_id'=>$companyId,'type'=>$type,'mime'=>$mime
        ]);
        return $id;
    }

    public static function adminPendingCompanyDocuments(): array
    {
        self::ensureCompanyVerificationSchema();
        $sql="SELECT d.*,c.trade_name,c.cnpj,u.name owner_name,u.email owner_email
              FROM tp_company_documents d
              JOIN tp_companies c ON c.id=d.company_id
              LEFT JOIN tp_company_members cm ON cm.company_id=c.id AND cm.member_role='owner'
              LEFT JOIN tp_users u ON u.id=cm.user_id
              WHERE d.status='pending'
              ORDER BY d.created_at ASC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function adminCompanyDocument(int $documentId): ?array
    {
        self::ensureCompanyVerificationSchema();
        $st=Database::connection()->prepare('SELECT d.*,c.trade_name FROM tp_company_documents d JOIN tp_companies c ON c.id=d.company_id WHERE d.id=? LIMIT 1');
        $st->execute([$documentId]);
        return $st->fetch() ?: null;
    }

    public static function setCompanyDocumentVerification(int $adminUserId,int $documentId,string $decision,string $reason=''): void
    {
        self::ensureCompanyVerificationSchema();
        if(!in_array($decision,['verified','rejected'],true)) throw new InvalidArgumentException('Decisão inválida.');
        $reason=mb_substr(trim($reason),0,500);
        if($decision==='rejected' && $reason==='') $reason='Documento recusado durante a revisão.';
        $st=Database::connection()->prepare('UPDATE tp_company_documents SET status=?,verified_at=?,rejection_reason=? WHERE id=? AND status="pending"');
        $st->execute([$decision,$decision==='verified'?date('Y-m-d H:i:s'):null,$decision==='rejected'?$reason:null,$documentId]);
        if($st->rowCount()===0) throw new RuntimeException('Documento empresarial pendente não encontrado.');
        self::audit($adminUserId,'verification.company_document_reviewed','company_document',$documentId,['decision'=>$decision]);
    }

    public static function companyDashboard(int $userId): array
    {
        $pdo=Database::connection();
        $companyId=self::companyIdForUser($userId);
        $company=self::companyProfile($userId);

        $s=$pdo->prepare("SELECT COUNT(*) FROM tp_shifts WHERE company_id=? AND status IN ('published','filling','confirmed') AND starts_at >= NOW()");
        $s->execute([$companyId]);
        $open=(int)$s->fetchColumn();

        $s=$pdo->prepare('SELECT COALESCE(SUM(required_workers),0) FROM tp_shifts WHERE company_id=? AND DATE(starts_at)=CURDATE()');
        $s->execute([$companyId]);
        $today=(int)$s->fetchColumn();

        $s=$pdo->prepare("SELECT COALESCE(SUM(required_workers),0) needed,
            COALESCE(SUM((SELECT COUNT(*) FROM tp_assignments a WHERE a.shift_id=s.id AND a.status IN ('confirmed','checked_in','completed'))),0) filled
            FROM tp_shifts s WHERE company_id=? AND starts_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $s->execute([$companyId]);
        $fill=$s->fetch() ?: ['needed'=>0,'filled'=>0];
        $fillRate=(int)$fill['needed']>0 ? min(100,(int)round(((int)$fill['filled']/(int)$fill['needed'])*100)) : 0;

        $s=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_ledger WHERE company_id=? AND direction='debit' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $s->execute([$companyId]);
        $spend=(float)$s->fetchColumn();

        return [
            'company'=>$company,
            'kpis'=>['open'=>$open,'today'=>$today,'fill_rate'=>$fillRate,'spend'=>$spend],
            'shifts'=>self::companyShifts($userId,6),
            'professionals'=>self::suggestedProfessionals(3),
        ];
    }

    public static function companyShifts(int $userId, int $limit=50): array
    {
        $companyId=self::companyIdForUser($userId);
        $sql="SELECT s.*, c.name category_name,
                (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id AND a.status IN ('applied','invited')) candidates,
                (SELECT COUNT(*) FROM tp_assignments x WHERE x.shift_id=s.id AND x.status <> 'cancelled') assigned
            FROM tp_shifts s
            JOIN tp_job_categories c ON c.id=s.category_id
            WHERE s.company_id=?
            ORDER BY CASE WHEN s.starts_at>=NOW() THEN 0 ELSE 1 END, s.starts_at ASC
            LIMIT ".max(1,(int)$limit);
        $st=Database::connection()->prepare($sql);
        $st->execute([$companyId]);
        return $st->fetchAll();
    }

    public static function companyOpenShifts(int $userId): array
    {
        $companyId=self::companyIdForUser($userId);
        $extra=self::hasColumn('tp_shifts','acceptance_mode') ? ',s.acceptance_mode' : '';
        $sql="SELECT s.id,s.title,s.starts_at,s.required_workers,c.name category_name $extra
              FROM tp_shifts s JOIN tp_job_categories c ON c.id=s.category_id
              WHERE s.company_id=? AND s.status IN ('published','filling') AND s.starts_at>NOW()
              ORDER BY s.starts_at ASC";
        $st=Database::connection()->prepare($sql);
        $st->execute([$companyId]);
        return $st->fetchAll();
    }

    public static function companyShift(int $userId, int $shiftId): ?array
    {
        $companyId=self::companyIdForUser($userId);
        $sql="SELECT s.*,c.name category_name,
              (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id AND a.status IN ('applied','invited')) candidates,
              (SELECT COUNT(*) FROM tp_assignments x WHERE x.shift_id=s.id AND x.status <> 'cancelled') assigned
              FROM tp_shifts s JOIN tp_job_categories c ON c.id=s.category_id
              WHERE s.id=? AND s.company_id=? LIMIT 1";
        $st=Database::connection()->prepare($sql);
        $st->execute([$shiftId,$companyId]);
        $row=$st->fetch() ?: null;
        if($row && !array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        return $row;
    }
    public static function companyShiftCandidates(int $userId, int $shiftId): array
    {
        if(!self::companyShift($userId,$shiftId)) return [];
        $sql="SELECT a.*,p.id professional_id,p.status professional_status,p.headline,p.reliability_score,p.punctuality_score,p.attendance_score,p.rating,p.completed_shifts,
                     u.name,u.avatar_url,x.id assignment_id,x.status assignment_status
              FROM tp_shift_applications a
              JOIN tp_professionals p ON p.id=a.professional_id
              JOIN tp_users u ON u.id=p.user_id
              LEFT JOIN tp_assignments x ON x.shift_id=a.shift_id AND x.professional_id=a.professional_id
              WHERE a.shift_id=?
              ORDER BY FIELD(a.status,'applied','invited','accepted','rejected'),a.applied_at DESC";
        $st=Database::connection()->prepare($sql);
        $st->execute([$shiftId]);
        return $st->fetchAll();
    }

    public static function companyShiftAssignments(int $userId, int $shiftId): array
    {
        if(!self::companyShift($userId,$shiftId)) return [];
        $sql="SELECT a.*,p.reliability_score,p.punctuality_score,p.attendance_score,p.rating,p.completed_shifts,p.headline,u.name,u.avatar_url
              FROM tp_assignments a
              JOIN tp_professionals p ON p.id=a.professional_id
              JOIN tp_users u ON u.id=p.user_id
              WHERE a.shift_id=? AND a.status<>'cancelled'
              ORDER BY a.confirmed_at ASC";
        $st=Database::connection()->prepare($sql);
        $st->execute([$shiftId]);
        return $st->fetchAll();
    }

    public static function categories(): array
    {
        return Database::connection()->query('SELECT * FROM tp_job_categories WHERE active=1 ORDER BY name')->fetchAll();
    }

    public static function createJobCategory(int $userId,string $name): array
    {
        $company=self::companyProfile($userId);
        if(!$company) throw new RuntimeException('Empresa não encontrada para este usuário.');
        if(($company['status']??'pending')!=='verified') throw new RuntimeException('A empresa precisa ser verificada antes de cadastrar categorias.');

        $name=trim((string)(preg_replace('/\\s+/u',' ',trim($name)) ?? trim($name)));
        $length=mb_strlen($name);
        if($length<2) throw new InvalidArgumentException('Informe um nome de categoria com pelo menos 2 caracteres.');
        if($length>120) throw new InvalidArgumentException('O nome da categoria pode ter no máximo 120 caracteres.');

        $pdo=Database::connection();
        $findByName=$pdo->prepare('SELECT id,name,slug,active FROM tp_job_categories WHERE name=? LIMIT 1');
        $findByName->execute([$name]);
        $existing=$findByName->fetch();

        if($existing){
            if(!(int)$existing['active']){
                $pdo->prepare('UPDATE tp_job_categories SET active=1 WHERE id=?')->execute([(int)$existing['id']]);
                self::audit($userId,'category.reactivated','job_category',(int)$existing['id'],['name'=>$existing['name']]);
            }
            return [
                'id'=>(int)$existing['id'],
                'name'=>(string)$existing['name'],
                'slug'=>(string)$existing['slug'],
                'active'=>1,
                'created'=>false,
            ];
        }

        $base=self::jobCategorySlug($name);
        $slug=$base;
        $slugExists=$pdo->prepare('SELECT id FROM tp_job_categories WHERE slug=? LIMIT 1');
        for($suffix=2;;$suffix++){
            $slugExists->execute([$slug]);
            if(!$slugExists->fetchColumn()) break;
            $tail='-'.$suffix;
            $slug=mb_substr($base,0,max(1,120-mb_strlen($tail))).$tail;
        }

        try{
            $st=$pdo->prepare('INSERT INTO tp_job_categories (name,slug,active) VALUES (?,?,1)');
            $st->execute([$name,$slug]);
            $id=(int)$pdo->lastInsertId();
        }catch(PDOException $e){
            if((string)$e->getCode()==='23000'){
                $findByName->execute([$name]);
                $existing=$findByName->fetch();
                if($existing){
                    return [
                        'id'=>(int)$existing['id'],
                        'name'=>(string)$existing['name'],
                        'slug'=>(string)$existing['slug'],
                        'active'=>(int)$existing['active'],
                        'created'=>false,
                    ];
                }
            }
            throw $e;
        }

        self::audit($userId,'category.created','job_category',$id,['name'=>$name,'slug'=>$slug]);
        return ['id'=>$id,'name'=>$name,'slug'=>$slug,'active'=>1,'created'=>true];
    }

    private static function jobCategorySlug(string $name): string
    {
        $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
        $source=is_string($ascii)&&$ascii!==''?$ascii:$name;
        $source=strtolower($source);
        $slug=preg_replace('/[^a-z0-9]+/','-',$source) ?? '';
        $slug=trim($slug,'-');
        if($slug==='') $slug='categoria';
        return mb_substr($slug,0,110);
    }

    private static function normalizedShiftInput(array $data): array
    {
        $required=['category_id','title','date','start_time','end_time','value','required_workers','address','city','state'];
        foreach($required as $field){
            if(!isset($data[$field]) || trim((string)$data[$field])==='') throw new InvalidArgumentException('Preencha todos os campos obrigatórios.');
        }

        $date=trim((string)$data['date']);
        $start=trim((string)$data['start_time']);
        $end=trim((string)$data['end_time']);
        $starts=$date.' '.$start.':00';
        $endDate=$date;
        if($end<=$start) $endDate=date('Y-m-d',strtotime($date.' +1 day'));
        $ends=$endDate.' '.$end.':00';

        if(strtotime($ends)<=strtotime($starts)) throw new InvalidArgumentException('O horário final precisa ser posterior ao início.');
        if((float)$data['value']<=0) throw new InvalidArgumentException('Informe um valor válido para o turno.');

        $mode=in_array(($data['acceptance_mode']??'automatic'),['automatic','manual'],true)
            ? (string)$data['acceptance_mode']
            : 'automatic';

        return [
            'category_id'=>(int)$data['category_id'],
            'title'=>trim((string)$data['title']),
            'description'=>trim((string)($data['description']??'')),
            'starts_at'=>$starts,
            'ends_at'=>$ends,
            'shift_value'=>(float)$data['value'],
            'required_workers'=>max(1,(int)$data['required_workers']),
            'address'=>trim((string)$data['address']),
            'city'=>trim((string)$data['city']),
            'state'=>mb_strtoupper(trim((string)$data['state'])),
            'dress_code'=>trim((string)($data['dress_code']??'')),
            'notes'=>trim((string)($data['notes']??'')),
            'acceptance_mode'=>$mode
        ];
    }

    public static function createShift(int $userId, array $data): int
    {
        $companyId=self::companyIdForUser($userId);
        if(!$companyId) throw new RuntimeException('Empresa não encontrada para este usuário.');
        $company=self::companyProfile($userId);
        if(($company['status']??'pending')!=='verified') throw new RuntimeException('A empresa precisa ser verificada antes de publicar vagas.');
        $d=self::normalizedShiftInput($data);
        $pdo=Database::connection();

        if(self::hasColumn('tp_shifts','acceptance_mode')){
            $sql='INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,dress_code,notes,checkin_pin,acceptance_mode,status,created_at)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"published",NOW())';
            $params=[$companyId,$d['category_id'],$d['title'],$d['description'],$d['starts_at'],$d['ends_at'],$d['shift_value'],$d['required_workers'],$d['address'],$d['city'],$d['state'],$d['dress_code'],$d['notes'],sprintf('%06d',random_int(0,999999)),$d['acceptance_mode']];
        } else {
            $sql='INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,dress_code,notes,checkin_pin,status,created_at)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,"published",NOW())';
            $params=[$companyId,$d['category_id'],$d['title'],$d['description'],$d['starts_at'],$d['ends_at'],$d['shift_value'],$d['required_workers'],$d['address'],$d['city'],$d['state'],$d['dress_code'],$d['notes'],sprintf('%06d',random_int(0,999999))];
        }

        $st=$pdo->prepare($sql);
        $st->execute($params);
        $id=(int)$pdo->lastInsertId();
        self::audit($userId,'shift.created','shift',$id,['title'=>$d['title'],'acceptance_mode'=>$d['acceptance_mode']]);
        self::notifyProfessionalsForShift($id);
        return $id;
    }

    public static function updateShift(int $userId, int $shiftId, array $data): void
    {
        $current=self::companyShift($userId,$shiftId);
        if(!$current) throw new RuntimeException('Vaga não encontrada.');
        if(in_array($current['status'],['cancelled','completed'],true)) throw new RuntimeException('Esta vaga não pode mais ser editada.');
        if(strtotime($current['starts_at'])<=time()) throw new RuntimeException('Uma vaga que já iniciou não pode ser editada.');

        $d=self::normalizedShiftInput($data);
        $pdo=Database::connection();

        if(self::hasColumn('tp_shifts','acceptance_mode')){
            $sql='UPDATE tp_shifts SET category_id=?,title=?,description=?,starts_at=?,ends_at=?,shift_value=?,required_workers=?,address=?,city=?,state=?,dress_code=?,notes=?,acceptance_mode=?,updated_at=NOW() WHERE id=? AND company_id=?';
            $params=[$d['category_id'],$d['title'],$d['description'],$d['starts_at'],$d['ends_at'],$d['shift_value'],$d['required_workers'],$d['address'],$d['city'],$d['state'],$d['dress_code'],$d['notes'],$d['acceptance_mode'],$shiftId,$current['company_id']];
        } else {
            $sql='UPDATE tp_shifts SET category_id=?,title=?,description=?,starts_at=?,ends_at=?,shift_value=?,required_workers=?,address=?,city=?,state=?,dress_code=?,notes=?,updated_at=NOW() WHERE id=? AND company_id=?';
            $params=[$d['category_id'],$d['title'],$d['description'],$d['starts_at'],$d['ends_at'],$d['shift_value'],$d['required_workers'],$d['address'],$d['city'],$d['state'],$d['dress_code'],$d['notes'],$shiftId,$current['company_id']];
        }

        $pdo->prepare($sql)->execute($params);
        self::audit($userId,'shift.updated','shift',$shiftId,['title'=>$d['title']]);
        if((int)$current['category_id']!==(int)$d['category_id']) self::notifyProfessionalsForShift($shiftId);
    }

    public static function cancelShift(int $userId, int $shiftId, string $reason=''): void
    {
        $shift=self::companyShift($userId,$shiftId);
        if(!$shift) throw new RuntimeException('Vaga não encontrada.');
        if($shift['status']==='cancelled') return;
        if(strtotime($shift['starts_at'])<=time()) throw new RuntimeException('Vagas já iniciadas exigem tratamento operacional pelo suporte.');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE tp_shifts SET status="cancelled",updated_at=NOW() WHERE id=? AND company_id=?')->execute([$shiftId,$shift['company_id']]);
            $pdo->prepare('UPDATE tp_assignments SET status="cancelled",cancellation_reason=? WHERE shift_id=? AND status IN ("confirmed","checked_in")')->execute([$reason?:'Vaga cancelada pela empresa',$shiftId]);
            $pdo->prepare('UPDATE tp_shift_applications SET status="rejected" WHERE shift_id=? AND status IN ("applied","invited")')->execute([$shiftId]);
            self::audit($userId,'shift.cancelled','shift',$shiftId,['reason'=>$reason]);
            $pdo->commit();
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function approveApplication(int $userId, int $shiftId, int $applicationId): int
    {
        $companyId=self::companyIdForUser($userId);
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT * FROM tp_shifts WHERE id=? AND company_id=? FOR UPDATE');
            $st->execute([$shiftId,$companyId]);
            $shift=$st->fetch();
            if(!$shift) throw new RuntimeException('Vaga não encontrada.');
            if(!in_array($shift['status'],['published','filling'],true)) throw new RuntimeException('Esta vaga não aceita novas aprovações.');

            $st=$pdo->prepare('SELECT * FROM tp_shift_applications WHERE id=? AND shift_id=? FOR UPDATE');
            $st->execute([$applicationId,$shiftId]);
            $application=$st->fetch();
            if(!$application) throw new RuntimeException('Candidatura não encontrada.');

            $verification=$pdo->prepare('SELECT status FROM tp_professionals WHERE id=? LIMIT 1');
            $verification->execute([(int)$application['professional_id']]);
            if((string)$verification->fetchColumn()!=='verified'){
                throw new RuntimeException('Este profissional ainda está em verificação. Aguarde a liberação do cadastro antes de confirmá-lo no turno.');
            }

            $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status<>'cancelled'");
            $st->execute([$shiftId]);
            if((int)$st->fetchColumn()>=(int)$shift['required_workers']) throw new RuntimeException('Todas as vagas deste turno já foram preenchidas.');

            $pdo->prepare('INSERT INTO tp_assignments (shift_id,professional_id,status,agreed_value,confirmed_at) VALUES (?,?,"confirmed",?,NOW())
                           ON DUPLICATE KEY UPDATE status="confirmed",agreed_value=VALUES(agreed_value),confirmed_at=NOW(),cancellation_reason=NULL')
                ->execute([$shiftId,$application['professional_id'],$shift['shift_value']]);
            $assignmentId=(int)$pdo->lastInsertId();
            if(!$assignmentId){
                $q=$pdo->prepare('SELECT id FROM tp_assignments WHERE shift_id=? AND professional_id=?');
                $q->execute([$shiftId,$application['professional_id']]);
                $assignmentId=(int)$q->fetchColumn();
            }

            $pdo->prepare('UPDATE tp_shift_applications SET status="accepted" WHERE id=?')->execute([$applicationId]);

            $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status<>'cancelled'");
            $st->execute([$shiftId]);
            $count=(int)$st->fetchColumn();
            $pdo->prepare('UPDATE tp_shifts SET status=? WHERE id=?')->execute([$count>=(int)$shift['required_workers']?'confirmed':'filling',$shiftId]);

            self::audit($userId,'application.approved','shift_application',$applicationId,['shift_id'=>$shiftId,'assignment_id'=>$assignmentId]);
            $pdo->commit();

            $professionalUserId=self::professionalUserId((int)$application['professional_id']);
            if($professionalUserId){
                self::createNotification(
                    $professionalUserId,
                    'application_approved',
                    'Sua candidatura foi aprovada',
                    'Você foi confirmado para '.$shift['title'].'. Consulte os detalhes do turno.',
                    'profissional/turno/'.$assignmentId,
                    'application_approved:'.$applicationId
                );
            }
            return $assignmentId;
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectApplication(int $userId, int $shiftId, int $applicationId): void
    {
        if(!self::companyShift($userId,$shiftId)) throw new RuntimeException('Vaga não encontrada.');
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT a.professional_id,x.id assignment_id FROM tp_shift_applications a LEFT JOIN tp_assignments x ON x.shift_id=a.shift_id AND x.professional_id=a.professional_id AND x.status<>"cancelled" WHERE a.id=? AND a.shift_id=?');
        $st->execute([$applicationId,$shiftId]);
        $row=$st->fetch();
        if(!$row) throw new RuntimeException('Candidatura não encontrada.');
        if(!empty($row['assignment_id'])) throw new RuntimeException('O profissional já está confirmado neste turno.');
        $pdo->prepare('UPDATE tp_shift_applications SET status="rejected" WHERE id=? AND shift_id=?')->execute([$applicationId,$shiftId]);
        self::audit($userId,'application.rejected','shift_application',$applicationId,['shift_id'=>$shiftId]);
        $professionalUserId=self::professionalUserId((int)$row['professional_id']);
        if($professionalUserId){
            self::createNotification(
                $professionalUserId,
                'application_rejected',
                'Atualização sobre sua candidatura',
                'A empresa não confirmou sua candidatura para esta vaga.',
                'profissional/vagas/'.$shiftId,
                'application_rejected:'.$applicationId
            );
        }
    }

    public static function inviteProfessional(int $userId, int $professionalId, int $shiftId): void
    {
        $shift=self::companyShift($userId,$shiftId);
        if(!$shift) throw new RuntimeException('Selecione uma vaga válida.');
        if(!in_array($shift['status'],['published','filling'],true) || strtotime($shift['starts_at'])<=time()) throw new RuntimeException('Esta vaga não está aberta para convites.');

        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT p.id,p.user_id FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.id=? AND p.status="verified" AND u.status="active"');
        $st->execute([$professionalId]);
        $professional=$st->fetch();
        if(!$professional) throw new RuntimeException('Profissional indisponível.');

        $existing=$pdo->prepare('SELECT status FROM tp_shift_applications WHERE shift_id=? AND professional_id=? LIMIT 1');
        $existing->execute([$shiftId,$professionalId]);
        $previousStatus=$existing->fetchColumn();

        $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,?,"invited",NOW())
            ON DUPLICATE KEY UPDATE status=IF(status IN ("accepted","applied"),status,"invited"),applied_at=NOW()')->execute([$shiftId,$professionalId]);
        self::audit($userId,'professional.invited','professional',$professionalId,['shift_id'=>$shiftId]);

        if(!in_array($previousStatus,['accepted','applied'],true)){
            $company=self::companyProfile($userId);
            $body=($company['trade_name']??'Uma empresa').' convidou você para '.$shift['category_name'].' · '.$shift['title'].', em '.date('d/m/Y H:i',strtotime($shift['starts_at'])).'.';
            self::createNotification(
                (int)$professional['user_id'],
                'invitation',
                'Você recebeu um convite',
                $body,
                'profissional/vagas/'.$shiftId,
                'invitation:'.$shiftId
            );
        }
    }

    public static function companySchedule(int $userId): array
    {
        $companyId=self::companyIdForUser($userId);
        $sql="SELECT a.*,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,c.name category_name,u.name professional_name,
                     p.reliability_score,p.punctuality_score,p.attendance_score
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_job_categories c ON c.id=s.category_id
              JOIN tp_professionals p ON p.id=a.professional_id
              JOIN tp_users u ON u.id=p.user_id
              WHERE s.company_id=? AND a.status<>'cancelled' AND s.ends_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
              ORDER BY s.starts_at ASC,u.name ASC";
        $st=Database::connection()->prepare($sql);
        $st->execute([$companyId]);
        return $st->fetchAll();
    }

    public static function companyFinance(int $userId): array
    {
        $companyId=self::companyIdForUser($userId);
        $pdo=Database::connection();

        $st=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_ledger WHERE company_id=? AND direction='debit' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
        $st->execute([$companyId]);
        $spentMonth=(float)$st->fetchColumn();

        $st=$pdo->prepare("SELECT COALESCE(SUM(a.agreed_value),0) FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id WHERE s.company_id=? AND a.status IN ('confirmed','checked_in')");
        $st->execute([$companyId]);
        $committed=(float)$st->fetchColumn();

        $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id WHERE s.company_id=? AND a.status='completed' AND a.checkout_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
        $st->execute([$companyId]);
        $completed=(int)$st->fetchColumn();

        $st=$pdo->prepare("SELECT l.*,u.name professional_name,c.name category_name,s.starts_at
                           FROM tp_ledger l
                           LEFT JOIN tp_assignments a ON a.id=l.assignment_id
                           LEFT JOIN tp_professionals p ON p.id=l.professional_id
                           LEFT JOIN tp_users u ON u.id=p.user_id
                           LEFT JOIN tp_shifts s ON s.id=a.shift_id
                           LEFT JOIN tp_job_categories c ON c.id=s.category_id
                           WHERE l.company_id=? ORDER BY l.created_at DESC LIMIT 100");
        $st->execute([$companyId]);

        return [
            'spent_month'=>$spentMonth,
            'committed'=>$committed,
            'completed'=>$completed,
            'transactions'=>$st->fetchAll()
        ];
    }

    public static function suggestedProfessionals(int $limit=10): array
    {
        $sql='SELECT p.*,u.name,u.avatar_url FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id
              WHERE p.status="verified" ORDER BY p.reliability_score DESC,p.completed_shifts DESC LIMIT '.max(1,(int)$limit);
        return Database::connection()->query($sql)->fetchAll();
    }
    public static function professionalProfile(int $userId): array
    {
        $sql='SELECT p.*,u.name,u.email,u.phone,u.phone_verified_at,u.avatar_url FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.user_id=? LIMIT 1';
        $s=Database::connection()->prepare($sql);
        $s->execute([$userId]);
        return $s->fetch() ?: [];
    }
    public static function professionalOnboardingState(int $userId): array
    {
        $profile=self::professionalProfile($userId);
        if(!$profile) throw new RuntimeException('Perfil profissional não encontrado.');
        $pid=(int)$profile['id'];

        $categories=self::professionalCategoryIds($userId);
        $emailComplete=filter_var((string)($profile['email']??''),FILTER_VALIDATE_EMAIL)!==false;
        $birthComplete=!empty($profile['birth_date']);
        $phoneVerified=!empty($profile['phone_verified_at']);
        $basicComplete=trim((string)($profile['name']??''))!=='' 
            && strlen(preg_replace('/\D+/','',(string)($profile['cpf']??'')))===11
            && $birthComplete;
        $contactComplete=$emailComplete && $phoneVerified;

        $paymentComplete=trim((string)($profile['pix_key_type']??''))!==''
            && trim((string)($profile['pix_key']??''))!==''
            && trim((string)($profile['pix_holder_name']??''))!==''
            && trim((string)($profile['pix_holder_document']??''))!=='';

        $identityComplete=trim((string)($profile['rg']??''))!=='';
        $locationComplete=trim((string)($profile['address']??''))!=='' 
            && strlen(preg_replace('/\D+/','',(string)($profile['postal_code']??'')))===8
            && trim((string)($profile['city']??''))!==''
            && strlen(trim((string)($profile['state']??'')))===2;

        $docs=Database::connection()->prepare("SELECT * FROM tp_documents WHERE professional_id=? AND type='identity' ORDER BY created_at DESC,id DESC LIMIT 1");
        $docs->execute([$pid]);
        $identityDoc=$docs->fetch() ?: null;
        $identitySubmitted=$identityDoc!==null && in_array((string)$identityDoc['status'],['pending','verified'],true);
        $identityVerified=($identityDoc['status']??'')==='verified';

        // A primeira candidatura exige apenas as três etapas leves:
        // dados básicos, contato validado e pagamento. Verificação documental vem depois.
        $applicationReady=$basicComplete && $contactComplete && $paymentComplete && !empty($categories);
        $verificationDataComplete=$applicationReady && $identityComplete && $locationComplete;
        $profileVerified=($profile['status']??'pending')==='verified';

        $steps=[
            'account'=>$basicComplete,
            'whatsapp'=>$phoneVerified,
            'interests'=>!empty($categories),
            'payment'=>$paymentComplete,
            'identity_data'=>$identityComplete,
            'location'=>$locationComplete,
            'identity_document'=>$identitySubmitted,
        ];
        $done=count(array_filter($steps));
        $progress=(int)round(($done/count($steps))*100);

        $nextStep='done';
        if(!$identityComplete || !$basicComplete || !$contactComplete) $nextStep='identity';
        elseif(!$paymentComplete) $nextStep='payment';
        elseif(!$locationComplete) $nextStep='location';
        elseif(!$identitySubmitted) $nextStep='document';
        elseif(!$identityVerified) $nextStep='review';
        elseif(!$profileVerified) $nextStep='final_review';

        return [
            'profile'=>$profile,
            'categories'=>$categories,
            'steps'=>$steps,
            'progress'=>$progress,
            'basic_complete'=>$basicComplete,
            'contact_complete'=>$contactComplete,
            'identity_complete'=>$identityComplete,
            'location_complete'=>$locationComplete,
            'payment_complete'=>$paymentComplete,
            'phone_verified'=>$phoneVerified,
            'identity_document'=>$identityDoc,
            'identity_submitted'=>$identitySubmitted,
            'identity_verified'=>$identityVerified,
            'application_ready'=>$applicationReady,
            'application_data_complete'=>$applicationReady,
            'verification_data_complete'=>$verificationDataComplete,
            'can_apply'=>$applicationReady,
            'profile_verified'=>$profileVerified,
            'next_step'=>$nextStep,
        ];
    }

    public static function updateProfessionalOnboardingStep(int $userId,string $step,array $data): void
    {
        $profile=self::professionalProfile($userId);
        if(!$profile) throw new RuntimeException('Perfil profissional não encontrado.');
        $pid=(int)$profile['id'];
        $pdo=Database::connection();

        if($step==='identity'){
            $rg=mb_strtoupper(preg_replace('/[^0-9A-Za-z]+/','',(string)($data['rg']??'')));
            if(strlen($rg)<5||strlen($rg)>20) throw new InvalidArgumentException('Informe um documento de identidade válido.');

            $birth=trim((string)($data['birth_date']??''));
            $date=DateTime::createFromFormat('Y-m-d',$birth);
            if(!$date || $date->format('Y-m-d')!==$birth) throw new InvalidArgumentException('Informe uma data de nascimento válida.');
            $today=new DateTime('today');
            if($date>$today || $today->diff($date)->y<18) throw new InvalidArgumentException('É necessário ter pelo menos 18 anos.');

            $headline=mb_substr(trim((string)($data['headline']??'')),0,190);
            $email=mb_strtolower(trim((string)($data['email']??'')));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
            $dup=$pdo->prepare('SELECT id FROM tp_users WHERE email=? AND id<>? LIMIT 1');
            $dup->execute([$email,$userId]);
            if($dup->fetchColumn()) throw new RuntimeException('Este e-mail já está vinculado a outra conta.');

            $pdo->beginTransaction();
            try{
                $pdo->prepare('UPDATE tp_professionals SET rg=?,birth_date=?,headline=COALESCE(NULLIF(?,""),headline) WHERE id=?')
                    ->execute([$rg,$birth,$headline,$pid]);
                $pdo->prepare('UPDATE tp_users SET email=?,updated_at=NOW() WHERE id=?')->execute([$email,$userId]);
                self::audit($userId,'onboarding.professional_identity','professional',$pid);
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            return;
        }

        if($step==='location'){
            $postal=preg_replace('/\D+/','',(string)($data['postal_code']??''));
            $address=trim((string)($data['address']??''));
            $city=trim((string)($data['city']??''));
            $state=mb_strtoupper(trim((string)($data['state']??'')));
            if(strlen($postal)!==8 || $address==='' || $city==='' || strlen($state)!==2) throw new InvalidArgumentException('Preencha CEP, endereço, cidade e UF.');
            $pdo->prepare('UPDATE tp_professionals SET postal_code=?,address=?,city=?,state=? WHERE id=?')
                ->execute([$postal,$address,$city,$state,$pid]);
            self::audit($userId,'onboarding.professional_location','professional',$pid);
            return;
        }

        if($step==='payment'){
            $type=trim((string)($data['pix_key_type']??''));
            $key=trim((string)($data['pix_key']??''));
            $holder=trim((string)($data['pix_holder_name']??''));
            $holderDoc=preg_replace('/\D+/','',(string)($data['pix_holder_document']??''));
            if(!in_array($type,['cpf','email','phone','random'],true) || $key==='' || $holder==='') throw new InvalidArgumentException('Preencha os dados da chave Pix.');
            $cpf=preg_replace('/\D+/','',(string)($profile['cpf']??''));
            if($holderDoc!==$cpf) throw new InvalidArgumentException('Por segurança, o CPF do titular do Pix deve ser o mesmo CPF da conta.');
            $pdo->prepare('UPDATE tp_professionals SET pix_key_type=?,pix_key=?,pix_holder_name=?,pix_holder_document=? WHERE id=?')
                ->execute([$type,$key,$holder,$holderDoc,$pid]);
            self::audit($userId,'onboarding.professional_payment','professional',$pid);
            return;
        }

        throw new InvalidArgumentException('Etapa de cadastro inválida.');
    }
    public static function professionalHome(int $userId): array
    {
        $pdo=Database::connection();
        $pid=self::professionalIdForUser($userId);
        $profile=self::professionalProfile($userId);

        $s=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id WHERE a.professional_id=? AND a.status<>'cancelled' AND YEARWEEK(s.starts_at,1)=YEARWEEK(CURDATE(),1)");
        $s->execute([$pid]);
        $week=(int)$s->fetchColumn();

        $s=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_ledger WHERE professional_id=? AND direction='credit' AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
        $s->execute([$pid]);
        $earnings=(float)$s->fetchColumn();

        return [
            'profile'=>$profile,
            'onboarding'=>self::professionalOnboardingState($userId),
            'kpis'=>[
                'week'=>$week,
                'earnings'=>$earnings,
                'reliability'=>(int)($profile['reliability_score']??100),
                'punctuality'=>(int)($profile['punctuality_score']??100),
            ],
            'opportunities'=>self::opportunities($userId,5),
            'assignments'=>self::professionalAssignments($userId,6),
            'documents'=>self::documents($userId),
        ];
    }
    public static function globalSearch(int $userId,string $role,string $query,int $limit=30): array
    {
        $q=mb_substr(trim($query),0,80);
        if(mb_strlen($q)<2) return [];
        $like='%'.$q.'%';
        $limit=max(1,min(50,$limit));
        $pdo=Database::connection();
        $results=[];

        if($role==='professional'){
            $sql="SELECT s.id,s.title,s.starts_at,s.city,s.state,s.shift_value,jc.name category_name,
                         co.trade_name company_name
                  FROM tp_shifts s
                  JOIN tp_job_categories jc ON jc.id=s.category_id
                  JOIN tp_companies co ON co.id=s.company_id
                  WHERE s.status IN ('published','filling')
                    AND s.starts_at>NOW()
                    AND co.status='verified'
                    AND (
                      s.title LIKE ? OR s.description LIKE ? OR s.city LIKE ? OR s.state LIKE ? OR
                      s.address LIKE ? OR jc.name LIKE ? OR co.trade_name LIKE ? OR co.legal_name LIKE ?
                    )
                  ORDER BY s.starts_at ASC
                  LIMIT {$limit}";
            $st=$pdo->prepare($sql);
            $st->execute(array_fill(0,8,$like));
            foreach($st->fetchAll() as $row){
                $results[]=[
                    'type'=>'Vaga',
                    'title'=>(string)($row['title']?:$row['category_name']),
                    'subtitle'=>$row['company_name'].' · '.$row['city'].' - '.$row['state'],
                    'meta'=>date('d/m/Y H:i',strtotime((string)$row['starts_at'])).' · '.money((float)$row['shift_value']),
                    'url'=>'profissional/vagas/'.(int)$row['id'],
                    'icon'=>'briefcase',
                ];
            }
            return $results;
        }

        if($role==='company'){
            $companyId=self::companyIdForUser($userId);
            if($companyId){
                $sql="SELECT s.id,s.title,s.starts_at,s.city,s.state,s.status,jc.name category_name
                      FROM tp_shifts s
                      JOIN tp_job_categories jc ON jc.id=s.category_id
                      WHERE s.company_id=?
                        AND (s.title LIKE ? OR s.description LIKE ? OR s.city LIKE ? OR s.state LIKE ? OR jc.name LIKE ?)
                      ORDER BY s.starts_at DESC
                      LIMIT {$limit}";
                $st=$pdo->prepare($sql);
                $st->execute([$companyId,$like,$like,$like,$like,$like]);
                foreach($st->fetchAll() as $row){
                    $results[]=[
                        'type'=>'Vaga',
                        'title'=>(string)($row['title']?:$row['category_name']),
                        'subtitle'=>$row['category_name'].' · '.$row['city'].' - '.$row['state'],
                        'meta'=>date('d/m/Y H:i',strtotime((string)$row['starts_at'])).' · '.ucfirst(str_replace('_',' ',(string)$row['status'])),
                        'url'=>'empresa/vagas/'.(int)$row['id'],
                        'icon'=>'briefcase',
                    ];
                }
            }

            $remaining=max(1,$limit-count($results));
            $sql="SELECT p.id,u.name,p.headline,p.city,p.state,p.rating,p.completed_shifts
                  FROM tp_professionals p
                  JOIN tp_users u ON u.id=p.user_id
                  WHERE p.status='verified' AND (
                    u.name LIKE ? OR p.headline LIKE ? OR p.city LIKE ? OR p.state LIKE ? OR
                    EXISTS (
                      SELECT 1 FROM tp_professional_categories pc
                      JOIN tp_job_categories jc ON jc.id=pc.category_id
                      WHERE pc.professional_id=p.id AND jc.name LIKE ?
                    )
                  )
                  ORDER BY p.reliability_score DESC,p.completed_shifts DESC
                  LIMIT {$remaining}";
            $st=$pdo->prepare($sql);
            $st->execute([$like,$like,$like,$like,$like]);
            foreach($st->fetchAll() as $row){
                $results[]=[
                    'type'=>'Profissional',
                    'title'=>(string)$row['name'],
                    'subtitle'=>trim((string)($row['headline']??''))!==''?(string)$row['headline']:'Profissional TurnoPronto',
                    'meta'=>trim(($row['city']??'').' - '.($row['state']??''),' -').' · '.number_format((float)$row['rating'],1,',','.').'★',
                    'url'=>'empresa/profissionais#profissional-'.(int)$row['id'],
                    'icon'=>'users',
                ];
            }
            return array_slice($results,0,$limit);
        }

        if($role==='admin'){
            $companyLimit=max(5,(int)floor($limit/3));
            $st=$pdo->prepare("SELECT id,trade_name,legal_name,city,state,status FROM tp_companies
                               WHERE trade_name LIKE ? OR legal_name LIKE ? OR cnpj LIKE ? OR city LIKE ?
                               ORDER BY trade_name LIMIT {$companyLimit}");
            $st->execute([$like,$like,$like,$like]);
            foreach($st->fetchAll() as $row){
                $results[]=[
                    'type'=>'Empresa',
                    'title'=>(string)$row['trade_name'],
                    'subtitle'=>(string)$row['legal_name'],
                    'meta'=>trim(($row['city']??'').' - '.($row['state']??''),' -').' · '.ucfirst((string)$row['status']),
                    'url'=>'admin/verificacao/empresa/'.(int)$row['id'],
                    'icon'=>'briefcase',
                ];
            }

            $st=$pdo->prepare("SELECT p.id,u.name,u.email,p.headline,p.city,p.state,p.status
                               FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id
                               WHERE u.name LIKE ? OR u.email LIKE ? OR p.cpf LIKE ? OR p.headline LIKE ? OR p.city LIKE ?
                               ORDER BY u.name LIMIT {$companyLimit}");
            $st->execute([$like,$like,$like,$like,$like]);
            foreach($st->fetchAll() as $row){
                $results[]=[
                    'type'=>'Profissional',
                    'title'=>(string)$row['name'],
                    'subtitle'=>(string)($row['headline']?:$row['email']),
                    'meta'=>trim(($row['city']??'').' - '.($row['state']??''),' -').' · '.ucfirst((string)$row['status']),
                    'url'=>'admin/verificacao/profissional/'.(int)$row['id'],
                    'icon'=>'users',
                ];
            }

            $remaining=max(1,$limit-count($results));
            $st=$pdo->prepare("SELECT s.id,s.title,s.starts_at,s.status,jc.name category_name,co.trade_name company_name
                               FROM tp_shifts s
                               JOIN tp_job_categories jc ON jc.id=s.category_id
                               JOIN tp_companies co ON co.id=s.company_id
                               WHERE s.title LIKE ? OR jc.name LIKE ? OR co.trade_name LIKE ? OR s.city LIKE ?
                               ORDER BY s.starts_at DESC LIMIT {$remaining}");
            $st->execute([$like,$like,$like,$like]);
            foreach($st->fetchAll() as $row){
                $results[]=[
                    'type'=>'Vaga',
                    'title'=>(string)($row['title']?:$row['category_name']),
                    'subtitle'=>$row['company_name'].' · '.$row['category_name'],
                    'meta'=>date('d/m/Y H:i',strtotime((string)$row['starts_at'])).' · '.ucfirst((string)$row['status']),
                    'url'=>'admin/dashboard',
                    'icon'=>'briefcase',
                ];
            }
            return array_slice($results,0,$limit);
        }

        return [];
    }

    public static function opportunities(int $userId, int $limit=50): array
    {
        $pid=self::professionalIdForUser($userId);
        $mode=self::hasColumn('tp_shifts','acceptance_mode') ? ',s.acceptance_mode' : '';
        $sql="SELECT s.*,jc.name category_name,co.trade_name company_name,co.rating company_rating $mode,
                     TIMESTAMPDIFF(MINUTE,s.starts_at,s.ends_at) duration_minutes,
                     (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id AND a.status IN ('applied','invited','accepted')) candidates
              FROM tp_shifts s
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE s.status IN ('published','filling') AND s.starts_at>NOW()
                AND (
                    NOT EXISTS (SELECT 1 FROM tp_professional_categories pc0 WHERE pc0.professional_id=?)
                    OR EXISTS (
                        SELECT 1 FROM tp_professional_categories pc
                        WHERE pc.professional_id=? AND pc.category_id=s.category_id
                    )
                )
                AND NOT EXISTS (
                    SELECT 1 FROM tp_assignments x
                    WHERE x.shift_id=s.id AND x.professional_id=? AND x.status<>'cancelled'
                )
              ORDER BY s.starts_at ASC LIMIT ".max(1,(int)$limit);
        $st=Database::connection()->prepare($sql);
        $st->execute([$pid,$pid,$pid]);
        $rows=$st->fetchAll();
        foreach($rows as &$row){
            if(!array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        }
        return $rows;
    }

    public static function publicOpportunities(int $limit=80): array
    {
        $mode=self::hasColumn('tp_shifts','acceptance_mode') ? ',s.acceptance_mode' : '';
        $sql="SELECT s.id,s.title,s.category_id,s.starts_at,s.ends_at,s.city,s.state,s.shift_value,s.required_workers,
                     jc.name category_name,co.trade_name company_name,co.rating company_rating $mode,
                     TIMESTAMPDIFF(MINUTE,s.starts_at,s.ends_at) duration_minutes,
                     (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id AND a.status IN ('applied','invited','accepted')) candidates
              FROM tp_shifts s
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE s.status IN ('published','filling') AND s.starts_at>NOW() AND co.status='verified'
              ORDER BY s.starts_at ASC
              LIMIT ".max(1,min(200,$limit));
        $rows=Database::connection()->query($sql)->fetchAll();
        foreach($rows as &$row){
            if(!array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        }
        return $rows;
    }

    public static function publicShift(int $id): ?array
    {
        $mode=self::hasColumn('tp_shifts','acceptance_mode') ? ',s.acceptance_mode' : '';
        $sql="SELECT s.*,jc.name category_name,co.trade_name company_name,co.rating company_rating,co.logo_url company_logo $mode,
                     TIMESTAMPDIFF(MINUTE,s.starts_at,s.ends_at) duration_minutes,
                     (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id AND a.status IN ('applied','invited','accepted')) candidates
              FROM tp_shifts s
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE s.id=? AND s.status IN ('published','filling') AND s.starts_at>NOW() AND co.status='verified'
              LIMIT 1";
        $st=Database::connection()->prepare($sql);
        $st->execute([$id]);
        $row=$st->fetch() ?: null;
        if($row && !array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        return $row;
    }

    public static function shift(int $id): ?array
    {
        $mode=self::hasColumn('tp_shifts','acceptance_mode') ? ',s.acceptance_mode' : '';
        $sql="SELECT s.*,jc.name category_name,co.trade_name company_name,co.rating company_rating,co.logo_url company_logo $mode,
                     (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id) candidates
              FROM tp_shifts s
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE s.id=?";
        $st=Database::connection()->prepare($sql);
        $st->execute([$id]);
        $row=$st->fetch() ?: null;
        if($row && !array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        return $row;
    }
    public static function acceptShift(int $userId, int $shiftId): array
    {
        $pdo=Database::connection();
        $pid=self::professionalIdForUser($userId);
        if(!$pid) throw new RuntimeException('Perfil profissional não encontrado.');

        $readiness=self::professionalOnboardingState($userId);
        if(!$readiness['application_ready']){
            throw new RuntimeException('Conclua as informações básicas, de contato e de pagamento para enviar sua candidatura.');
        }

        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT * FROM tp_shifts WHERE id=? FOR UPDATE');
            $st->execute([$shiftId]);
            $shift=$st->fetch();
            if(!$shift || !in_array($shift['status'],['published','filling'],true)) throw new RuntimeException('Esta vaga não está mais disponível.');
            if(strtotime($shift['starts_at'])<=time()) throw new RuntimeException('Este turno já iniciou.');

            $pdo->prepare('INSERT IGNORE INTO tp_professional_categories (professional_id,category_id,experience_level) VALUES (?,?,"initial")')
                ->execute([$pid,(int)$shift['category_id']]);

            $st=$pdo->prepare('SELECT id FROM tp_assignments WHERE shift_id=? AND professional_id=? AND status<>"cancelled" LIMIT 1');
            $st->execute([$shiftId,$pid]);
            $existing=$st->fetchColumn();
            if($existing){
                $pdo->commit();
                return ['status'=>'confirmed','assignment_id'=>(int)$existing];
            }

            $mode=$shift['acceptance_mode']??'automatic';

            // Candidatura pode ser enviada antes da verificação documental.
            // A empresa só consegue confirmar o turno depois que o TurnoPronto liberar o perfil.
            if(!$readiness['profile_verified']){
                $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,?,"applied",NOW())
                               ON DUPLICATE KEY UPDATE status=IF(status="accepted","accepted","applied"),applied_at=NOW()')
                    ->execute([$shiftId,$pid]);
                $pdo->prepare('UPDATE tp_shifts SET status="filling" WHERE id=? AND status="published"')->execute([$shiftId]);
                self::audit($userId,'shift.applied_pending_verification','shift',$shiftId);
                $pdo->commit();
                self::notifyCompanyMembersOfApplication($shiftId,$pid);
                return ['status'=>'verification_pending','assignment_id'=>null];
            }

            if($mode==='manual'){
                $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,?,"applied",NOW())
                               ON DUPLICATE KEY UPDATE status=IF(status="accepted","accepted","applied"),applied_at=NOW()')
                    ->execute([$shiftId,$pid]);
                $pdo->prepare('UPDATE tp_shifts SET status="filling" WHERE id=? AND status="published"')->execute([$shiftId]);
                self::audit($userId,'shift.applied','shift',$shiftId);
                $pdo->commit();
                self::notifyCompanyMembersOfApplication($shiftId,$pid);
                return ['status'=>'applied','assignment_id'=>null];
            }

            $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status<>'cancelled'");
            $st->execute([$shiftId]);
            if((int)$st->fetchColumn()>=(int)$shift['required_workers']) throw new RuntimeException('Todas as vagas deste turno já foram preenchidas.');

            $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,?,"accepted",NOW())
                           ON DUPLICATE KEY UPDATE status="accepted",applied_at=NOW()')->execute([$shiftId,$pid]);
            $pdo->prepare('INSERT INTO tp_assignments (shift_id,professional_id,status,confirmed_at,agreed_value) VALUES (?,?,"confirmed",NOW(),?)')
                ->execute([$shiftId,$pid,$shift['shift_value']]);
            $assignmentId=(int)$pdo->lastInsertId();

            $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status<>'cancelled'");
            $st->execute([$shiftId]);
            $count=(int)$st->fetchColumn();
            $pdo->prepare('UPDATE tp_shifts SET status=? WHERE id=?')->execute([$count>=(int)$shift['required_workers']?'confirmed':'filling',$shiftId]);

            self::audit($userId,'shift.accepted','shift',$shiftId,['assignment_id'=>$assignmentId]);
            $pdo->commit();
            return ['status'=>'confirmed','assignment_id'=>$assignmentId];
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function professionalAssignments(int $userId, int $limit=50): array
    {
        $pid=self::professionalIdForUser($userId);
        $sql="SELECT a.*,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,jc.name category_name,co.trade_name company_name
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE a.professional_id=? AND a.status<>'cancelled'
              ORDER BY s.starts_at ASC LIMIT ".max(1,(int)$limit);
        $st=Database::connection()->prepare($sql);
        $st->execute([$pid]);
        return $st->fetchAll();
    }

    public static function assignment(int $userId, int $assignmentId): ?array
    {
        $pid=self::professionalIdForUser($userId);
        $sql='SELECT a.*,TIMESTAMPDIFF(SECOND,a.checkin_at,NOW()) checkout_elapsed_seconds,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,s.dress_code,s.notes,jc.name category_name,co.trade_name company_name
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_job_categories jc ON jc.id=s.category_id
              JOIN tp_companies co ON co.id=s.company_id
              WHERE a.id=? AND a.professional_id=?';
        $st=Database::connection()->prepare($sql);
        $st->execute([$assignmentId,$pid]);
        return $st->fetch() ?: null;
    }

    public static function checkIn(int $userId, int $assignmentId, ?string $pin=null): void
    {
        $a=self::assignment($userId,$assignmentId);
        if(!$a) throw new RuntimeException('Turno não encontrado.');
        if(!in_array($a['status'],['confirmed','checked_in'],true)) throw new RuntimeException('Este turno não permite check-in.');

        $shift=self::shift((int)$a['shift_id']);
        $pin=trim((string)$pin);
        if(!empty($shift['checkin_pin']) && ($pin==='' || !hash_equals((string)$shift['checkin_pin'],$pin))) throw new RuntimeException('Informe o PIN correto de check-in.');

        Database::connection()->prepare('UPDATE tp_assignments SET status="checked_in",checkin_at=COALESCE(checkin_at,NOW()),checkin_method=? WHERE id=?')
            ->execute([$pin?'pin':'web',$assignmentId]);
        self::audit($userId,'assignment.checkin','assignment',$assignmentId);
    }

    public static function checkOut(int $userId, int $assignmentId): void
    {
        $a=self::assignment($userId,$assignmentId);
        if(!$a) throw new RuntimeException('Turno não encontrado.');
        if($a['status']!=='checked_in') throw new RuntimeException('Faça o check-in antes de encerrar.');
        $elapsed=$a['checkout_elapsed_seconds'];
        if($elapsed===null) throw new RuntimeException('Horário do check-in não encontrado.');
        $elapsed=max(0,(int)$elapsed);
        if($elapsed<15*60){
            $remaining=(15*60)-$elapsed;
            $minutes=(int)ceil($remaining/60);
            throw new RuntimeException('O check-out será liberado 15 minutos após o check-in. Aguarde cerca de '.$minutes.' minuto(s).');
        }

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE tp_assignments SET status="completed",checkout_at=NOW() WHERE id=?')->execute([$assignmentId]);
            $pdo->prepare('UPDATE tp_professionals SET completed_shifts=completed_shifts+1 WHERE id=?')->execute([$a['professional_id']]);
            $companyId=$pdo->query('SELECT company_id FROM tp_shifts WHERE id='.(int)$a['shift_id'])->fetchColumn();

            $exists=$pdo->prepare('SELECT COUNT(*) FROM tp_ledger WHERE assignment_id=? AND kind="shift_payment"');
            $exists->execute([$assignmentId]);
            if(!(int)$exists->fetchColumn()){
                $pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"credit",?,"shift_payment","available",NOW())')
                    ->execute([$companyId,$a['professional_id'],$assignmentId,$a['agreed_value']]);
                $pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"debit",?,"shift_cost","settled",NOW())')
                    ->execute([$companyId,$a['professional_id'],$assignmentId,$a['agreed_value']]);
            }

            $pdo->prepare('INSERT INTO tp_reputation_events (professional_id,assignment_id,event_type,severity,points_delta,description,occurred_at) VALUES (?,?,"completed_shift","positive",1,"Turno concluído.",NOW())')
                ->execute([$a['professional_id'],$assignmentId]);

            self::audit($userId,'assignment.checkout','assignment',$assignmentId);
            $pdo->commit();
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function earnings(int $userId): array
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare("SELECT COALESCE(SUM(amount),0) total,COALESCE(SUM(CASE WHEN status='available' THEN amount ELSE 0 END),0) available FROM tp_ledger WHERE professional_id=? AND direction='credit'");
        $st->execute([$pid]);
        $summary=$st->fetch() ?: ['total'=>0,'available'=>0];

        $st=Database::connection()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') month,SUM(amount) amount FROM tp_ledger WHERE professional_id=? AND direction='credit' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month");
        $st->execute([$pid]);
        return ['summary'=>$summary,'months'=>$st->fetchAll()];
    }


    public static function updateCompanyAccount(int $userId, array $data): void
    {
        $companyId=self::companyIdForUser($userId);
        if(!$companyId) throw new RuntimeException('Empresa não encontrada.');
        $name=trim((string)($data['name']??''));
        $phone=self::normalizeCompanyVerificationPhone((string)($data['phone']??''));
        $legal=trim((string)($data['legal_name']??''));
        $trade=trim((string)($data['trade_name']??''));
        $cnpj=trim((string)($data['cnpj']??''));
        $companyEmail=mb_strtolower(trim((string)($data['company_email']??'')));
        $address=trim((string)($data['address']??''));
        $mapsUrl=self::normalizeCompanyMapsUrl((string)($data['maps_url']??''));
        $city=trim((string)($data['city']??''));
        $state=mb_strtoupper(trim((string)($data['state']??'')));
        if($name===''||$legal===''||$trade===''||$cnpj===''||$address===''||$city===''||strlen($state)!==2) throw new InvalidArgumentException('Preencha todos os campos obrigatórios.');
        if(!filter_var($companyEmail,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe o e-mail da empresa.');

        self::ensureCompanyMapsColumn();
        self::ensureCompanyEmailColumn();
        $pdo=Database::connection();
        $current=$pdo->prepare('SELECT phone FROM tp_users WHERE id=? LIMIT 1');
        $current->execute([$userId]);
        $currentPhone=(string)$current->fetchColumn();
        $phoneChanged=$phone!==$currentPhone;

        $pdo->beginTransaction();
        try{
            if($phoneChanged){
                $pdo->prepare('UPDATE tp_users SET name=?,phone=?,phone_verified_at=NULL,updated_at=NOW() WHERE id=?')->execute([$name,$phone,$userId]);
            }else{
                $pdo->prepare('UPDATE tp_users SET name=?,phone=?,updated_at=NOW() WHERE id=?')->execute([$name,$phone,$userId]);
            }
            $pdo->prepare('UPDATE tp_companies SET legal_name=?,trade_name=?,cnpj=?,company_email=?,address=?,city=?,state=?,maps_url=? WHERE id=?')->execute([$legal,$trade,$cnpj,$companyEmail,$address,$city,$state,$mapsUrl,$companyId]);
            self::audit($userId,'account.company_updated','company',$companyId);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function updateProfessionalAccount(int $userId, array $data): void
    {
        $professionalId=self::professionalIdForUser($userId);
        if(!$professionalId) throw new RuntimeException('Perfil profissional não encontrado.');

        $name=trim((string)($data['name']??''));
        $headline=mb_substr(trim((string)($data['headline']??'')),0,190);
        $bio=mb_substr(trim((string)($data['bio']??'')),0,2000);
        $email=mb_strtolower(trim((string)($data['email']??'')));
        $categories=array_values(array_unique(array_filter(array_map('intval',(array)($data['categories']??[])))));

        if(mb_strlen($name)<3) throw new InvalidArgumentException('Informe seu nome completo.');
        if(!$categories) throw new InvalidArgumentException('Selecione pelo menos uma área de interesse.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');

        $pdo=Database::connection();
        $dup=$pdo->prepare('SELECT id FROM tp_users WHERE email=? AND id<>? LIMIT 1');
        $dup->execute([$email,$userId]);
        if($dup->fetchColumn()) throw new RuntimeException('Este e-mail já está vinculado a outra conta.');

        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE tp_users SET name=?,email=?,updated_at=NOW() WHERE id=?')
                ->execute([$name,$email,$userId]);
            $pdo->prepare('UPDATE tp_professionals SET headline=?,bio=? WHERE id=?')
                ->execute([$headline!==''?$headline:null,$bio,$professionalId]);
            $pdo->prepare('DELETE FROM tp_professional_categories WHERE professional_id=?')->execute([$professionalId]);
            $cat=$pdo->prepare('INSERT IGNORE INTO tp_professional_categories (professional_id,category_id,experience_level) SELECT ?,id,"experienced" FROM tp_job_categories WHERE id=? AND active=1');
            foreach($categories as $categoryId)$cat->execute([$professionalId,$categoryId]);
            self::audit($userId,'account.professional_updated','professional',$professionalId);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function professionalCategoryIds(int $userId): array
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT category_id FROM tp_professional_categories WHERE professional_id=? ORDER BY category_id');
        $st->execute([$pid]);
        return array_map('intval',array_column($st->fetchAll(),'category_id'));
    }

    private static function ensureDocumentAutomationSchema(): void
    {
        $pdo=Database::connection();
        $columns=[
            'automation_status'=>'VARCHAR(30) NULL AFTER rejection_reason',
            'automation_reason'=>'VARCHAR(500) NULL AFTER automation_status',
            'automation_json'=>'LONGTEXT NULL AFTER automation_reason',
            'automation_checked_at'=>'DATETIME NULL AFTER automation_json',
        ];
        foreach($columns as $column=>$definition){
            if(!self::hasColumn('tp_documents',$column)){
                $pdo->exec("ALTER TABLE tp_documents ADD COLUMN {$column} {$definition}");
                self::$columnCache['tp_documents.'.$column]=true;
            }
        }
    }

    private static function runProfessionalDocumentAutomation(int $actorUserId,int $documentId,int $professionalId,string $type,string $absolutePath,string $mime): void
    {
        if($type!=='identity') return;
        self::ensureDocumentAutomationSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT p.user_id,p.cpf,u.name FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.id=? LIMIT 1');
        $st->execute([$professionalId]);
        $profile=$st->fetch();
        if(!$profile) return;

        $result=DocumentRecognition::analyze($absolutePath,$mime,(string)$profile['name'],(string)$profile['cpf']);
        $stored=$result['raw']??null;
        $pdo->prepare('UPDATE tp_documents SET automation_status=?,automation_reason=?,automation_json=?,automation_checked_at=NOW() WHERE id=?')
            ->execute([
                $result['status']??'manual',
                mb_substr((string)($result['reason']??''),0,500),
                $stored?json_encode($stored,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
                $documentId
            ]);

        if(($result['status']??'')==='match' && (bool)(app_config('document_recognition.auto_approve') ?? true)){
            $pdo->prepare('UPDATE tp_documents SET status="verified",verified_at=NOW(),rejection_reason=NULL WHERE id=? AND status="pending"')
                ->execute([$documentId]);
            self::audit($actorUserId,'document.auto_verified','document',$documentId,[
                'professional_id'=>$professionalId,
                'name_score'=>$result['name_score']??null,
                'document_type'=>$result['document_type']??null
            ]);

            $state=self::professionalOnboardingState((int)$profile['user_id']);
            if($state['verification_data_complete'] && $state['identity_verified']){
                $pdo->prepare('UPDATE tp_professionals SET status="verified" WHERE id=? AND status="pending"')->execute([$professionalId]);
                if($pdo->query('SELECT ROW_COUNT()')->fetchColumn()){
                    self::audit($actorUserId,'verification.professional_auto','professional',$professionalId,['document_id'=>$documentId]);
                    self::createNotification(
                        (int)$profile['user_id'],
                        'verification_approved',
                        'Cadastro verificado automaticamente',
                        'Seu documento foi validado e seu perfil está liberado para confirmação de turnos.',
                        'profissional/inicio',
                        'professional_auto_verified:'.$professionalId
                    );
                }
            }
        }elseif(($result['status']??'')==='review'){
            self::audit($actorUserId,'document.auto_review_required','document',$documentId,[
                'professional_id'=>$professionalId,
                'reason'=>$result['reason']??'Revisão manual necessária'
            ]);
        }
    }

    public static function uploadProfessionalDocument(int $userId, string $type, array $file): int
    {
        $pid=self::professionalIdForUser($userId);
        if(!$pid) throw new RuntimeException('Perfil profissional não encontrado.');
        $labels=[
            'identity'=>'Documento de identidade',
            'cpf'=>'CPF',
            'address'=>'Comprovante de residência',
            'food'=>'Certificado de manipulação de alimentos',
            'other'=>'Outro documento'
        ];
        if(!isset($labels[$type])) throw new InvalidArgumentException('Tipo de documento inválido.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Selecione um arquivo válido.');
        if((int)($file['size']??0)<=0 || (int)$file['size']>5*1024*1024) throw new RuntimeException('O arquivo deve ter no máximo 5 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp)) throw new RuntimeException('Upload não reconhecido pelo servidor.');

        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp) ?: '';
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Envie PDF, JPG ou PNG.');

        $root=dirname(__DIR__);
        $dir=$root.'/storage/uploads/documents';
        if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta privada de documentos.');
        $filename=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        $absolute=$dir.'/'.$filename;
        if(!move_uploaded_file($tmp,$absolute)) throw new RuntimeException('Falha ao salvar o documento.');
        @chmod($absolute,0660);
        $relative='storage/uploads/documents/'.$filename;

        $pdo=Database::connection();
        $st=$pdo->prepare('INSERT INTO tp_documents (professional_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,?,?,"pending",?,?,?,NOW())');
        $st->execute([$pid,$type,$labels[$type],$relative,mb_substr((string)($file['name']??'documento'),0,255),$mime]);
        $id=(int)$pdo->lastInsertId();
        self::audit($userId,'document.uploaded','document',$id,['type'=>$type,'mime'=>$mime]);
        try{ self::runProfessionalDocumentAutomation($userId,$id,$pid,$type,$absolute,$mime); }
        catch(Throwable $e){ self::audit($userId,'document.auto_analysis_failed','document',$id,['reason'=>$e->getMessage()]); }
        return $id;
    }

    public static function adminUploadProfessionalDocument(int $adminUserId,int $professionalId,string $type,array $file): int
    {
        $labels=[
            'identity'=>'Documento de identidade',
            'cpf'=>'CPF',
            'address'=>'Comprovante de residência',
            'food'=>'Certificado de manipulação de alimentos',
            'other'=>'Outro documento'
        ];
        if(!isset($labels[$type])) throw new InvalidArgumentException('Tipo de documento inválido.');

        $pdo=Database::connection();
        $professional=$pdo->prepare('SELECT id FROM tp_professionals WHERE id=? LIMIT 1');
        $professional->execute([$professionalId]);
        if(!$professional->fetchColumn()) throw new RuntimeException('Profissional não encontrado.');

        $current=$pdo->prepare('SELECT status FROM tp_documents WHERE professional_id=? AND type=? ORDER BY created_at DESC,id DESC LIMIT 1');
        $current->execute([$professionalId,$type]);
        $currentStatus=(string)($current->fetchColumn()?:'');
        if($currentStatus==='verified') throw new RuntimeException('Este documento já foi verificado.');
        if($currentStatus==='pending') throw new RuntimeException('Já existe um documento aguardando análise para este item.');

        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Selecione um arquivo válido.');
        if((int)($file['size']??0)<=0 || (int)$file['size']>5*1024*1024) throw new RuntimeException('O arquivo deve ter no máximo 5 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_uploaded_file($tmp)) throw new RuntimeException('Upload não reconhecido pelo servidor.');

        $finfo=new finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp) ?: '';
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Envie PDF, JPG ou PNG.');

        $dir=dirname(__DIR__).'/storage/uploads/documents';
        if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta privada de documentos.');
        $filename=bin2hex(random_bytes(20)).'.'.$allowed[$mime];
        $absolute=$dir.'/'.$filename;
        if(!move_uploaded_file($tmp,$absolute)) throw new RuntimeException('Falha ao salvar o documento.');
        @chmod($absolute,0660);
        $relative='storage/uploads/documents/'.$filename;

        $st=$pdo->prepare('INSERT INTO tp_documents (professional_id,type,label,status,file_path,original_name,mime_type,created_at) VALUES (?,?,?,"pending",?,?,?,NOW())');
        $st->execute([$professionalId,$type,$labels[$type],$relative,mb_substr((string)($file['name']??'documento'),0,255),$mime]);
        $id=(int)$pdo->lastInsertId();
        self::audit($adminUserId,'verification.professional_document_uploaded_by_admin','document',$id,[
            'professional_id'=>$professionalId,'type'=>$type,'mime'=>$mime
        ]);
        try{ self::runProfessionalDocumentAutomation($adminUserId,$id,$professionalId,$type,$absolute,$mime); }
        catch(Throwable $e){ self::audit($adminUserId,'document.auto_analysis_failed','document',$id,['reason'=>$e->getMessage()]); }
        return $id;
    }

    public static function adminDocument(int $documentId): ?array
    {
        $sql="SELECT d.*,u.name professional_name FROM tp_documents d JOIN tp_professionals p ON p.id=d.professional_id JOIN tp_users u ON u.id=p.user_id WHERE d.id=? LIMIT 1";
        $st=Database::connection()->prepare($sql);
        $st->execute([$documentId]);
        return $st->fetch() ?: null;
    }

    public static function adminPendingDocuments(): array
    {
        $sql="SELECT d.*,u.name professional_name,u.email,p.status professional_status
              FROM tp_documents d
              JOIN tp_professionals p ON p.id=d.professional_id
              JOIN tp_users u ON u.id=p.user_id
              WHERE d.status='pending'
              ORDER BY d.created_at ASC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function setDocumentVerification(int $adminUserId, int $documentId, string $decision, string $reason=''): void
    {
        if(!in_array($decision,['verified','rejected'],true)) throw new InvalidArgumentException('Decisão inválida.');
        $reason=mb_substr(trim($reason),0,500);
        if($decision==='rejected' && $reason==='') $reason='Documento rejeitado na revisão.';
        $st=Database::connection()->prepare('UPDATE tp_documents SET status=?,verified_at=?,rejection_reason=? WHERE id=? AND status="pending"');
        $st->execute([$decision,$decision==='verified'?date('Y-m-d H:i:s'):null,$decision==='rejected'?$reason:null,$documentId]);
        if($st->rowCount()===0) throw new RuntimeException('Documento pendente não encontrado.');
        self::audit($adminUserId,'document.reviewed','document',$documentId,['decision'=>$decision]);
    }

    public static function documents(int $userId): array
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT * FROM tp_documents WHERE professional_id=? ORDER BY type');
        $st->execute([$pid]);
        return $st->fetchAll();
    }

    public static function reputation(int $userId): array
    {
        $profile=self::professionalProfile($userId);
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT * FROM tp_reputation_events WHERE professional_id=? ORDER BY occurred_at DESC LIMIT 20');
        $st->execute([$pid]);
        return ['profile'=>$profile,'events'=>$st->fetchAll()];
    }



    private static function normalizedRegistrationAccount(array $data): array
    {
        $name=trim((string)($data['name']??''));
        $email=mb_strtolower(trim((string)($data['email']??'')));
        $phone=trim((string)($data['phone']??''));
        $password=(string)($data['password']??'');
        $confirm=(string)($data['password_confirm']??'');
        if(mb_strlen($name)<3) throw new InvalidArgumentException('Informe seu nome completo.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
        if(strlen($password)<8) throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        if($password!==$confirm) throw new InvalidArgumentException('As senhas não coincidem.');
        $st=Database::connection()->prepare('SELECT id FROM tp_users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        if($st->fetchColumn()) throw new RuntimeException('Já existe uma conta com este e-mail.');
        return ['name'=>$name,'email'=>$email,'phone'=>$phone,'password'=>$password];
    }

    public static function registerCompany(array $data): int
    {
        $account=self::normalizedRegistrationAccount($data);
        $legal=trim((string)($data['legal_name']??''));
        $trade=trim((string)($data['trade_name']??''));
        $cnpj=trim((string)($data['cnpj']??''));
        $address=trim((string)($data['address']??''));
        $city=trim((string)($data['city']??''));
        $state=mb_strtoupper(trim((string)($data['state']??'')));
        if($legal===''||$trade===''||$cnpj===''||$address===''||$city===''||strlen($state)!==2) throw new InvalidArgumentException('Preencha todos os dados obrigatórios da empresa.');
        $pdo=Database::connection(); $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('INSERT INTO tp_users (name,email,password_hash,role,phone,status,created_at) VALUES (?,?,?,?,?,"active",NOW())');
            $st->execute([$account['name'],$account['email'],password_hash($account['password'],PASSWORD_DEFAULT),'company',$account['phone']]);
            $userId=(int)$pdo->lastInsertId();
            $st=$pdo->prepare('INSERT INTO tp_companies (legal_name,trade_name,cnpj,address,city,state,status,created_at) VALUES (?,?,?,?,?,?,"pending",NOW())');
            $st->execute([$legal,$trade,$cnpj,$address,$city,$state]);
            $companyId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO tp_company_members (company_id,user_id,member_role,created_at) VALUES (?,?,"owner",NOW())')->execute([$companyId,$userId]);
            self::audit($userId,'registration.company','company',$companyId);
            $pdo->commit(); return $userId;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function registerProfessional(array $data): int
    {
        $account=self::normalizedRegistrationAccount($data);
        $cpf=trim((string)($data['cpf']??''));
        $headline=trim((string)($data['headline']??''));
        $city=trim((string)($data['city']??''));
        $state=mb_strtoupper(trim((string)($data['state']??'')));
        $categories=array_values(array_filter(array_map('intval',(array)($data['categories']??[]))));
        if($cpf===''||$headline===''||$city===''||strlen($state)!==2) throw new InvalidArgumentException('Preencha CPF, atividade principal, cidade e UF.');
        if(!$categories) throw new InvalidArgumentException('Selecione pelo menos uma categoria de trabalho.');
        $pdo=Database::connection(); $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('INSERT INTO tp_users (name,email,password_hash,role,phone,status,created_at) VALUES (?,?,?,?,?,"active",NOW())');
            $st->execute([$account['name'],$account['email'],password_hash($account['password'],PASSWORD_DEFAULT),'professional',$account['phone']]);
            $userId=(int)$pdo->lastInsertId();
            $st=$pdo->prepare('INSERT INTO tp_professionals (user_id,cpf,headline,city,state,status,created_at) VALUES (?,?,?,?,?,"pending",NOW())');
            $st->execute([$userId,$cpf,$headline,$city,$state]);
            $professionalId=(int)$pdo->lastInsertId();
            $cat=$pdo->prepare('INSERT IGNORE INTO tp_professional_categories (professional_id,category_id,experience_level) SELECT ?,id,"initial" FROM tp_job_categories WHERE id=? AND active=1');
            foreach($categories as $categoryId)$cat->execute([$professionalId,$categoryId]);
            self::audit($userId,'registration.professional','professional',$professionalId);
            $pdo->commit(); return $userId;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function adminPendingVerifications(): array
    {
        $pdo=Database::connection();
        $companies=$pdo->query("SELECT c.*,u.id owner_user_id,u.name owner_name,u.email owner_email
                                FROM tp_companies c
                                LEFT JOIN tp_company_members cm ON cm.company_id=c.id AND cm.member_role='owner'
                                LEFT JOIN tp_users u ON u.id=cm.user_id
                                WHERE c.status='pending'
                                ORDER BY c.created_at ASC")->fetchAll();

        foreach($companies as &$company){
            $summary=null;
            if(!empty($company['owner_user_id'])){
                try{$summary=self::companyVerificationSummary((int)$company['owner_user_id']);}catch(Throwable $e){$summary=null;}
            }
            $checks=[
                ['label'=>'WhatsApp do responsável','ok'=>(bool)($summary['phone_verified']??false)],
                ['label'=>'Dados cadastrais','ok'=>(bool)($summary['data_complete']??false)],
            ];
            foreach(($summary['required_documents']??self::companyVerificationDocumentTypes()) as $type=>$label){
                $checks[]=['label'=>$label,'ok'=>(($summary['documents'][$type]['status']??'')==='verified')];
            }
            $company['verification_kind']='Cadastro empresarial';
            $company['verification_purpose']='Liberação da empresa para publicar vagas';
            $company['verification_checks']=$checks;
            $company['ready_for_final']=(bool)($summary['ready_for_final']??false);
        }
        unset($company);

        $professionals=$pdo->query("SELECT p.*,u.id user_id,u.name,u.email,u.phone
                                    FROM tp_professionals p
                                    JOIN tp_users u ON u.id=p.user_id
                                    WHERE p.status='pending'
                                      AND EXISTS (SELECT 1 FROM tp_documents d WHERE d.professional_id=p.id AND d.type='identity')
                                    ORDER BY p.created_at ASC")->fetchAll();

        foreach($professionals as &$professional){
            $state=self::professionalOnboardingState((int)$professional['user_id']);
            $professional['verification_kind']='Cadastro profissional';
            $professional['verification_purpose']='Liberação final para confirmação automática e realização de turnos';
            $professional['verification_checks']=[
                ['label'=>'WhatsApp','ok'=>$state['phone_verified']],
                ['label'=>'Cadastro para candidatura','ok'=>$state['application_ready']],
                ['label'=>'Identidade e endereço','ok'=>$state['verification_data_complete']],
                ['label'=>'Documento de identidade','ok'=>$state['identity_verified']],
            ];
            $professional['ready_for_final']=$state['verification_data_complete']&&$state['identity_verified'];
        }
        unset($professional);

        return ['companies'=>$companies,'professionals'=>$professionals];
    }

    public static function adminCompanyVerificationDetail(int $companyId): array
    {
        self::ensureCompanyVerificationSchema();
        $pdo=Database::connection();

        $st=$pdo->prepare("SELECT c.*,u.id owner_user_id,u.name owner_name,u.email owner_email,u.phone owner_phone,u.phone_verified_at
                           FROM tp_companies c
                           LEFT JOIN tp_company_members cm ON cm.company_id=c.id AND cm.member_role='owner'
                           LEFT JOIN tp_users u ON u.id=cm.user_id
                           WHERE c.id=? LIMIT 1");
        $st->execute([$companyId]);
        $company=$st->fetch();
        if(!$company) throw new RuntimeException('Empresa não encontrada.');

        $summary=null;
        if(!empty($company['owner_user_id'])){
            $summary=self::companyVerificationSummary((int)$company['owner_user_id']);
        }

        $docs=$pdo->prepare("SELECT * FROM tp_company_documents WHERE company_id=? ORDER BY created_at DESC,id DESC");
        $docs->execute([$companyId]);

        return [
            'kind'=>'company',
            'company'=>$company,
            'summary'=>$summary,
            'documents'=>$docs->fetchAll(),
            'ready_for_final'=>(bool)($summary['ready_for_final']??false),
        ];
    }
    public static function adminProfessionalVerificationDetail(int $professionalId): array
    {
        self::ensureDocumentAutomationSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT p.*,u.name,u.email,u.phone,u.phone_verified_at,u.avatar_url
                           FROM tp_professionals p
                           JOIN tp_users u ON u.id=p.user_id
                           WHERE p.id=? LIMIT 1");
        $st->execute([$professionalId]);
        $professional=$st->fetch();
        if(!$professional) throw new RuntimeException('Profissional não encontrado.');

        $cats=$pdo->prepare("SELECT jc.id,jc.name
                             FROM tp_professional_categories pc
                             JOIN tp_job_categories jc ON jc.id=pc.category_id
                             WHERE pc.professional_id=?
                             ORDER BY jc.name");
        $cats->execute([$professionalId]);

        $docs=$pdo->prepare("SELECT * FROM tp_documents WHERE professional_id=? ORDER BY created_at DESC,id DESC");
        $docs->execute([$professionalId]);
        $documents=$docs->fetchAll();

        $latest=[];
        foreach($documents as $doc){
            $type=(string)$doc['type'];
            if(!isset($latest[$type])) $latest[$type]=$doc;
        }

        $state=self::professionalOnboardingState((int)$professional['user_id']);

        return [
            'kind'=>'professional',
            'professional'=>$professional,
            'categories'=>$cats->fetchAll(),
            'documents'=>$documents,
            'latest_documents'=>$latest,
            'onboarding'=>$state,
            'ready_for_final'=>$state['verification_data_complete']&&$state['identity_verified'],
        ];
    }

    public static function adminUpdateVerificationField(int $adminUserId,string $kind,int $entityId,string $field,string $value): array
    {
        $pdo=Database::connection();
        $value=trim($value);

        if($kind==='company'){
            self::ensureCompanyEmailColumn();
            $companyFields=[
                'trade_name'=>['table'=>'company','column'=>'trade_name','label'=>'Nome fantasia'],
                'legal_name'=>['table'=>'company','column'=>'legal_name','label'=>'Razão social'],
                'cnpj'=>['table'=>'company','column'=>'cnpj','label'=>'CNPJ'],
                'responsible_cpf'=>['table'=>'company','column'=>'responsible_cpf','label'=>'CPF do responsável'],
                'company_email'=>['table'=>'company','column'=>'company_email','label'=>'E-mail da empresa'],
                'address'=>['table'=>'company','column'=>'address','label'=>'Endereço'],
                'postal_code'=>['table'=>'company','column'=>'postal_code','label'=>'CEP'],
                'city'=>['table'=>'company','column'=>'city','label'=>'Cidade'],
                'state'=>['table'=>'company','column'=>'state','label'=>'UF'],
                'pix_key_type'=>['table'=>'company','column'=>'pix_key_type','label'=>'Tipo da chave Pix'],
                'pix_key'=>['table'=>'company','column'=>'pix_key','label'=>'Chave Pix'],
                'pix_holder_name'=>['table'=>'company','column'=>'pix_holder_name','label'=>'Titular Pix'],
                'pix_holder_document'=>['table'=>'company','column'=>'pix_holder_document','label'=>'Documento titular Pix'],
                'maps_url'=>['table'=>'company','column'=>'maps_url','label'=>'Google Maps'],
                'owner_name'=>['table'=>'user','column'=>'name','label'=>'Responsável'],
                'owner_email'=>['table'=>'user','column'=>'email','label'=>'E-mail do responsável'],
                'owner_phone'=>['table'=>'user','column'=>'phone','label'=>'WhatsApp'],
            ];
            if(!isset($companyFields[$field])) throw new InvalidArgumentException('Campo não permitido.');
            $meta=$companyFields[$field];

            $owner=$pdo->prepare("SELECT u.id,u.phone FROM tp_company_members cm JOIN tp_users u ON u.id=cm.user_id WHERE cm.company_id=? ORDER BY (cm.member_role='owner') DESC,cm.id ASC LIMIT 1");
            $owner->execute([$entityId]);
            $ownerRow=$owner->fetch();
            if(!$ownerRow) throw new RuntimeException('Responsável da empresa não encontrado.');

            if(in_array($field,['company_email','owner_email'],true) && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
            if($field==='state'){
                $value=mb_strtoupper($value);
                if(strlen($value)!==2) throw new InvalidArgumentException('Informe a UF com 2 letras.');
            }
            if($field==='postal_code'){
                $digits=preg_replace('/\D+/','',$value);
                if(strlen($digits)!==8) throw new InvalidArgumentException('Informe um CEP válido.');
                $value=$digits;
            }
            if($field==='owner_email'){
                $dup=$pdo->prepare('SELECT id FROM tp_users WHERE email=? AND id<>? LIMIT 1');
                $dup->execute([mb_strtolower($value),(int)$ownerRow['id']]);
                if($dup->fetchColumn()) throw new RuntimeException('Este e-mail já está em uso.');
                $value=mb_strtolower($value);
            }

            if($meta['table']==='user'){
                if($field==='owner_phone' && $value!==(string)$ownerRow['phone']){
                    $pdo->prepare('UPDATE tp_users SET phone=?,phone_verified_at=NULL,updated_at=NOW() WHERE id=?')->execute([$value,(int)$ownerRow['id']]);
                }else{
                    $pdo->prepare('UPDATE tp_users SET '.$meta['column'].'=?,updated_at=NOW() WHERE id=?')->execute([$value,(int)$ownerRow['id']]);
                }
            }else{
                $pdo->prepare('UPDATE tp_companies SET '.$meta['column'].'=? WHERE id=?')->execute([$value,$entityId]);
            }
            self::audit($adminUserId,'verification.field_updated','company',$entityId,['field'=>$field,'label'=>$meta['label']]);
            return ['field'=>$field,'value'=>$value,'label'=>$meta['label']];
        }

        if($kind==='professional'){
            $fields=[
                'name'=>['table'=>'user','column'=>'name','label'=>'Nome'],
                'email'=>['table'=>'user','column'=>'email','label'=>'E-mail'],
                'phone'=>['table'=>'user','column'=>'phone','label'=>'WhatsApp'],
                'cpf'=>['table'=>'professional','column'=>'cpf','label'=>'CPF'],
                'rg'=>['table'=>'professional','column'=>'rg','label'=>'RG/CIN'],
                'birth_date'=>['table'=>'professional','column'=>'birth_date','label'=>'Data de nascimento'],
                'headline'=>['table'=>'professional','column'=>'headline','label'=>'Atividade principal'],
                'address'=>['table'=>'professional','column'=>'address','label'=>'Endereço'],
                'postal_code'=>['table'=>'professional','column'=>'postal_code','label'=>'CEP'],
                'city'=>['table'=>'professional','column'=>'city','label'=>'Cidade'],
                'state'=>['table'=>'professional','column'=>'state','label'=>'UF'],
                'pix_key_type'=>['table'=>'professional','column'=>'pix_key_type','label'=>'Tipo da chave Pix'],
                'pix_key'=>['table'=>'professional','column'=>'pix_key','label'=>'Chave Pix'],
                'pix_holder_name'=>['table'=>'professional','column'=>'pix_holder_name','label'=>'Titular Pix'],
                'pix_holder_document'=>['table'=>'professional','column'=>'pix_holder_document','label'=>'Documento titular Pix'],
            ];
            if(!isset($fields[$field])) throw new InvalidArgumentException('Campo não permitido.');
            $meta=$fields[$field];
            $st=$pdo->prepare('SELECT p.user_id,u.phone FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.id=? LIMIT 1');
            $st->execute([$entityId]);
            $row=$st->fetch();
            if(!$row) throw new RuntimeException('Profissional não encontrado.');

            if($field==='email'){
                if(!filter_var($value,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
                $dup=$pdo->prepare('SELECT id FROM tp_users WHERE email=? AND id<>? LIMIT 1');
                $dup->execute([mb_strtolower($value),(int)$row['user_id']]);
                if($dup->fetchColumn()) throw new RuntimeException('Este e-mail já está em uso.');
                $value=mb_strtolower($value);
            }
            if($field==='state'){
                $value=mb_strtoupper($value);
                if(strlen($value)!==2) throw new InvalidArgumentException('Informe a UF com 2 letras.');
            }
            if($field==='postal_code'){
                $digits=preg_replace('/\D+/','',$value);
                if(strlen($digits)!==8) throw new InvalidArgumentException('Informe um CEP válido.');
                $value=$digits;
            }
            if($field==='birth_date'){
                $date=DateTime::createFromFormat('Y-m-d',$value);
                if(!$date || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Data inválida.');
            }

            if($meta['table']==='user'){
                if($field==='phone' && $value!==(string)$row['phone']){
                    $pdo->prepare('UPDATE tp_users SET phone=?,phone_verified_at=NULL,updated_at=NOW() WHERE id=?')->execute([$value,(int)$row['user_id']]);
                }else{
                    $pdo->prepare('UPDATE tp_users SET '.$meta['column'].'=?,updated_at=NOW() WHERE id=?')->execute([$value,(int)$row['user_id']]);
                }
            }else{
                $pdo->prepare('UPDATE tp_professionals SET '.$meta['column'].'=? WHERE id=?')->execute([$value,$entityId]);
            }
            self::audit($adminUserId,'verification.field_updated','professional',$entityId,['field'=>$field,'label'=>$meta['label']]);
            return ['field'=>$field,'value'=>$value,'label'=>$meta['label']];
        }

        throw new InvalidArgumentException('Tipo de cadastro inválido.');
    }

    public static function auditActionLabel(string $action,?string $metadataJson=null): string
    {
        $labels=[
            'verification.company'=>'Cadastro empresarial revisado',
            'verification.professional'=>'Cadastro profissional revisado',
            'verification.professional_auto'=>'Cadastro profissional aprovado automaticamente',
            'verification.company_document_reviewed'=>'Documento da empresa revisado',
            'verification.company_document_uploaded_by_admin'=>'Documento da empresa anexado pelo administrador',
            'verification.professional_document_uploaded_by_admin'=>'Documento do profissional anexado pelo administrador',
            'document.reviewed'=>'Documento do profissional revisado',
            'document.uploaded'=>'Documento enviado pelo profissional',
            'document.auto_verified'=>'Documento aprovado automaticamente',
            'document.auto_review_required'=>'Documento encaminhado para revisão manual',
            'document.auto_analysis_failed'=>'Análise automática do documento não pôde ser concluída',
            'account.company_updated'=>'Dados da empresa atualizados',
            'account.professional_updated'=>'Dados do profissional atualizados',
            'account.admin_updated'=>'Dados do administrador atualizados',
            'verification.field_updated'=>'Dado cadastral corrigido pelo administrador',
            'assignment.checkin'=>'Check-in realizado',
            'assignment.checkout'=>'Check-out realizado',
            'shift.applied'=>'Profissional se candidatou a uma vaga',
            'shift.applied_pending_verification'=>'Candidatura enviada enquanto o cadastro estava em análise',
            'shift.accepted'=>'Turno confirmado',
            'support.ticket_created'=>'Chamado de suporte aberto',
            'support.message_created'=>'Mensagem enviada no suporte',
            'support.status_changed'=>'Status do chamado de suporte alterado',
            'reputation.appealed'=>'Contestação de reputação enviada',
            'reputation.appeal_resolved'=>'Contestação de reputação revisada',
        ];
        $label=$labels[$action]??null;
        if($label!==null){
            if($action==='verification.field_updated' && $metadataJson){
                $meta=json_decode($metadataJson,true);
                if(is_array($meta) && !empty($meta['label'])) return $meta['label'].' corrigido pelo administrador';
            }
            return $label;
        }
        return ucfirst(str_replace(['.','_'],[' › ',' '],$action));
    }

    public static function auditEntityLabel(?string $entityType): string
    {
        return match($entityType){
            'company'=>'empresa',
            'professional'=>'profissional',
            'document'=>'documento',
            'company_document'=>'documento empresarial',
            'assignment'=>'turno',
            'shift'=>'vaga',
            'support_ticket'=>'chamado',
            'reputation_event'=>'ocorrência',
            'user'=>'usuário',
            default=>$entityType?:'evento',
        };
    }

    public static function setCompanyVerification(int $adminUserId,int $companyId,string $decision): void
    {
        if(!in_array($decision,['verified','rejected'],true))throw new InvalidArgumentException('Decisão inválida.');
        if($decision==='verified'){
            self::ensureCompanyVerificationSchema();
            $owner=Database::connection()->prepare("SELECT user_id FROM tp_company_members WHERE company_id=? ORDER BY (member_role='owner') DESC,id ASC LIMIT 1");
            $owner->execute([$companyId]);
            $ownerUserId=(int)$owner->fetchColumn();
            if(!$ownerUserId) throw new RuntimeException('A empresa não possui responsável vinculado.');
            $summary=self::companyVerificationSummary($ownerUserId);
            if(!$summary['ready_for_final']){
                throw new RuntimeException('A empresa ainda possui etapas pendentes: WhatsApp, dados cadastrais ou documentos obrigatórios.');
            }
        }
        $st=Database::connection()->prepare('UPDATE tp_companies SET status=? WHERE id=? AND status="pending"');
        $st->execute([$decision,$companyId]);
        if($st->rowCount()===0)throw new RuntimeException('Empresa pendente não encontrada.');
        self::audit($adminUserId,'verification.company','company',$companyId,['decision'=>$decision]);
    }
    public static function setProfessionalVerification(int $adminUserId,int $professionalId,string $decision): void
    {
        if(!in_array($decision,['verified','rejected'],true)) throw new InvalidArgumentException('Decisão inválida.');

        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT user_id,status FROM tp_professionals WHERE id=? LIMIT 1');
        $st->execute([$professionalId]);
        $professional=$st->fetch();
        if(!$professional || $professional['status']!=='pending') throw new RuntimeException('Profissional pendente não encontrado.');

        if($decision==='verified'){
            $state=self::professionalOnboardingState((int)$professional['user_id']);
            if(!$state['verification_data_complete']) throw new RuntimeException('O profissional ainda não concluiu os dados de identidade e endereço necessários para trabalhar.');
            if(!$state['identity_verified']) throw new RuntimeException('Verifique o documento de identidade antes de liberar o profissional.');
        }

        $pdo->prepare('UPDATE tp_professionals SET status=? WHERE id=? AND status="pending"')->execute([$decision,$professionalId]);
        self::audit($adminUserId,'verification.professional','professional',$professionalId,['decision'=>$decision]);
    }

    public static function companyReviews(int $userId): array
    {
        $companyId=self::companyIdForUser($userId);
        $sql="SELECT a.id assignment_id,a.agreed_value,a.checkout_at,s.id shift_id,s.starts_at,s.title,c.name category_name,
                     p.id professional_id,p.rating professional_rating,p.reliability_score,p.attendance_score,p.punctuality_score,
                     u.id professional_user_id,u.name professional_name,
                     r.id review_id,r.rating review_rating,r.attendance_rating,r.punctuality_rating,r.comment review_comment,r.created_at review_created_at
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_job_categories c ON c.id=s.category_id
              JOIN tp_professionals p ON p.id=a.professional_id
              JOIN tp_users u ON u.id=p.user_id
              LEFT JOIN tp_reviews r ON r.assignment_id=a.id AND r.reviewer_user_id=?
              WHERE s.company_id=? AND a.status='completed'
              ORDER BY COALESCE(a.checkout_at,s.ends_at) DESC";
        $st=Database::connection()->prepare($sql);
        $st->execute([$userId,$companyId]);
        return $st->fetchAll();
    }

    private static function normalizedReview(array $data, bool $withOperationalRatings=false): array
    {
        $rating=(int)($data['rating']??0);
        if($rating<1 || $rating>5) throw new InvalidArgumentException('Selecione uma avaliação de 1 a 5.');
        $result=[
            'rating'=>$rating,
            'comment'=>mb_substr(trim((string)($data['comment']??'')),0,1500),
            'attendance_rating'=>null,
            'punctuality_rating'=>null
        ];
        if($withOperationalRatings){
            $attendance=(int)($data['attendance_rating']??0);
            $punctuality=(int)($data['punctuality_rating']??0);
            if($attendance<1 || $attendance>5 || $punctuality<1 || $punctuality>5){
                throw new InvalidArgumentException('Avalie presença e pontualidade de 1 a 5.');
            }
            $result['attendance_rating']=$attendance;
            $result['punctuality_rating']=$punctuality;
        }
        return $result;
    }

    public static function createCompanyReview(int $userId, int $assignmentId, array $data): void
    {
        $companyId=self::companyIdForUser($userId);
        $review=self::normalizedReview($data,true);
        $pdo=Database::connection();

        $sql="SELECT a.id,p.id professional_id,p.user_id professional_user_id
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_professionals p ON p.id=a.professional_id
              WHERE a.id=? AND s.company_id=? AND a.status='completed' LIMIT 1";
        $st=$pdo->prepare($sql);
        $st->execute([$assignmentId,$companyId]);
        $assignment=$st->fetch();
        if(!$assignment) throw new RuntimeException('Turno concluído não encontrado.');

        $st=$pdo->prepare('SELECT id FROM tp_reviews WHERE assignment_id=? AND reviewer_user_id=?');
        $st->execute([$assignmentId,$userId]);
        if($st->fetchColumn()) throw new RuntimeException('Este turno já foi avaliado.');

        $pdo->prepare('INSERT INTO tp_reviews (assignment_id,reviewer_user_id,reviewee_user_id,rating,attendance_rating,punctuality_rating,comment,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([$assignmentId,$userId,$assignment['professional_user_id'],$review['rating'],$review['attendance_rating'],$review['punctuality_rating'],$review['comment']]);

        $avg=$pdo->prepare('SELECT AVG(rating) FROM tp_reviews WHERE reviewee_user_id=?');
        $avg->execute([$assignment['professional_user_id']]);
        $rating=(float)$avg->fetchColumn();
        $pdo->prepare('UPDATE tp_professionals SET rating=? WHERE id=?')->execute([$rating,$assignment['professional_id']]);

        self::audit($userId,'review.company_to_professional','assignment',$assignmentId,['rating'=>$review['rating']]);
    }

    public static function professionalReviewForAssignment(int $userId, int $assignmentId): ?array
    {
        $pid=self::professionalIdForUser($userId);
        $sql="SELECT a.id assignment_id,a.status,co.id company_id,co.trade_name,
                     (SELECT MIN(cm.user_id) FROM tp_company_members cm WHERE cm.company_id=co.id) company_user_id,
                     r.id review_id,r.rating,r.comment,r.created_at
              FROM tp_assignments a
              JOIN tp_shifts s ON s.id=a.shift_id
              JOIN tp_companies co ON co.id=s.company_id
              LEFT JOIN tp_reviews r ON r.assignment_id=a.id AND r.reviewer_user_id=?
              WHERE a.id=? AND a.professional_id=? LIMIT 1";
        $st=Database::connection()->prepare($sql);
        $st->execute([$userId,$assignmentId,$pid]);
        return $st->fetch() ?: null;
    }

    public static function createProfessionalReview(int $userId, int $assignmentId, array $data): void
    {
        $review=self::normalizedReview($data,false);
        $info=self::professionalReviewForAssignment($userId,$assignmentId);
        if(!$info || $info['status']!=='completed') throw new RuntimeException('Somente turnos concluídos podem ser avaliados.');
        if(!empty($info['review_id'])) throw new RuntimeException('Este turno já foi avaliado.');
        if(empty($info['company_user_id'])) throw new RuntimeException('Não foi possível identificar o responsável da empresa.');

        $pdo=Database::connection();
        $pdo->prepare('INSERT INTO tp_reviews (assignment_id,reviewer_user_id,reviewee_user_id,rating,comment,created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([$assignmentId,$userId,$info['company_user_id'],$review['rating'],$review['comment']]);

        $avg=$pdo->prepare('SELECT AVG(r.rating) FROM tp_reviews r JOIN tp_company_members cm ON cm.user_id=r.reviewee_user_id WHERE cm.company_id=?');
        $avg->execute([$info['company_id']]);
        $rating=(float)$avg->fetchColumn();
        if($rating>0) $pdo->prepare('UPDATE tp_companies SET rating=? WHERE id=?')->execute([$rating,$info['company_id']]);

        self::audit($userId,'review.professional_to_company','assignment',$assignmentId,['rating'=>$review['rating']]);
    }

    public static function cancelProfessionalAssignment(int $userId, int $assignmentId, string $reason=''): void
    {
        $a=self::assignment($userId,$assignmentId);
        if(!$a) throw new RuntimeException('Turno não encontrado.');
        if($a['status']!=='confirmed') throw new RuntimeException('Somente turnos confirmados e ainda não iniciados podem ser cancelados por aqui.');

        $seconds=strtotime($a['starts_at'])-time();
        if($seconds<=0) throw new RuntimeException('O turno já começou. Procure o suporte.');
        $hours=$seconds/3600;

        $points=0.0;
        $severity='info';
        $description='Cancelamento com antecedência superior a 48 horas.';
        if($hours<6){
            $points=-10;
            $severity='critical';
            $description='Cancelamento com menos de 6 horas de antecedência.';
        } elseif($hours<24){
            $points=-5;
            $severity='warning';
            $description='Cancelamento entre 6 e 24 horas de antecedência.';
        } elseif($hours<48){
            $points=-2;
            $severity='warning';
            $description='Cancelamento entre 24 e 48 horas de antecedência.';
        }

        $reason=mb_substr(trim($reason),0,500);
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE tp_assignments SET status="cancelled",cancellation_reason=? WHERE id=?')->execute([$reason?:$description,$assignmentId]);
            $pdo->prepare('UPDATE tp_shift_applications SET status="cancelled" WHERE shift_id=? AND professional_id=?')->execute([$a['shift_id'],$a['professional_id']]);
            $pdo->prepare('INSERT INTO tp_reputation_events (professional_id,assignment_id,event_type,severity,points_delta,description,occurred_at) VALUES (?,?,"professional_cancellation",?,?,?,NOW())')
                ->execute([$a['professional_id'],$assignmentId,$severity,$points,$description]);

            if($points!==0.0){
                $pdo->prepare('UPDATE tp_professionals SET reliability_score=GREATEST(0,LEAST(100,reliability_score+?)) WHERE id=?')
                    ->execute([$points,$a['professional_id']]);
            }

            $st=$pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status<>'cancelled'");
            $st->execute([$a['shift_id']]);
            $count=(int)$st->fetchColumn();
            $shift=self::shift((int)$a['shift_id']);
            if($shift && $shift['status']!=='cancelled'){
                $newStatus=$count>=(int)$shift['required_workers']?'confirmed':($count>0?'filling':'published');
                $pdo->prepare('UPDATE tp_shifts SET status=? WHERE id=?')->execute([$newStatus,$a['shift_id']]);
            }

            self::audit($userId,'assignment.cancelled_by_professional','assignment',$assignmentId,['hours_before'=>round($hours,1),'points'=>$points]);
            $pdo->commit();
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function appealReputationEvent(int $userId, int $eventId): void
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT * FROM tp_reputation_events WHERE id=? AND professional_id=? LIMIT 1');
        $st->execute([$eventId,$pid]);
        $event=$st->fetch();
        if(!$event) throw new RuntimeException('Ocorrência não encontrada.');
        if((float)$event['points_delta']>=0) throw new RuntimeException('Somente ocorrências com impacto negativo podem ser contestadas.');
        if(!empty($event['appeal_status'])) throw new RuntimeException('Esta ocorrência já possui uma contestação.');

        Database::connection()->prepare('UPDATE tp_reputation_events SET appealed_at=NOW(),appeal_status="pending" WHERE id=?')->execute([$eventId]);
        self::audit($userId,'reputation.appealed','reputation_event',$eventId);
    }

    public static function adminPendingAppeals(): array
    {
        $sql="SELECT e.*,u.name professional_name,p.reliability_score,a.shift_id,s.title shift_title,s.starts_at
              FROM tp_reputation_events e
              JOIN tp_professionals p ON p.id=e.professional_id
              JOIN tp_users u ON u.id=p.user_id
              LEFT JOIN tp_assignments a ON a.id=e.assignment_id
              LEFT JOIN tp_shifts s ON s.id=a.shift_id
              WHERE e.appeal_status='pending'
              ORDER BY e.appealed_at ASC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function adminAuditLogs(int $limit=30): array
    {
        $sql="SELECT l.*,u.name user_name,u.role user_role
              FROM tp_audit_logs l
              LEFT JOIN tp_users u ON u.id=l.user_id
              ORDER BY l.created_at DESC LIMIT ".max(1,(int)$limit);
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function resolveReputationAppeal(int $adminUserId, int $eventId, string $decision): void
    {
        if(!in_array($decision,['accepted','rejected'],true)) throw new InvalidArgumentException('Decisão inválida.');
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT * FROM tp_reputation_events WHERE id=? AND appeal_status="pending" FOR UPDATE');
            $st->execute([$eventId]);
            $event=$st->fetch();
            if(!$event) throw new RuntimeException('Contestação pendente não encontrada.');

            if($decision==='accepted' && (float)$event['points_delta']<0){
                $restore=abs((float)$event['points_delta']);
                $pdo->prepare('UPDATE tp_professionals SET reliability_score=LEAST(100,reliability_score+?) WHERE id=?')->execute([$restore,$event['professional_id']]);
            }

            $pdo->prepare('UPDATE tp_reputation_events SET appeal_status=? WHERE id=?')->execute([$decision,$eventId]);
            self::audit($adminUserId,'reputation.appeal_resolved','reputation_event',$eventId,['decision'=>$decision]);
            $pdo->commit();
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function ensureSupportSchema(): void
    {
        static $ready=false;
        if($ready) return;
        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            requester_user_id BIGINT UNSIGNED NOT NULL,
            segment VARCHAR(30) NOT NULL,
            category VARCHAR(60) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            context_ref VARCHAR(190) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_support_ticket_requester FOREIGN KEY (requester_user_id) REFERENCES tp_users(id) ON DELETE CASCADE,
            INDEX idx_support_requester (requester_user_id,status,last_message_at),
            INDEX idx_support_segment_status (segment,status,last_message_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_support_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            author_role VARCHAR(30) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_id) REFERENCES tp_support_tickets(id) ON DELETE CASCADE,
            CONSTRAINT fk_support_message_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE SET NULL,
            INDEX idx_support_message_ticket (ticket_id,created_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ready=true;
    }

    public static function supportCategories(string $role): array
    {
        if($role==='company'){
            return [
                'registration'=>'Cadastro e verificação da empresa',
                'shift'=>'Publicação ou alteração de vaga',
                'professionals'=>'Candidatos e profissionais',
                'schedule'=>'Escalas, presença e turnos',
                'finance'=>'Financeiro, pagamentos e cobranças',
                'refund'=>'Reembolso ou devolução',
                'security'=>'Segurança da conta',
                'other'=>'Outro assunto',
            ];
        }
        if($role==='professional'){
            return [
                'registration'=>'Conta, cadastro e verificação',
                'documents'=>'Documentos e identidade',
                'opportunity'=>'Candidatura ou oportunidade',
                'shift'=>'Turno, escala, presença ou cancelamento',
                'payment'=>'Ganhos e pagamentos',
                'reputation'=>'Reputação e avaliações',
                'security'=>'Segurança da conta',
                'other'=>'Outro assunto',
            ];
        }
        return [];
    }

    public static function supportDashboard(int $userId,string $role): array
    {
        self::ensureSupportSchema();
        $pdo=Database::connection();

        $baseSelect="SELECT t.*,u.name requester_name,u.email requester_email,
                    (SELECT c.trade_name FROM tp_company_members cm JOIN tp_companies c ON c.id=cm.company_id WHERE cm.user_id=t.requester_user_id LIMIT 1) company_name,
                    (SELECT p.headline FROM tp_professionals p WHERE p.user_id=t.requester_user_id LIMIT 1) professional_headline,
                    (SELECT COUNT(*) FROM tp_support_messages sm WHERE sm.ticket_id=t.id) message_count
             FROM tp_support_tickets t
             JOIN tp_users u ON u.id=t.requester_user_id";

        if($role==='admin'){
            $tickets=$pdo->query($baseSelect." ORDER BY FIELD(t.status,'open','in_progress','answered','closed'),t.last_message_at DESC LIMIT 100")->fetchAll();
            $stats=['open'=>0,'in_progress'=>0,'answered'=>0,'closed'=>0,'company'=>0,'professional'=>0];
            $counts=$pdo->query("SELECT status,COUNT(*) total FROM tp_support_tickets GROUP BY status")->fetchAll();
            foreach($counts as $row) if(isset($stats[$row['status']])) $stats[$row['status']]=(int)$row['total'];
            $segments=$pdo->query("SELECT segment,COUNT(*) total FROM tp_support_tickets WHERE status<>'closed' GROUP BY segment")->fetchAll();
            foreach($segments as $row) if(isset($stats[$row['segment']])) $stats[$row['segment']]=(int)$row['total'];
            return ['tickets'=>$tickets,'stats'=>$stats,'categories'=>[]];
        }

        if(!in_array($role,['company','professional'],true)) throw new RuntimeException('Segmento de suporte inválido.');
        $st=$pdo->prepare($baseSelect." WHERE t.requester_user_id=? AND t.segment=? ORDER BY t.last_message_at DESC LIMIT 60");
        $st->execute([$userId,$role]);
        $tickets=$st->fetchAll();
        $stats=['open'=>0,'in_progress'=>0,'answered'=>0,'closed'=>0];
        foreach($tickets as $ticket) if(isset($stats[$ticket['status']])) $stats[$ticket['status']]++;

        return [
            'tickets'=>$tickets,
            'stats'=>$stats,
            'categories'=>self::supportCategories($role),
        ];
    }

    public static function createSupportTicket(int $userId,string $role,array $data): int
    {
        self::ensureSupportSchema();
        if(!in_array($role,['company','professional'],true)) throw new RuntimeException('Abertura de chamado disponível apenas para empresas e profissionais.');

        $categories=self::supportCategories($role);
        $category=trim((string)($data['category']??''));
        $subject=trim((string)($data['subject']??''));
        $message=trim((string)($data['message']??''));
        $context=mb_substr(trim((string)($data['context_ref']??'')),0,190);

        if(!isset($categories[$category])) throw new InvalidArgumentException('Selecione o assunto do chamado.');
        if(mb_strlen($subject)<5 || mb_strlen($subject)>190) throw new InvalidArgumentException('Resuma o problema em um título de 5 a 190 caracteres.');
        if(mb_strlen($message)<10 || mb_strlen($message)>5000) throw new InvalidArgumentException('Descreva o problema com pelo menos 10 e no máximo 5.000 caracteres.');

        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare("INSERT INTO tp_support_tickets
                (requester_user_id,segment,category,subject,context_ref,status,last_message_at,created_at,updated_at)
                VALUES (?,?,?,?,?,'open',NOW(),NOW(),NOW())");
            $st->execute([$userId,$role,$category,$subject,$context!==''?$context:null]);
            $ticketId=(int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO tp_support_messages (ticket_id,user_id,author_role,body,created_at) VALUES (?,?,?,?,NOW())')
                ->execute([$ticketId,$userId,$role,$message]);

            self::audit($userId,'support.ticket_created','support_ticket',$ticketId,['segment'=>$role,'category'=>$category]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $admins=$pdo->query("SELECT id FROM tp_users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach($admins as $adminId){
            self::createNotification(
                (int)$adminId,
                'support_ticket',
                'Novo chamado de suporte',
                ($role==='company'?'Empresa':'Profissional').': '.$subject,
                'admin/suporte/chamados/'.$ticketId,
                'support_ticket:'.$ticketId
            );
        }

        return $ticketId;
    }

    public static function supportTicket(int $userId,string $role,int $ticketId): array
    {
        self::ensureSupportSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare("SELECT t.*,u.name requester_name,u.email requester_email,u.phone requester_phone,
                           (SELECT c.trade_name FROM tp_company_members cm JOIN tp_companies c ON c.id=cm.company_id WHERE cm.user_id=t.requester_user_id LIMIT 1) company_name,
                           (SELECT p.headline FROM tp_professionals p WHERE p.user_id=t.requester_user_id LIMIT 1) professional_headline
                    FROM tp_support_tickets t
                    JOIN tp_users u ON u.id=t.requester_user_id
                    WHERE t.id=? LIMIT 1");
        $st->execute([$ticketId]);
        $ticket=$st->fetch();
        if(!$ticket) throw new RuntimeException('Chamado não encontrado.');
        if($role!=='admin' && (int)$ticket['requester_user_id']!==$userId) throw new RuntimeException('Você não tem acesso a este chamado.');

        $messages=$pdo->prepare("SELECT sm.*,u.name author_name
                                 FROM tp_support_messages sm
                                 LEFT JOIN tp_users u ON u.id=sm.user_id
                                 WHERE sm.ticket_id=?
                                 ORDER BY sm.created_at ASC,sm.id ASC");
        $messages->execute([$ticketId]);

        return [
            'ticket'=>$ticket,
            'messages'=>$messages->fetchAll(),
            'categories'=>self::supportCategories((string)$ticket['segment']),
        ];
    }

    public static function addSupportMessage(int $userId,string $role,int $ticketId,string $body): void
    {
        self::ensureSupportSchema();
        $body=trim($body);
        if(mb_strlen($body)<2 || mb_strlen($body)>5000) throw new InvalidArgumentException('A mensagem deve ter entre 2 e 5.000 caracteres.');

        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT * FROM tp_support_tickets WHERE id=? LIMIT 1');
        $st->execute([$ticketId]);
        $ticket=$st->fetch();
        if(!$ticket) throw new RuntimeException('Chamado não encontrado.');
        if($role!=='admin' && (int)$ticket['requester_user_id']!==$userId) throw new RuntimeException('Você não tem acesso a este chamado.');
        if($role!=='admin' && $ticket['status']==='closed') throw new RuntimeException('Este chamado está encerrado. Abra um novo chamado se precisar de ajuda.');

        $newStatus=$role==='admin'?'answered':'open';
        $pdo->beginTransaction();
        try{
            $pdo->prepare('INSERT INTO tp_support_messages (ticket_id,user_id,author_role,body,created_at) VALUES (?,?,?,?,NOW())')
                ->execute([$ticketId,$userId,$role,$body]);
            $messageId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE tp_support_tickets SET status=?,last_message_at=NOW(),updated_at=NOW() WHERE id=?')
                ->execute([$newStatus,$ticketId]);
            self::audit($userId,'support.message_created','support_ticket',$ticketId,['message_id'=>$messageId,'role'=>$role]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        if($role==='admin'){
            $targetRole=(string)$ticket['segment'];
            self::createNotification(
                (int)$ticket['requester_user_id'],
                'support_reply',
                'O suporte respondeu seu chamado',
                (string)$ticket['subject'],
                ($targetRole==='company'?'empresa':'profissional').'/suporte/chamados/'.$ticketId,
                'support_reply:'.$ticketId.':'.$messageId
            );
        }else{
            $admins=$pdo->query("SELECT id FROM tp_users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach($admins as $adminId){
                self::createNotification(
                    (int)$adminId,
                    'support_reply',
                    'Nova mensagem em chamado',
                    (string)$ticket['subject'],
                    'admin/suporte/chamados/'.$ticketId,
                    'support_user_reply:'.$ticketId.':'.$messageId.':'.$adminId
                );
            }
        }
    }

    public static function setSupportTicketStatus(int $adminUserId,int $ticketId,string $status): void
    {
        self::ensureSupportSchema();
        if(!in_array($status,['open','in_progress','answered','closed'],true)) throw new InvalidArgumentException('Status inválido.');
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT * FROM tp_support_tickets WHERE id=? LIMIT 1');
        $st->execute([$ticketId]);
        $ticket=$st->fetch();
        if(!$ticket) throw new RuntimeException('Chamado não encontrado.');

        $pdo->prepare('UPDATE tp_support_tickets SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$ticketId]);
        self::audit($adminUserId,'support.status_changed','support_ticket',$ticketId,['status'=>$status]);

        if($status==='closed'){
            $segment=(string)$ticket['segment'];
            self::createNotification(
                (int)$ticket['requester_user_id'],
                'support_reply',
                'Chamado de suporte encerrado',
                (string)$ticket['subject'],
                ($segment==='company'?'empresa':'profissional').'/suporte/chamados/'.$ticketId,
                'support_closed:'.$ticketId
            );
        }
    }

    public static function updateAdminAccount(int $userId,array $data): void
    {
        $name=trim((string)($data['name']??''));
        if(mb_strlen($name)<3) throw new InvalidArgumentException('Informe o nome do administrador.');

        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT id,password_hash FROM tp_users WHERE id=? AND role="admin" AND status="active" LIMIT 1');
        $st->execute([$userId]);
        $admin=$st->fetch();
        if(!$admin) throw new RuntimeException('Conta administrativa não encontrada.');

        $newPassword=(string)($data['new_password']??'');
        $confirm=(string)($data['new_password_confirm']??'');
        if($newPassword!==''){
            if(strlen($newPassword)<8) throw new InvalidArgumentException('A nova senha deve ter pelo menos 8 caracteres.');
            if($newPassword!==$confirm) throw new InvalidArgumentException('A confirmação da nova senha não confere.');
            $current=(string)($data['current_password']??'');
            if(!password_verify($current,(string)$admin['password_hash'])) throw new InvalidArgumentException('Senha atual incorreta.');
            $pdo->prepare('UPDATE tp_users SET name=?,password_hash=?,updated_at=NOW() WHERE id=?')->execute([$name,password_hash($newPassword,PASSWORD_DEFAULT),$userId]);
        }else{
            $pdo->prepare('UPDATE tp_users SET name=?,updated_at=NOW() WHERE id=?')->execute([$name,$userId]);
        }
        self::audit($userId,'account.admin_updated','user',$userId,['password_changed'=>$newPassword!=='']);
    }

    public static function adminStats(): array
    {
        $pdo=Database::connection();
        return [
            'users'=>(int)$pdo->query('SELECT COUNT(*) FROM tp_users')->fetchColumn(),
            'companies'=>(int)$pdo->query('SELECT COUNT(*) FROM tp_companies')->fetchColumn(),
            'professionals'=>(int)$pdo->query('SELECT COUNT(*) FROM tp_professionals')->fetchColumn(),
            'shifts'=>(int)$pdo->query('SELECT COUNT(*) FROM tp_shifts')->fetchColumn(),
            'assignments'=>(int)$pdo->query('SELECT COUNT(*) FROM tp_assignments')->fetchColumn(),
        ];
    }

    public static function createApiToken(int $userId): string
    {
        $plain=bin2hex(random_bytes(32));
        $hash=hash('sha256',$plain);
        Database::connection()->prepare('INSERT INTO tp_api_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())')->execute([$userId,$hash]);
        return $plain;
    }

    public static function apiUserFromToken(string $token): ?array
    {
        if(!$token) return null;
        $hash=hash('sha256',$token);
        $sql='SELECT u.* FROM tp_api_tokens t JOIN tp_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW() AND u.status="active" LIMIT 1';
        $st=Database::connection()->prepare($sql);
        $st->execute([$hash]);
        return $st->fetch() ?: null;
    }

    public static function revokeApiToken(string $token): void
    {
        Database::connection()->prepare('UPDATE tp_api_tokens SET revoked_at=NOW() WHERE token_hash=?')->execute([hash('sha256',$token)]);
    }
}
