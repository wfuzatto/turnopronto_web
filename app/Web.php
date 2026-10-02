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
            Auth::logout();
            redirect('login');
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
                try {
                    Data::createShift((int)$u['id'],$_POST);
                    flash('success','Vaga publicada com sucesso.');
                    redirect('empresa/vagas');
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('company_shift_form',[
                'title'=>'Publicar nova vaga',
                'categories'=>Data::categories(),
                'shift'=>null,
                'user'=>$u
            ]);
        }

        if (preg_match('#^/empresa/vagas/(\d+)/editar$#',$path,$m)) {
            $u=Auth::requireRole('company');
            $shift=Data::companyShift((int)$u['id'],(int)$m[1]);
            if(!$shift){
                http_response_code(404);
                View::render('placeholder',['title'=>'Vaga não encontrada','heading'=>'Vaga não encontrada','user'=>$u]);
            }
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateShift((int)$u['id'],(int)$m[1],$_POST);
                    flash('success','Vaga atualizada com sucesso.');
                    redirect('empresa/vagas/'.$m[1]);
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('company_shift_form',[
                'title'=>'Editar vaga',
                'categories'=>Data::categories(),
                'shift'=>$shift,
                'user'=>$u
            ]);
        }

        if (preg_match('#^/empresa/vagas/(\d+)/cancelar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                Data::cancelShift((int)$u['id'],(int)$m[1],trim((string)($_POST['reason']??'')));
                flash('success','Vaga cancelada. Os profissionais vinculados foram liberados.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/vagas/'.$m[1]);
        }

        if (preg_match('#^/empresa/vagas/(\d+)/candidaturas/(\d+)/(aprovar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                if($m[3]==='aprovar'){
                    Data::approveApplication((int)$u['id'],(int)$m[1],(int)$m[2]);
                    flash('success','Profissional aprovado e turno confirmado.');
                } else {
                    Data::rejectApplication((int)$u['id'],(int)$m[1],(int)$m[2]);
                    flash('success','Candidatura rejeitada.');
                }
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/vagas/'.$m[1]);
        }

        if (preg_match('#^/empresa/vagas/(\d+)$#',$path,$m)) {
            $u=Auth::requireRole('company');
            $shift=Data::companyShift((int)$u['id'],(int)$m[1]);
            if(!$shift){
                http_response_code(404);
                View::render('placeholder',['title'=>'Vaga não encontrada','heading'=>'Vaga não encontrada','user'=>$u]);
            }
            View::render('company_shift_detail',[
                'title'=>$shift['category_name'].' • '.$shift['title'],
                'shift'=>$shift,
                'candidates'=>Data::companyShiftCandidates((int)$u['id'],(int)$m[1]),
                'assigned'=>Data::companyShiftAssignments((int)$u['id'],(int)$m[1]),
                'user'=>$u
            ]);
        }

        if ($path === '/empresa/profissionais') {
            $u=Auth::requireRole('company');
            View::render('professionals',[
                'title'=>'Profissionais',
                'professionals'=>Data::suggestedProfessionals(30),
                'openShifts'=>Data::companyOpenShifts((int)$u['id']),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/empresa/profissionais/(\d+)/convidar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                $shiftId=(int)($_POST['shift_id']??0);
                Data::inviteProfessional((int)$u['id'],(int)$m[1],$shiftId);
                flash('success','Convite registrado para o profissional.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/profissionais');
        }

        if ($path === '/empresa/escalas') {
            $u=Auth::requireRole('company');
            View::render('company_schedule',[
                'title'=>'Escalas',
                'assignments'=>Data::companySchedule((int)$u['id']),
                'user'=>$u
            ]);
        }

        if ($path === '/empresa/financeiro') {
            $u=Auth::requireRole('company');
            View::render('company_finance',[
                'title'=>'Financeiro',
                'data'=>Data::companyFinance((int)$u['id']),
                'user'=>$u
            ]);
        }

        if ($path === '/empresa/avaliacoes') {
            $u=Auth::requireRole('company');
            View::render('company_reviews',[
                'title'=>'Avaliações',
                'reviews'=>Data::companyReviews((int)$u['id']),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/empresa/avaliacoes/(\\d+)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                Data::createCompanyReview((int)$u['id'],(int)$m[1],$_POST);
                flash('success','Avaliação registrada. Os indicadores objetivos continuam separados da nota subjetiva.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/avaliacoes');
        }

        if ($path === '/suporte') {
            $u=Auth::requireRole('company','professional','admin');
            View::render('placeholder',['title'=>'Suporte','heading'=>'Suporte','user'=>$u]);
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
            $u=Auth::requireRole('professional');
            $shift=Data::shift((int)$m[1]);
            if(!$shift){
                http_response_code(404);
                View::render('placeholder',['title'=>'Não encontrada','heading'=>'Vaga não encontrada','user'=>$u]);
            }
            View::render('shift_detail',['title'=>$shift['category_name'],'shift'=>$shift,'user'=>$u]);
        }

        if (preg_match('#^/profissional/vagas/(\d+)/aceitar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                $result=Data::acceptShift((int)$u['id'],(int)$m[1]);
                if(!empty($result['assignment_id'])){
                    flash('success','Turno confirmado!');
                    redirect('profissional/turno/'.$result['assignment_id']);
                }
                flash('success','Candidatura enviada. A empresa fará a confirmação.');
                redirect('profissional/oportunidades');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('profissional/vagas/'.$m[1]);
            }
        }

        if ($path === '/profissional/turnos' || $path === '/profissional/agenda') {
            $u=Auth::requireRole('professional');
            View::render('assignments',['title'=>$path==='/profissional/agenda'?'Agenda':'Meus turnos','assignments'=>Data::professionalAssignments((int)$u['id']),'user'=>$u]);
        }

        if (preg_match('#^/profissional/turno/(\d+)$#',$path,$m)) {
            $u=Auth::requireRole('professional');
            $a=Data::assignment((int)$u['id'],(int)$m[1]);
            if(!$a){
                http_response_code(404);
                View::render('placeholder',['title'=>'Não encontrado','heading'=>'Turno não encontrado','user'=>$u]);
            }
            View::render('current_shift',[
                'title'=>'Turno atual',
                'assignment'=>$a,
                'review'=>Data::professionalReviewForAssignment((int)$u['id'],(int)$m[1]),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/profissional/turno/(\\d+)/cancelar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::cancelProfessionalAssignment((int)$u['id'],(int)$m[1],trim((string)($_POST['reason']??'')));
                flash('success','Turno cancelado. A reputação foi tratada de acordo com a antecedência.');
                redirect('profissional/turnos');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('profissional/turno/'.$m[1]);
            }
        }

        if (preg_match('#^/profissional/turno/(\\d+)/avaliar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::createProfessionalReview((int)$u['id'],(int)$m[1],$_POST);
                flash('success','Avaliação da empresa registrada.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/turno/'.$m[1]);
        }

        if (preg_match('#^/profissional/turno/(\d+)/checkin$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::checkIn((int)$u['id'],(int)$m[1],(string)($_POST['pin']??''));
                flash('success','Check-in realizado. Bom turno!');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/turno/'.$m[1]);
        }

        if (preg_match('#^/profissional/turno/(\d+)/checkout$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::checkOut((int)$u['id'],(int)$m[1]);
                flash('success','Turno concluído. O valor foi lançado nos seus ganhos.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/turno/'.$m[1]);
        }

        if ($path === '/profissional/ganhos') {
            $u=Auth::requireRole('professional');
            View::render('earnings',['title'=>'Ganhos','data'=>Data::earnings((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/profissional/reputacao') {
            $u=Auth::requireRole('professional');
            View::render('reputation',['title'=>'Reputação','data'=>Data::reputation((int)$u['id']),'user'=>$u]);
        }

        if (preg_match('#^/profissional/reputacao/(\\d+)/contestar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::appealReputationEvent((int)$u['id'],(int)$m[1]);
                flash('success','Contestação enviada para revisão humana.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/reputacao');
        }

        if ($path === '/profissional/documentos') {
            $u=Auth::requireRole('professional');
            View::render('documents',['title'=>'Documentos','documents'=>Data::documents((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/admin/dashboard') {
            $u=Auth::requireRole('admin');
            View::render('admin_dashboard',[
                'title'=>'Administração',
                'stats'=>Data::adminStats(),
                'appeals'=>Data::adminPendingAppeals(),
                'audit'=>Data::adminAuditLogs(20),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/admin/reputacao/(\\d+)/(aceitar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                Data::resolveReputationAppeal((int)$u['id'],(int)$m[1],$m[2]==='aceitar'?'accepted':'rejected');
                flash('success','Contestação revisada e decisão registrada na auditoria.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/dashboard');
        }

        http_response_code(404);
        $u=Auth::user();
        View::render('placeholder',['title'=>'404','heading'=>'Página não encontrada','user'=>$u]);
    }
}
