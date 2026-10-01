<?php
final class Web
{
    public static function handle(string $path, string $method): never
    {
        if ($path === '/login') {
            if ($method === 'POST') {
                verify_csrf();
                if (!Database::available()) {
                    flash('error','Banco não configurado. Execute a instalação primeiro.');
                    redirect('install.php');
                }
                if (Auth::attempt((string)($_POST['email'] ?? ''),(string)($_POST['password'] ?? ''))) {
                    redirect(Auth::dashboardPath(Auth::user()));
                }
                flash('error','E-mail ou senha inválidos.');
                redirect('login');
            }
            View::render('login',['title'=>'Entrar'],false);
        }

        if ($path === '/logout') {
            Auth::logout(); redirect('login');
        }

        if ($path === '/' || $path === '') {
            if(!Auth::check()) redirect('login');
            redirect(Auth::dashboardPath(Auth::user()));
        }

        if (!Database::available()) {
            View::render('not_installed',['title'=>'Instalação necessária']);
        }

        if ($path === '/empresa/dashboard') {
            $u=Auth::requireRole('company');
            View::render('company_dashboard',['title'=>'Dashboard','data'=>Data::companyDashboard((int)$u['id']),'user'=>$u]);
        }
        if ($path === '/empresa/vagas') {
            $u=Auth::requireRole('company');
            View::render('company_shifts',['title'=>'Minhas vagas','shifts'=>Data::companyShifts((int)$u['id']),'user'=>$u]);
        }
        if ($path === '/empresa/vagas/nova') {
            $u=Auth::requireRole('company');
            if($method==='POST'){
                verify_csrf();
                try { $id=Data::createShift((int)$u['id'],$_POST); flash('success','Vaga publicada com sucesso.'); redirect('empresa/vagas'); }
                catch(Throwable $e){ flash('error',$e->getMessage()); }
            }
            View::render('company_shift_form',['title'=>'Publicar nova vaga','categories'=>Data::categories(),'user'=>$u]);
        }
        if ($path === '/empresa/profissionais') {
            $u=Auth::requireRole('company');
            View::render('professionals',['title'=>'Profissionais','professionals'=>Data::suggestedProfessionals(30),'user'=>$u]);
        }
        if (in_array($path,['/empresa/escalas','/empresa/financeiro','/empresa/avaliacoes','/suporte'],true)) {
            $u=Auth::requireRole('company','professional','admin');
            $labels=['/empresa/escalas'=>'Escalas','/empresa/financeiro'=>'Financeiro','/empresa/avaliacoes'=>'Avaliações','/suporte'=>'Suporte'];
            View::render('placeholder',['title'=>$labels[$path]??'Página','heading'=>$labels[$path]??'Página','user'=>$u]);
        }

        if ($path === '/profissional/inicio') {
            $u=Auth::requireRole('professional');
            View::render('professional_dashboard',['title'=>'Início','data'=>Data::professionalHome((int)$u['id']),'user'=>$u]);
        }
        if ($path === '/profissional/oportunidades') {
            $u=Auth::requireRole('professional');
            View::render('opportunities',['title'=>'Oportunidades','opportunities'=>Data::opportunities((int)$u['id']),'user'=>$u]);
        }
        if (preg_match('#^/profissional/vagas/(\d+)$#',$path,$m)) {
            $u=Auth::requireRole('professional'); $shift=Data::shift((int)$m[1]);
            if(!$shift){ http_response_code(404); View::render('placeholder',['title'=>'Não encontrada','heading'=>'Vaga não encontrada','user'=>$u]); }
            View::render('shift_detail',['title'=>$shift['category_name'],'shift'=>$shift,'user'=>$u]);
        }
        if (preg_match('#^/profissional/vagas/(\d+)/aceitar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional'); verify_csrf();
            try { $assignmentId=Data::acceptShift((int)$u['id'],(int)$m[1]); flash('success','Turno confirmado!'); redirect('profissional/turno/'.$assignmentId); }
            catch(Throwable $e){ flash('error',$e->getMessage()); redirect('profissional/vagas/'.$m[1]); }
        }
        if ($path === '/profissional/turnos' || $path === '/profissional/agenda') {
            $u=Auth::requireRole('professional');
            View::render('assignments',['title'=>$path==='/profissional/agenda'?'Agenda':'Meus turnos','assignments'=>Data::professionalAssignments((int)$u['id']),'user'=>$u]);
        }
        if (preg_match('#^/profissional/turno/(\d+)$#',$path,$m)) {
            $u=Auth::requireRole('professional'); $a=Data::assignment((int)$u['id'],(int)$m[1]);
            if(!$a){http_response_code(404);View::render('placeholder',['title'=>'Não encontrado','heading'=>'Turno não encontrado','user'=>$u]);}
            View::render('current_shift',['title'=>'Turno atual','assignment'=>$a,'user'=>$u]);
        }
        if (preg_match('#^/profissional/turno/(\d+)/checkin$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional'); verify_csrf();
            try { Data::checkIn((int)$u['id'],(int)$m[1],(string)($_POST['pin']??'')); flash('success','Check-in realizado. Bom turno!'); }
            catch(Throwable $e){flash('error',$e->getMessage());}
            redirect('profissional/turno/'.$m[1]);
        }
        if (preg_match('#^/profissional/turno/(\d+)/checkout$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional'); verify_csrf();
            try { Data::checkOut((int)$u['id'],(int)$m[1]); flash('success','Turno concluído. O valor foi lançado nos seus ganhos.'); }
            catch(Throwable $e){flash('error',$e->getMessage());}
            redirect('profissional/turno/'.$m[1]);
        }
        if ($path === '/profissional/ganhos') {
            $u=Auth::requireRole('professional'); View::render('earnings',['title'=>'Ganhos','data'=>Data::earnings((int)$u['id']),'user'=>$u]);
        }
        if ($path === '/profissional/reputacao') {
            $u=Auth::requireRole('professional'); View::render('reputation',['title'=>'Reputação','data'=>Data::reputation((int)$u['id']),'user'=>$u]);
        }
        if ($path === '/profissional/documentos') {
            $u=Auth::requireRole('professional'); View::render('documents',['title'=>'Documentos','documents'=>Data::documents((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/admin/dashboard') {
            $u=Auth::requireRole('admin'); View::render('admin_dashboard',['title'=>'Administração','stats'=>Data::adminStats(),'user'=>$u]);
        }

        http_response_code(404);
        $u=Auth::user();
        View::render('placeholder',['title'=>'404','heading'=>'Página não encontrada','user'=>$u]);
    }
}
