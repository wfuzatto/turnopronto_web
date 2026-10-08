<?php
final class Api
{
    private static function bearer(): string
    {
        $header=$_SERVER['HTTP_AUTHORIZATION']??'';
        if(preg_match('/Bearer\s+(.+)/i',$header,$m)) return trim($m[1]);
        return '';
    }

    private static function user(): array
    {
        $user=Data::apiUserFromToken(self::bearer());
        if(!$user) json_response(['ok'=>false,'error'=>'Não autenticado.'],401);
        return $user;
    }

    public static function handle(string $path, string $method): never
    {
        if(!Database::available()) json_response(['ok'=>false,'error'=>'Serviço temporariamente indisponível.'],503);
        $relative=preg_replace('#^/api/v1#','',$path) ?: '/';

        if($relative==='/health' && $method==='GET'){
            json_response([
                'ok'=>true,
                'service'=>'TurnoPronto API',
                'version'=>'1.1.1',
                'time'=>date(DATE_ATOM),
                'public_base_path'=>base_path(),
                'css_asset'=>asset('css/app.css'),
            ]);
        }

        if($relative==='/auth/login' && $method==='POST'){
            $data=json_input();
            $identifier=trim((string)($data['identifier']??$data['cpf']??$data['email']??''));
            $password=(string)($data['password']??'');
            $cpf=preg_replace('/\D+/','',$identifier);
            if(strlen($cpf)===11){
                $st=Database::connection()->prepare('SELECT u.* FROM tp_users u JOIN tp_professionals p ON p.user_id=u.id WHERE REPLACE(REPLACE(REPLACE(p.cpf,".",""),"-","")," ","")=? AND u.status="active" LIMIT 1');
                $st->execute([$cpf]);
            }else{
                $st=Database::connection()->prepare('SELECT * FROM tp_users WHERE email=? AND status="active" LIMIT 1');
                $st->execute([mb_strtolower($identifier)]);
            }
            $user=$st->fetch();
            if(!$user || !password_verify($password,$user['password_hash'])) json_response(['ok'=>false,'error'=>'CPF/e-mail ou senha inválidos.'],422);
            $token=Data::createApiToken((int)$user['id']);
            unset($user['password_hash']);
            json_response(['ok'=>true,'token'=>$token,'user'=>$user]);
        }

        if($relative==='/categories' && $method==='GET'){
            json_response(['ok'=>true,'data'=>Data::categories()]);
        }

        if($relative==='/appearance' && $method==='GET'){
            json_response(['ok'=>true,'data'=>Data::platformAppearance()]);
        }

        if($relative==='/auth/register/start' && $method==='POST'){
            $data=json_input();
            $role=(string)($data['role']??'');
            try {
                $result=Registration::start($data,$role);
                json_response(['ok'=>true]+$result,201);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if($relative==='/auth/register/resend' && $method==='POST'){
            $data=json_input();
            try {
                $result=Registration::resend((string)($data['registration_id']??''));
                json_response(['ok'=>true]+$result);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if($relative==='/auth/register/verify' && $method==='POST'){
            $data=json_input();
            try {
                $result=Registration::verify(
                    (string)($data['registration_id']??''),
                    (string)($data['code']??'')
                );
                json_response(['ok'=>true]+$result);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        $user=self::user();

        if($relative==='/auth/logout' && $method==='POST'){
            Data::revokeApiToken(self::bearer());
            json_response(['ok'=>true]);
        }

        if($relative==='/me' && $method==='GET'){
            unset($user['password_hash']);
            if($user['role']==='professional'){
                $reputation=Data::reputation((int)$user['id']);
                $profile=$reputation['profile']??Data::professionalProfile((int)$user['id']);
            }else{
                $profile=$user['role']==='company' ? Data::companyProfile((int)$user['id']) : [];
            }
            json_response(['ok'=>true,'user'=>$user,'profile'=>$profile]);
        }

        if($relative==='/opportunities' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::opportunities((int)$user['id'])]);
        }

        if($relative==='/home' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::professionalHome((int)$user['id'])]);
        }

        if($relative==='/onboarding' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::professionalOnboardingState((int)$user['id'])]);
        }

        if($relative==='/onboarding/payment' && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try{
                Data::updateProfessionalOnboardingStep((int)$user['id'],'payment',json_input());
                json_response(['ok'=>true,'data'=>Data::professionalOnboardingState((int)$user['id'])]);
            }catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if(preg_match('#^/shifts/(\d+)$#',$relative,$m) && $method==='GET'){
            $shift=Data::shift((int)$m[1]);
            if(!$shift) json_response(['ok'=>false,'error'=>'Vaga não encontrada.'],404);
            if($user['role']==='professional'){
                $shift['following']=Data::isFollowingShift((int)$user['id'],(int)$m[1]);
            }
            json_response(['ok'=>true,'data'=>$shift]);
        }

        if(preg_match('#^/shifts/(\d+)/follow$#',$relative,$m) && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try{
                Data::followShift((int)$user['id'],(int)$m[1]);
                json_response(['ok'=>true,'following'=>true]);
            }catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if(preg_match('#^/shifts/(\d+)/unfollow$#',$relative,$m) && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try{
                Data::unfollowShift((int)$user['id'],(int)$m[1]);
                json_response(['ok'=>true,'following'=>false]);
            }catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if(preg_match('#^/shifts/(\d+)/accept$#',$relative,$m) && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try {
                $result=Data::acceptShift((int)$user['id'],(int)$m[1]);
                json_response(['ok'=>true]+$result);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if($relative==='/assignments' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::professionalAssignments((int)$user['id'])]);
        }

        if(preg_match('#^/assignments/(\d+)$#',$relative,$m) && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            $a=Data::assignment((int)$user['id'],(int)$m[1]);
            if(!$a) json_response(['ok'=>false,'error'=>'Turno não encontrado.'],404);
            json_response(['ok'=>true,'data'=>$a]);
        }

        if(preg_match('#^/assignments/(\d+)/check-in$#',$relative,$m) && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            $data=json_input();
            try {
                Data::checkIn((int)$user['id'],(int)$m[1],isset($data['pin'])?(string)$data['pin']:null);
                json_response(['ok'=>true]);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if(preg_match('#^/assignments/(\d+)/check-out$#',$relative,$m) && $method==='POST'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            try {
                Data::checkOut((int)$user['id'],(int)$m[1]);
                json_response(['ok'=>true]);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if($relative==='/earnings' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::earnings((int)$user['id'])]);
        }

        if($relative==='/reputation' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::reputation((int)$user['id'])]);
        }

        if($relative==='/documents' && $method==='GET'){
            if($user['role']!=='professional') json_response(['ok'=>false,'error'=>'Endpoint exclusivo do profissional.'],403);
            json_response(['ok'=>true,'data'=>Data::documents((int)$user['id'])]);
        }

        json_response(['ok'=>false,'error'=>'Endpoint não encontrado.'],404);
    }
}
