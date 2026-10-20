<?php
declare(strict_types=1);

/**
 * Notificações FCM HTTP v1: cadastro de dispositivos, fila persistente e envio via cron.
 * Nunca envia usando credenciais no aplicativo ou no GitHub.
 */
final class Push
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) return;
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_push_devices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token TEXT NOT NULL,
            platform VARCHAR(12) NOT NULL DEFAULT 'android',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_push_token (token_hash),
            INDEX idx_push_user (user_id,enabled),
            CONSTRAINT fk_push_device_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_push_deliveries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            notification_id BIGINT UNSIGNED NOT NULL,
            device_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_at DATETIME NULL,
            sent_at DATETIME NULL,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_push_delivery (notification_id,device_id),
            INDEX idx_push_pending (status,available_at),
            CONSTRAINT fk_push_delivery_notification FOREIGN KEY (notification_id) REFERENCES tp_notifications(id) ON DELETE CASCADE,
            CONSTRAINT fk_push_delivery_device FOREIGN KEY (device_id) REFERENCES tp_push_devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tp_push_preferences (
            user_id BIGINT UNSIGNED PRIMARY KEY,
            radius_km SMALLINT UNSIGNED NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_push_preference_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready = true;
    }

    public static function preferences(int $userId): array
    {
        self::ensureSchema();
        $pdo=Database::connection();
        $st=$pdo->prepare('SELECT radius_km,latitude,longitude FROM tp_push_preferences WHERE user_id=? LIMIT 1');
        $st->execute([$userId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if($row){
            return ['radius_km'=>(int)$row['radius_km'],
                'latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude'],
                'configured'=>true];
        }
        // Se o profissional já informou coordenadas no endereço cadastrado,
        // elas podem ser utilizadas como ponto de partida, sem ativar o filtro.
        $st=$pdo->prepare('SELECT latitude,longitude FROM tp_professionals WHERE user_id=? LIMIT 1');
        $st->execute([$userId]);
        $profile=$st->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['radius_km'=>null,
            'latitude'=>isset($profile['latitude'])?(float)$profile['latitude']:null,
            'longitude'=>isset($profile['longitude'])?(float)$profile['longitude']:null,
            'configured'=>false];
    }

    public static function savePreferences(int $userId,int $radiusKm,mixed $latitude=null,mixed $longitude=null): array
    {
        if($radiusKm<5 || $radiusKm>200 || $radiusKm%5!==0) {
            throw new InvalidArgumentException('Selecione uma distância entre 5 e 200 km, de 5 em 5 km.');
        }
        $previous=self::preferences($userId);
        $latitude=($latitude===null || $latitude==='') ? $previous['latitude'] : $latitude;
        $longitude=($longitude===null || $longitude==='') ? $previous['longitude'] : $longitude;
        if(!is_numeric($latitude) || !is_numeric($longitude)
            || (float)$latitude<-90 || (float)$latitude>90
            || (float)$longitude<-180 || (float)$longitude>180){
            throw new InvalidArgumentException('Defina uma localização válida para calcular a distância das vagas.');
        }
        Database::connection()->prepare(
            'INSERT INTO tp_push_preferences (user_id,radius_km,latitude,longitude) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE radius_km=VALUES(radius_km),latitude=VALUES(latitude),
                 longitude=VALUES(longitude),updated_at=NOW()'
        )->execute([$userId,$radiusKm,$latitude,$longitude]);
        return self::preferences($userId);
    }

    /** Distância geográfica em linha reta (Haversine). Ausência de raio mantém o comportamento legado. */
    public static function withinDistance(mixed $radiusKm,mixed $originLat,mixed $originLng,mixed $shiftLat,mixed $shiftLng): bool
    {
        if($radiusKm===null) return true;
        foreach([$originLat,$originLng,$shiftLat,$shiftLng] as $coordinate){
            if($coordinate===null || !is_numeric($coordinate)) return false;
        }
        $lat1=deg2rad((float)$originLat);
        $lat2=deg2rad((float)$shiftLat);
        $deltaLat=$lat2-$lat1;
        $deltaLon=deg2rad((float)$shiftLng-(float)$originLng);
        $h=pow(sin($deltaLat/2),2)+cos($lat1)*cos($lat2)*pow(sin($deltaLon/2),2);
        $distanceKm=2*6371.0088*asin(min(1.0,sqrt(max(0.0,$h))));
        return $distanceKm<=(float)$radiusKm+0.000001;
    }

    public static function register(int $userId, string $token, string $platform = 'android', bool $enabled = true): void
    {
        if (strlen($token) < 20 || strlen($token) > 4096 || preg_match('/\s/', $token)) {
            throw new InvalidArgumentException('Token FCM inválido.');
        }
        if (!in_array($platform, ['android','ios'], true)) {
            throw new InvalidArgumentException('Plataforma inválida.');
        }
        self::ensureSchema();
        Database::connection()->prepare(
            'INSERT INTO tp_push_devices (user_id,token_hash,token,platform,enabled,last_seen_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),token=VALUES(token),
               platform=VALUES(platform),enabled=VALUES(enabled),last_seen_at=NOW()'
        )->execute([$userId, hash('sha256', $token), $token, $platform, (int)$enabled]);
    }

    public static function unregister(int $userId, string $token): void
    {
        if ($token === '') return;
        self::ensureSchema();
        Database::connection()->prepare('DELETE FROM tp_push_devices WHERE user_id=? AND token_hash=?')
            ->execute([$userId, hash('sha256', $token)]);
    }

    public static function enqueueNotification(int $notificationId, int $userId): void
    {
        self::ensureSchema();
        Database::connection()->prepare(
            "INSERT IGNORE INTO tp_push_deliveries (notification_id,device_id)
             SELECT ?,d.id FROM tp_push_devices d
             WHERE d.user_id=? AND d.enabled=1"
        )->execute([$notificationId,$userId]);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function credentials(): array
    {
        $file = trim((string)(getenv('TP_FIREBASE_SERVICE_ACCOUNT_PATH') ?: app_config('firebase.service_account_path') ?: ''));
        if ($file === '' || !is_readable($file)) {
            throw new RuntimeException('Arquivo de conta de serviço Firebase não configurado.');
        }
        $cred = json_decode((string)file_get_contents($file), true);
        if (!is_array($cred) || empty($cred['client_email']) || empty($cred['private_key']) || empty($cred['project_id'])) {
            throw new RuntimeException('Conta de serviço Firebase inválida.');
        }
        return $cred;
    }

    private static function http(string $url, array $headers, string $payload): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Extensão PHP cURL não disponível.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Falha HTTPS Firebase: '.$error);
        $decoded = json_decode((string)$raw, true);
        return [$status, is_array($decoded) ? $decoded : []];
    }

    private static function oauthToken(array $cred): string
    {
        $now = time();
        $header = self::base64Url(json_encode(['alg'=>'RS256','typ'=>'JWT'], JSON_THROW_ON_ERROR));
        $claims = self::base64Url(json_encode([
            'iss'=>$cred['client_email'],
            'scope'=>'https://www.googleapis.com/auth/firebase.messaging',
            'aud'=>'https://oauth2.googleapis.com/token',
            'iat'=>$now,'exp'=>$now+3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        if (!openssl_sign($unsigned, $signature, $cred['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar o JWT do Firebase.');
        }
        [$status,$data] = self::http(
            'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$unsigned.'.'.self::base64Url($signature)])
        );
        if ($status !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('OAuth Firebase falhou (HTTP '.$status.').');
        }
        return (string)$data['access_token'];
    }

    public static function dispatch(int $limit = 100): array
    {
        self::ensureSchema();
        $cred = self::credentials();
        $accessToken = self::oauthToken($cred);
        $pdo = Database::connection();
        $st = $pdo->query("SELECT q.id,n.id notification_id,n.type,n.title,n.body,n.action_url,
                                 d.token,d.id device_id
                          FROM tp_push_deliveries q
                          JOIN tp_notifications n ON n.id=q.notification_id
                          JOIN tp_push_devices d ON d.id=q.device_id AND d.user_id=n.user_id
                          WHERE d.enabled=1 AND ((q.status='pending' AND q.available_at<=NOW())
                              OR (q.status='processing' AND q.locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)))
                          ORDER BY q.id ASC LIMIT ".max(1,min(500,$limit)));
        $sent=0; $failed=0; $skipped=0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id=(int)$row['id'];
            $claim=$pdo->prepare("UPDATE tp_push_deliveries SET status='processing',attempts=attempts+1,locked_at=NOW()
               WHERE id=? AND ((status='pending' AND available_at<=NOW()) OR
                  (status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)))");
            $claim->execute([$id]);
            if ($claim->rowCount() !== 1) continue;
            $shiftId=0;
            if (preg_match('#^profissional/vagas/(\d+)$#',(string)$row['action_url'],$m)) $shiftId=(int)$m[1];
            // Não avisar de uma vaga cancelada, iniciada ou cuja categoria deixou de interessar.
            $valid=$pdo->prepare("SELECT s.latitude,s.longitude,pref.radius_km,
                    pref.latitude origin_latitude,pref.longitude origin_longitude
                FROM tp_shifts s
                JOIN tp_professional_categories pc ON pc.category_id=s.category_id
                JOIN tp_professionals p ON p.id=pc.professional_id
                JOIN tp_notifications n ON n.user_id=p.user_id
                LEFT JOIN tp_push_preferences pref ON pref.user_id=n.user_id
                WHERE s.id=? AND n.id=? AND s.status IN ('published','filling') AND s.starts_at>NOW()
                LIMIT 1");
            $valid->execute([$shiftId,(int)$row['notification_id']]);
            $eligible=$valid->fetch(PDO::FETCH_ASSOC);
            if (!$shiftId || !$eligible || !self::withinDistance(
                $eligible['radius_km'],$eligible['origin_latitude'],$eligible['origin_longitude'],
                $eligible['latitude'],$eligible['longitude'])) {
                $pdo->prepare("UPDATE tp_push_deliveries SET status='skipped',locked_at=NULL WHERE id=?")->execute([$id]);
                $skipped++;
                continue;
            }
            $payload=json_encode(['message'=>[
                'token'=>$row['token'],
                'notification'=>['title'=>$row['title'],'body'=>$row['body']],
                'data'=>['type'=>'matching_shift','shift_id'=>(string)$shiftId,'notification_id'=>(string)$row['notification_id']],
                'android'=>['priority'=>'HIGH','ttl'=>'86400s'],
            ]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            try {
                [$code,$result]=self::http(
                    'https://fcm.googleapis.com/v1/projects/'.rawurlencode((string)$cred['project_id']).'/messages:send',
                    ['Authorization: Bearer '.$accessToken,'Content-Type: application/json; charset=utf-8'],
                    $payload
                );
                if ($code===200 && !empty($result['name'])) {
                    $pdo->prepare("UPDATE tp_push_deliveries SET status='sent',sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=?")->execute([$id]);
                    $sent++;
                    continue;
                }
                $fcmStatus=(string)($result['error']['status']??'');
                if ($fcmStatus==='UNREGISTERED') {
                    $pdo->prepare("UPDATE tp_push_devices SET enabled=0 WHERE id=?")->execute([(int)$row['device_id']]);
                    $pdo->prepare("UPDATE tp_push_deliveries SET status='invalid',locked_at=NULL,last_error='UNREGISTERED' WHERE id=?")->execute([$id]);
                    $skipped++;
                    continue;
                }
                throw new RuntimeException('FCM HTTP '.$code.' ('.$fcmStatus.')');
            } catch(Throwable $e) {
                $attempts=$pdo->prepare('SELECT attempts FROM tp_push_deliveries WHERE id=?');
                $attempts->execute([$id]);
                $n=(int)$attempts->fetchColumn();
                $status=$n>=5?'failed':'pending';
                $delay=min(3600,(int)pow(2,$n)*60);
                $pdo->prepare("UPDATE tp_push_deliveries SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),
                    locked_at=NULL,last_error=? WHERE id=?")
                    ->execute([$status,$delay,mb_substr($e->getMessage(),0,255),$id]);
                $failed++;
            }
        }
        return ['sent'=>$sent,'retry_or_failed'=>$failed,'skipped'=>$skipped];
    }
}
