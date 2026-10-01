<?php
final class Api
{
    private static function bearer(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
        return '';
    }

    private static function user(): array
    {
        $user = Data::apiUserFromToken(self::bearer());
        if (!$user) json_response(['ok'=>false,'error'=>'Não autenticado.'], 401);
        return $user;
    }

    public static function handle(string $path, string $method): never
    {
        if (!Database::available()) json_response(['ok'=>false,'error'=>'Banco de dados indisponível. Execute /install.php.'], 503);
        $relative = preg_replace('#^/api/v1#', '', $path) ?: '/';

        if ($relative === '/health' && $method === 'GET') {
            json_response(['ok'=>true,'service'=>'TurnoPronto API','version'=>'1.0.0','time'=>date(DATE_ATOM)]);
        }

        if ($relative === '/auth/login' && $method === 'POST') {
            $data = json_input();
            $email = mb_strtolower(trim((string)($data['email'] ?? '')));
            $password = (string)($data['password'] ?? '');
            $st=Database::connection()->prepare('SELECT * FROM tp_users WHERE email=? AND status="active" LIMIT 1');
            $st->execute([$email]); $user=$st->fetch();
            if(!$user || !password_verify($password,$user['password_hash'])) json_response(['ok'=>false,'error'=>'E-mail ou senha inválidos.'],422);
            $token=Data::createApiToken((int)$user['id']);
            unset($user['password_hash']);
            json_response(['ok'=>true,'token'=>$token,'user'=>$user]);
        }

        $user = self::user();

        if ($relative === '/auth/logout' && $method === 'POST') {
            Data::revokeApiToken(self::bearer());
            json_response(['ok'=>true]);
        }
        if ($relative === '/me' && $method === 'GET') {
            unset($user['password_hash']);
            $profile = $user['role']==='professional' ? Data::professionalProfile((int)$user['id']) : ($user['role']==='company' ? Data::companyProfile((int)$user['id']) : []);
            json_response(['ok'=>true,'user'=>$user,'profile'=>$profile]);
        }
        if ($relative === '/opportunities' && $method === 'GET') {
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::opportunities((int)$user['id'])]);
        }
        if (preg_match('#^/shifts/(\d+)$#',$relative,$m) && $method==='GET') {
            $shift=Data::shift((int)$m[1]);
            if(!$shift) json_response(['ok'=>false,'error'=>'Vaga não encontrada.'],404);
            json_response(['ok'=>true,'data'=>$shift]);
        }
        if (preg_match('#^/shifts/(\d+)/accept$#',$relative,$m) && $method==='POST') {
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try { $id=Data::acceptShift((int)$user['id'],(int)$m[1]); json_response(['ok'=>true,'assignment_id'=>$id]); }
            catch(Throwable $e){ json_response(['ok'=>false,'error'=>$e->getMessage()],422); }
        }
        if ($relative === '/assignments' && $method === 'GET') {
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::professionalAssignments((int)$user['id'])]);
        }
        if (preg_match('#^/assignments/(\d+)$#',$relative,$m) && $method==='GET') {
            $a=Data::assignment((int)$user['id'],(int)$m[1]);
            if(!$a) json_response(['ok'=>false,'error'=>'Turno não encontrado.'],404);
            json_response(['ok'=>true,'data'=>$a]);
        }
        if (preg_match('#^/assignments/(\d+)/check-in$#',$relative,$m) && $method==='POST') {
            $data=json_input();
            try { Data::checkIn((int)$user['id'],(int)$m[1],isset($data['pin'])?(string)$data['pin']:null); json_response(['ok'=>true]); }
            catch(Throwable $e){ json_response(['ok'=>false,'error'=>$e->getMessage()],422); }
        }
        if (preg_match('#^/assignments/(\d+)/check-out$#',$relative,$m) && $method==='POST') {
            try { Data::checkOut((int)$user['id'],(int)$m[1]); json_response(['ok'=>true]); }
            catch(Throwable $e){ json_response(['ok'=>false,'error'=>$e->getMessage()],422); }
        }
        if ($relative === '/earnings' && $method === 'GET') {
            json_response(['ok'=>true,'data'=>Data::earnings((int)$user['id'])]);
        }
        if ($relative === '/reputation' && $method === 'GET') {
            json_response(['ok'=>true,'data'=>Data::reputation((int)$user['id'])]);
        }
        if ($relative === '/documents' && $method === 'GET') {
            json_response(['ok'=>true,'data'=>Data::documents((int)$user['id'])]);
        }

        json_response(['ok'=>false,'error'=>'Endpoint não encontrado.'],404);
    }
}
