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
                               WHERE pc.category_id=? AND p.status='verified' AND u.status='active'");
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
        $sql="SELECT a.*,p.id professional_id,p.headline,p.reliability_score,p.punctuality_score,p.attendance_score,p.rating,p.completed_shifts,
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
        $sql='SELECT p.*,u.name,u.email,u.avatar_url FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.user_id=? LIMIT 1';
        $s=Database::connection()->prepare($sql);
        $s->execute([$userId]);
        return $s->fetch() ?: [];
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
                AND NOT EXISTS (SELECT 1 FROM tp_assignments x WHERE x.shift_id=s.id AND x.professional_id=? AND x.status<>'cancelled')
              ORDER BY s.starts_at ASC LIMIT ".max(1,(int)$limit);
        $st=Database::connection()->prepare($sql);
        $st->execute([$pid]);
        $rows=$st->fetchAll();
        foreach($rows as &$row){
            if(!array_key_exists('acceptance_mode',$row)) $row['acceptance_mode']='automatic';
        }
        return $rows;
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
        $profile=self::professionalProfile($userId);
        if(($profile['status']??'pending')!=='verified') throw new RuntimeException('Seu perfil precisa ser verificado antes de aceitar ou se candidatar a turnos.');

        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT * FROM tp_shifts WHERE id=? FOR UPDATE');
            $st->execute([$shiftId]);
            $shift=$st->fetch();
            if(!$shift || !in_array($shift['status'],['published','filling'],true)) throw new RuntimeException('Esta vaga não está mais disponível.');
            if(strtotime($shift['starts_at'])<=time()) throw new RuntimeException('Este turno já iniciou.');

            $st=$pdo->prepare('SELECT id FROM tp_assignments WHERE shift_id=? AND professional_id=? AND status<>"cancelled" LIMIT 1');
            $st->execute([$shiftId,$pid]);
            $existing=$st->fetchColumn();
            if($existing){
                $pdo->commit();
                return ['status'=>'confirmed','assignment_id'=>(int)$existing];
            }

            $mode=$shift['acceptance_mode']??'automatic';
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
        $sql='SELECT a.*,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,s.dress_code,s.notes,jc.name category_name,co.trade_name company_name
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
        $address=trim((string)($data['address']??''));
        $mapsUrl=self::normalizeCompanyMapsUrl((string)($data['maps_url']??''));
        $city=trim((string)($data['city']??''));
        $state=mb_strtoupper(trim((string)($data['state']??'')));
        if($name===''||$legal===''||$trade===''||$cnpj===''||$address===''||$city===''||strlen($state)!==2) throw new InvalidArgumentException('Preencha todos os campos obrigatórios.');

        self::ensureCompanyMapsColumn();
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
            $pdo->prepare('UPDATE tp_companies SET legal_name=?,trade_name=?,cnpj=?,address=?,city=?,state=?,maps_url=? WHERE id=?')->execute([$legal,$trade,$cnpj,$address,$city,$state,$mapsUrl,$companyId]);
            self::audit($userId,'account.company_updated','company',$companyId);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function updateProfessionalAccount(int $userId, array $data): void
    {
        $professionalId=self::professionalIdForUser($userId);
        if(!$professionalId) throw new RuntimeException('Perfil profissional não encontrado.');
        $name=trim((string)($data['name']??''));
        $phone=trim((string)($data['phone']??''));
        $headline=trim((string)($data['headline']??''));
        $bio=trim((string)($data['bio']??''));
        $city=trim((string)($data['city']??''));
        $state=mb_strtoupper(trim((string)($data['state']??'')));
        $pix=trim((string)($data['pix_key']??''));
        $categories=array_values(array_filter(array_map('intval',(array)($data['categories']??[]))));
        if($name===''||$headline===''||$city===''||strlen($state)!==2) throw new InvalidArgumentException('Preencha nome, atividade, cidade e UF.');
        if(!$categories) throw new InvalidArgumentException('Selecione pelo menos uma categoria.');

        $pdo=Database::connection(); $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE tp_users SET name=?,phone=?,updated_at=NOW() WHERE id=?')->execute([$name,$phone,$userId]);
            $pdo->prepare('UPDATE tp_professionals SET headline=?,bio=?,city=?,state=?,pix_key=? WHERE id=?')->execute([$headline,$bio,$city,$state,$pix,$professionalId]);
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
        $companies=Database::connection()->query("SELECT c.*,u.name owner_name,u.email owner_email FROM tp_companies c LEFT JOIN tp_company_members cm ON cm.company_id=c.id AND cm.member_role='owner' LEFT JOIN tp_users u ON u.id=cm.user_id WHERE c.status='pending' ORDER BY c.created_at ASC")->fetchAll();
        $professionals=Database::connection()->query("SELECT p.*,u.name,u.email,u.phone FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.status='pending' ORDER BY p.created_at ASC")->fetchAll();
        return ['companies'=>$companies,'professionals'=>$professionals];
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
        if(!in_array($decision,['verified','rejected'],true))throw new InvalidArgumentException('Decisão inválida.');
        if($decision==='verified'){
            $st=Database::connection()->prepare("SELECT COUNT(DISTINCT type) FROM tp_documents WHERE professional_id=? AND type IN ('identity','cpf') AND status='verified'");
            $st->execute([$professionalId]);
            if((int)$st->fetchColumn()<2) throw new RuntimeException('Verifique Documento de identidade e CPF antes de liberar o profissional.');
        }
        $st=Database::connection()->prepare('UPDATE tp_professionals SET status=? WHERE id=? AND status="pending"');
        $st->execute([$decision,$professionalId]);
        if($st->rowCount()===0)throw new RuntimeException('Profissional pendente não encontrado.');
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
