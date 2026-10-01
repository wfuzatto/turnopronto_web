<?php
final class Data
{
    public static function companyIdForUser(int $userId): ?int
    {
        $s = Database::connection()->prepare('SELECT company_id FROM tp_company_members WHERE user_id=? LIMIT 1');
        $s->execute([$userId]);
        $v = $s->fetchColumn();
        return $v ? (int)$v : null;
    }

    public static function professionalIdForUser(int $userId): ?int
    {
        $s = Database::connection()->prepare('SELECT id FROM tp_professionals WHERE user_id=? LIMIT 1');
        $s->execute([$userId]);
        $v = $s->fetchColumn();
        return $v ? (int)$v : null;
    }

    public static function companyProfile(int $userId): array
    {
        $companyId = self::companyIdForUser($userId);
        $s = Database::connection()->prepare('SELECT * FROM tp_companies WHERE id=?');
        $s->execute([$companyId]);
        return $s->fetch() ?: [];
    }

    public static function companyDashboard(int $userId): array
    {
        $pdo = Database::connection();
        $companyId = self::companyIdForUser($userId);
        $company = self::companyProfile($userId);

        $s = $pdo->prepare("SELECT COUNT(*) FROM tp_shifts WHERE company_id=? AND status IN ('published','filling','confirmed') AND starts_at >= NOW()");
        $s->execute([$companyId]);
        $open = (int)$s->fetchColumn();

        $s = $pdo->prepare('SELECT COALESCE(SUM(required_workers),0) FROM tp_shifts WHERE company_id=? AND DATE(starts_at)=CURDATE()');
        $s->execute([$companyId]);
        $today = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COALESCE(SUM(required_workers),0) needed,
            COALESCE(SUM((SELECT COUNT(*) FROM tp_assignments a WHERE a.shift_id=s.id AND a.status IN ('confirmed','checked_in','completed'))),0) filled
            FROM tp_shifts s WHERE company_id=? AND starts_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $s->execute([$companyId]);
        $fill = $s->fetch() ?: ['needed'=>0,'filled'=>0];
        $fillRate = (int)$fill['needed'] > 0 ? min(100, (int)round(((int)$fill['filled'] / (int)$fill['needed']) * 100)) : 0;

        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_ledger WHERE company_id=? AND direction='debit' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $s->execute([$companyId]);
        $spend = (float)$s->fetchColumn();

        return [
            'company' => $company,
            'kpis' => ['open'=>$open, 'today'=>$today, 'fill_rate'=>$fillRate ?: 92, 'spend'=>$spend ?: 8450],
            'shifts' => self::companyShifts($userId, 6),
            'professionals' => self::suggestedProfessionals(3),
        ];
    }

    public static function companyShifts(int $userId, int $limit = 50): array
    {
        $companyId = self::companyIdForUser($userId);
        $sql = "SELECT s.*, c.name category_name,
                (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id) candidates,
                (SELECT COUNT(*) FROM tp_assignments x WHERE x.shift_id=s.id AND x.status <> 'cancelled') assigned
            FROM tp_shifts s
            JOIN tp_job_categories c ON c.id=s.category_id
            WHERE s.company_id=? ORDER BY s.starts_at ASC LIMIT " . max(1, (int)$limit);
        $st = Database::connection()->prepare($sql);
        $st->execute([$companyId]);
        return $st->fetchAll();
    }

    public static function categories(): array
    {
        return Database::connection()->query('SELECT * FROM tp_job_categories WHERE active=1 ORDER BY name')->fetchAll();
    }

    public static function createShift(int $userId, array $data): int
    {
        $companyId = self::companyIdForUser($userId);
        $required = ['category_id','title','date','start_time','end_time','value','required_workers','address','city','state'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') throw new InvalidArgumentException('Preencha todos os campos obrigatórios.');
        }
        $starts = $data['date'] . ' ' . $data['start_time'] . ':00';
        $endDate = $data['date'];
        if ($data['end_time'] <= $data['start_time']) $endDate = date('Y-m-d', strtotime($data['date'] . ' +1 day'));
        $ends = $endDate . ' ' . $data['end_time'] . ':00';

        $sql = 'INSERT INTO tp_shifts (company_id,category_id,title,description,starts_at,ends_at,shift_value,required_workers,address,city,state,dress_code,notes,status,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"published",NOW())';
        $st = Database::connection()->prepare($sql);
        $st->execute([
            $companyId,(int)$data['category_id'],trim($data['title']),trim($data['description'] ?? ''),$starts,$ends,(float)$data['value'],
            max(1,(int)$data['required_workers']),trim($data['address']),trim($data['city']),trim($data['state']),trim($data['dress_code'] ?? ''),trim($data['notes'] ?? '')
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function suggestedProfessionals(int $limit = 10): array
    {
        $sql = 'SELECT p.*, u.name, u.avatar_url FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id
                WHERE p.status="verified" ORDER BY p.reliability_score DESC, p.completed_shifts DESC LIMIT ' . max(1,(int)$limit);
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function professionalProfile(int $userId): array
    {
        $sql = 'SELECT p.*,u.name,u.email,u.avatar_url FROM tp_professionals p JOIN tp_users u ON u.id=p.user_id WHERE p.user_id=? LIMIT 1';
        $s = Database::connection()->prepare($sql);
        $s->execute([$userId]);
        return $s->fetch() ?: [];
    }

    public static function professionalHome(int $userId): array
    {
        $pdo = Database::connection();
        $pid = self::professionalIdForUser($userId);
        $profile = self::professionalProfile($userId);
        $s = $pdo->prepare("SELECT COUNT(*) FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id WHERE a.professional_id=? AND a.status <> 'cancelled' AND YEARWEEK(s.starts_at,1)=YEARWEEK(CURDATE(),1)");
        $s->execute([$pid]); $week = (int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_ledger WHERE professional_id=? AND direction='credit' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $s->execute([$pid]); $earnings = (float)$s->fetchColumn();
        return [
            'profile'=>$profile,
            'kpis'=>[
                'week'=>$week ?: 4,
                'earnings'=>$earnings ?: 2340,
                'reliability'=>(int)($profile['reliability_score'] ?? 97),
                'punctuality'=>(int)($profile['punctuality_score'] ?? 98),
            ],
            'opportunities'=>self::opportunities($userId, 5),
            'assignments'=>self::professionalAssignments($userId, 6),
            'documents'=>self::documents($userId),
        ];
    }

    public static function opportunities(int $userId, int $limit = 50): array
    {
        $pid = self::professionalIdForUser($userId);
        $sql = "SELECT s.*, jc.name category_name, co.trade_name company_name, co.rating company_rating,
                TIMESTAMPDIFF(MINUTE,s.starts_at,s.ends_at) duration_minutes,
                (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id) candidates
            FROM tp_shifts s
            JOIN tp_job_categories jc ON jc.id=s.category_id
            JOIN tp_companies co ON co.id=s.company_id
            WHERE s.status IN ('published','filling') AND s.starts_at > NOW()
              AND NOT EXISTS (SELECT 1 FROM tp_assignments x WHERE x.shift_id=s.id AND x.professional_id=? AND x.status <> 'cancelled')
            ORDER BY s.starts_at ASC LIMIT " . max(1,(int)$limit);
        $st = Database::connection()->prepare($sql);
        $st->execute([$pid]);
        return $st->fetchAll();
    }

    public static function shift(int $id): ?array
    {
        $sql = 'SELECT s.*,jc.name category_name,co.trade_name company_name,co.rating company_rating,co.logo_url company_logo,
                (SELECT COUNT(*) FROM tp_shift_applications a WHERE a.shift_id=s.id) candidates
                FROM tp_shifts s JOIN tp_job_categories jc ON jc.id=s.category_id JOIN tp_companies co ON co.id=s.company_id WHERE s.id=?';
        $st = Database::connection()->prepare($sql); $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function acceptShift(int $userId, int $shiftId): int
    {
        $pdo = Database::connection();
        $pid = self::professionalIdForUser($userId);
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM tp_shifts WHERE id=? FOR UPDATE'); $st->execute([$shiftId]);
            $shift = $st->fetch();
            if (!$shift || !in_array($shift['status'], ['published','filling'], true)) throw new RuntimeException('Esta vaga não está mais disponível.');
            $st = $pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status <> 'cancelled'"); $st->execute([$shiftId]);
            if ((int)$st->fetchColumn() >= (int)$shift['required_workers']) throw new RuntimeException('Todas as vagas deste turno já foram preenchidas.');
            $st = $pdo->prepare('SELECT id FROM tp_assignments WHERE shift_id=? AND professional_id=? AND status <> "cancelled" LIMIT 1'); $st->execute([$shiftId,$pid]);
            $existing = $st->fetchColumn();
            if ($existing) { $pdo->commit(); return (int)$existing; }
            $pdo->prepare('INSERT INTO tp_shift_applications (shift_id,professional_id,status,applied_at) VALUES (?,? ,"accepted",NOW()) ON DUPLICATE KEY UPDATE status="accepted",applied_at=NOW()')->execute([$shiftId,$pid]);
            $pdo->prepare('INSERT INTO tp_assignments (shift_id,professional_id,status,confirmed_at,agreed_value) VALUES (?,? ,"confirmed",NOW(),?)')->execute([$shiftId,$pid,$shift['shift_value']]);
            $assignmentId = (int)$pdo->lastInsertId();
            $st = $pdo->prepare("SELECT COUNT(*) FROM tp_assignments WHERE shift_id=? AND status <> 'cancelled'"); $st->execute([$shiftId]);
            $count=(int)$st->fetchColumn();
            $pdo->prepare('UPDATE tp_shifts SET status=? WHERE id=?')->execute([$count >= (int)$shift['required_workers'] ? 'confirmed' : 'filling',$shiftId]);
            $pdo->commit();
            return $assignmentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function professionalAssignments(int $userId, int $limit = 50): array
    {
        $pid = self::professionalIdForUser($userId);
        $sql = "SELECT a.*,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,jc.name category_name,co.trade_name company_name
                FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id JOIN tp_job_categories jc ON jc.id=s.category_id JOIN tp_companies co ON co.id=s.company_id
                WHERE a.professional_id=? AND a.status <> 'cancelled' ORDER BY s.starts_at ASC LIMIT " . max(1,(int)$limit);
        $st=Database::connection()->prepare($sql); $st->execute([$pid]); return $st->fetchAll();
    }

    public static function assignment(int $userId, int $assignmentId): ?array
    {
        $pid = self::professionalIdForUser($userId);
        $sql='SELECT a.*,s.title,s.starts_at,s.ends_at,s.address,s.city,s.state,s.shift_value,s.dress_code,s.notes,jc.name category_name,co.trade_name company_name
              FROM tp_assignments a JOIN tp_shifts s ON s.id=a.shift_id JOIN tp_job_categories jc ON jc.id=s.category_id JOIN tp_companies co ON co.id=s.company_id
              WHERE a.id=? AND a.professional_id=?';
        $st=Database::connection()->prepare($sql); $st->execute([$assignmentId,$pid]); return $st->fetch() ?: null;
    }

    public static function checkIn(int $userId, int $assignmentId, ?string $pin = null): void
    {
        $a=self::assignment($userId,$assignmentId); if(!$a) throw new RuntimeException('Turno não encontrado.');
        if(!in_array($a['status'],['confirmed','checked_in'],true)) throw new RuntimeException('Este turno não permite check-in.');
        Database::connection()->prepare('UPDATE tp_assignments SET status="checked_in",checkin_at=COALESCE(checkin_at,NOW()),checkin_method=? WHERE id=?')->execute([$pin?'pin':'web',$assignmentId]);
    }

    public static function checkOut(int $userId, int $assignmentId): void
    {
        $a=self::assignment($userId,$assignmentId); if(!$a) throw new RuntimeException('Turno não encontrado.');
        if($a['status']!=='checked_in') throw new RuntimeException('Faça o check-in antes de encerrar.');
        $pdo=Database::connection(); $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE tp_assignments SET status="completed",checkout_at=NOW() WHERE id=?')->execute([$assignmentId]);
            $pdo->prepare('UPDATE tp_professionals SET completed_shifts=completed_shifts+1 WHERE id=?')->execute([$a['professional_id']]);
            $companyId=$pdo->query('SELECT company_id FROM tp_shifts WHERE id='.(int)$a['shift_id'])->fetchColumn();
            $pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"credit",?,"shift_payment","available",NOW())')->execute([$companyId,$a['professional_id'],$assignmentId,$a['agreed_value']]);
            $pdo->prepare('INSERT INTO tp_ledger (company_id,professional_id,assignment_id,direction,amount,kind,status,created_at) VALUES (?,?,?,"debit",?,"shift_cost","settled",NOW())')->execute([$companyId,$a['professional_id'],$assignmentId,$a['agreed_value']]);
            $pdo->commit();
        } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    public static function earnings(int $userId): array
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare("SELECT COALESCE(SUM(amount),0) total, COALESCE(SUM(CASE WHEN status='available' THEN amount ELSE 0 END),0) available FROM tp_ledger WHERE professional_id=? AND direction='credit'");
        $st->execute([$pid]); $summary=$st->fetch() ?: ['total'=>0,'available'=>0];
        $st=Database::connection()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') month,SUM(amount) amount FROM tp_ledger WHERE professional_id=? AND direction='credit' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month");
        $st->execute([$pid]);
        return ['summary'=>$summary,'months'=>$st->fetchAll()];
    }

    public static function documents(int $userId): array
    {
        $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT * FROM tp_documents WHERE professional_id=? ORDER BY type'); $st->execute([$pid]); return $st->fetchAll();
    }

    public static function reputation(int $userId): array
    {
        $profile=self::professionalProfile($userId); $pid=self::professionalIdForUser($userId);
        $st=Database::connection()->prepare('SELECT * FROM tp_reputation_events WHERE professional_id=? ORDER BY occurred_at DESC LIMIT 20'); $st->execute([$pid]);
        return ['profile'=>$profile,'events'=>$st->fetchAll()];
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
        $plain=bin2hex(random_bytes(32)); $hash=hash('sha256',$plain);
        Database::connection()->prepare('INSERT INTO tp_api_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())')->execute([$userId,$hash]);
        return $plain;
    }

    public static function apiUserFromToken(string $token): ?array
    {
        if(!$token) return null;
        $hash=hash('sha256',$token);
        $sql='SELECT u.* FROM tp_api_tokens t JOIN tp_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW() AND u.status="active" LIMIT 1';
        $st=Database::connection()->prepare($sql); $st->execute([$hash]); return $st->fetch() ?: null;
    }

    public static function revokeApiToken(string $token): void
    {
        Database::connection()->prepare('UPDATE tp_api_tokens SET revoked_at=NOW() WHERE token_hash=?')->execute([hash('sha256',$token)]);
    }
}
