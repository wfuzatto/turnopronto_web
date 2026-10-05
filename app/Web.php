<?php
final class Web
{
    public static function handle(string $path, string $method): never
    {
        if ($path === '/visual-check' && $method === 'GET') {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            View::render('visual_check',[
                'title'=>'Visual Check',
                'user'=>['id'=>0,'role'=>'admin','name'=>'Visual Check']
            ]);
        }

        if ($path === '/login') {
            if ($method === 'POST') {
                verify_csrf();
                if (!Database::available()) {
                    flash('error','Serviço temporariamente indisponível. Tente novamente em alguns instantes.');
                    redirect('login');
                }
                if (Auth::attempt((string)($_POST['email'] ?? ''),(string)($_POST['password'] ?? ''))) {
                    redirect(Auth::dashboardPath(Auth::user()));
                }
                flash('error','E-mail ou senha inválidos.');
                redirect('login');
            }
            View::render('login',['title'=>'Entrar'],false);
        }

        if ($path === '/termos' && $method === 'GET') {
            View::render('legal',[
                'title'=>'Termos de Uso',
                'version'=>(string)(app_config('legal.terms_version') ?? '2026-10-02'),
                'content'=>'<h2>Uso da plataforma</h2><p>O TurnoPronto conecta empresas e profissionais para oportunidades de trabalho pontuais. Dados de identidade, contato, reputação e pagamento devem ser verdadeiros e atualizados.</p><h2>Compromisso com turnos</h2><p>Ao aceitar ou confirmar um turno, as partes assumem o compromisso de cumprir data, horário, valor e condições informadas. Cancelamentos, faltas e atrasos podem gerar registros operacionais e efeitos na reputação, sempre com possibilidade de contestação quando aplicável.</p><h2>Pagamentos</h2><p>Dados Pix são usados para repasses ao profissional e, quando aplicável, devoluções à empresa. A titularidade informada deve corresponder ao usuário ou à empresa responsável.</p>',
            ],false);
        }

        if ($path === '/privacidade' && $method === 'GET') {
            View::render('legal',[
                'title'=>'Política de Privacidade',
                'version'=>(string)(app_config('legal.privacy_version') ?? '2026-10-02'),
                'content'=>'<h2>Dados tratados</h2><p>Tratamos dados cadastrais, CPF/CNPJ, contato, endereço, dados Pix, documentos de verificação, histórico de turnos, avaliações e registros necessários à segurança da plataforma.</p><h2>WhatsApp</h2><p>O número informado é validado por código e pode receber mensagens transacionais relacionadas a cadastro, interesse em vagas, turnos e segurança da conta. Mensagens promocionais exigem base legal/consentimento próprio e não são abrangidas por este aceite transacional.</p><h2>Segurança e direitos</h2><p>O TurnoPronto aplica controles de acesso e auditoria. Solicitações sobre dados pessoais poderão ser tratadas pelos canais oficiais de suporte.</p>',
            ],false);
        }

        if (in_array($path,['/cadastro','/cadastro/empresa','/cadastro/profissional'],true)) {
            if (!Database::available()) {
                flash('error','Serviço temporariamente indisponível. Tente novamente em alguns instantes.');
                redirect('login');
            }
            $kind=$path==='/cadastro/empresa'?'company':($path==='/cadastro/profissional'?'professional':'choice');
            if($method==='POST' && $kind!=='choice'){
                verify_csrf();
                try {
                    $pending=Registration::start($_POST,$kind);
                    $_SESSION['registration_pending']=$pending;
                    redirect('cadastro/verificar');
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('register',[
                'title'=>'Criar conta',
                'kind'=>$kind,
                'categories'=>$kind==='professional'?Data::categories():[],
            ],false);
        }

        if ($path === '/cadastro/verificar') {
            if (!Database::available()) redirect('login');
            $pending=(array)($_SESSION['registration_pending']??[]);
            if(empty($pending['registration_id'])){
                flash('error','Inicie o cadastro antes de validar o telefone.');
                redirect('cadastro');
            }
            if($method==='POST'){
                verify_csrf();
                $action=(string)($_POST['action']??'verify');
                try {
                    if($action==='resend'){
                        $pending=Registration::resend((string)$pending['registration_id']);
                        $_SESSION['registration_pending']=$pending;
                        flash(
                            'success',
                            !empty($pending['development_bypass'])
                                ? 'Modo de desenvolvimento: continue usando o código '.($pending['development_code']??'000111').'. Nenhum WhatsApp foi enviado.'
                                : 'Novo código enviado por WhatsApp.'
                        );
                        redirect('cadastro/verificar');
                    }
                    $verified=Registration::verify((string)$pending['registration_id'],(string)($_POST['code']??''));
                    $userId=(int)($verified['user']['id']??0);
                    if($userId<=0 || !Auth::loginUserId($userId)){
                        throw new RuntimeException('Cadastro concluído, mas não foi possível iniciar a sessão automaticamente.');
                    }
                    unset($_SESSION['registration_pending']);
                    flash('success','Cadastro concluído. Bem-vindo ao TurnoPronto!');
                    redirect(Auth::dashboardPath(Auth::user()));
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('register_verify',[
                'title'=>'Validar WhatsApp',
                'pending'=>$pending,
            ],false);
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
            View::render('not_installed',['title'=>'Serviço indisponível']);
        }

        if ($path === '/empresa/dashboard') {
            $u=Auth::requireRole('company');
            View::render('company_dashboard',['title'=>'Dashboard','data'=>Data::companyDashboard((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/empresa/conta') {
            $u=Auth::requireRole('company');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateCompanyAccount((int)$u['id'],$_POST);
                    flash('success','Dados da empresa atualizados.');
                    redirect('empresa/conta');
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('company_account',[
                'title'=>'Minha conta',
                'company'=>Data::companyProfile((int)$u['id']),
                'user'=>$u
            ]);
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

        if ($path === '/profissional/perfil') {
            $u=Auth::requireRole('professional');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateProfessionalAccount((int)$u['id'],$_POST);
                    flash('success','Perfil atualizado.');
                    redirect('profissional/perfil');
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('professional_account',[
                'title'=>'Meu perfil',
                'profile'=>Data::professionalProfile((int)$u['id']),
                'categories'=>Data::categories(),
                'selected'=>Data::professionalCategoryIds((int)$u['id']),
                'user'=>$u
            ]);
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

        if ($path === '/profissional/documentos/enviar' && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try {
                Data::uploadProfessionalDocument((int)$u['id'],(string)($_POST['type']??''),$_FILES['document']??[]);
                flash('success','Documento enviado para verificação.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/documentos');
        }

        if ($path === '/profissional/documentos') {
            $u=Auth::requireRole('professional');
            View::render('documents',['title'=>'Documentos','documents'=>Data::documents((int)$u['id']),'user'=>$u]);
        }

        if (preg_match('#^/admin/documentos/(\d+)/arquivo$#',$path,$m) && $method==='GET') {
            Auth::requireRole('admin');
            $doc=Data::adminDocument((int)$m[1]);
            if(!$doc || empty($doc['file_path'])){ http_response_code(404); exit('Documento não encontrado.'); }
            $root=realpath(dirname(__DIR__).'/storage/uploads/documents');
            $file=realpath(dirname(__DIR__).'/'.$doc['file_path']);
            if(!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)){ http_response_code(404); exit('Arquivo não encontrado.'); }
            header('Content-Type: '.($doc['mime_type']?:'application/octet-stream'));
            header('Content-Disposition: inline; filename="'.preg_replace('/[^a-zA-Z0-9._-]/','_',($doc['original_name']?:'documento')).'"');
            header('Content-Length: '.filesize($file));
            readfile($file);
            exit;
        }

        if (preg_match('#^/admin/documentos/(\d+)/(aprovar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                Data::setDocumentVerification((int)$u['id'],(int)$m[1],$m[2]==='aprovar'?'verified':'rejected',trim((string)($_POST['reason']??'')));
                flash('success','Documento revisado.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/dashboard');
        }

        if (preg_match('#^/admin/verificacao/(empresa|profissional)/(\d+)/(aprovar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                $decision=$m[3]==='aprovar'?'verified':'rejected';
                if($m[1]==='empresa') Data::setCompanyVerification((int)$u['id'],(int)$m[2],$decision);
                else Data::setProfessionalVerification((int)$u['id'],(int)$m[2],$decision);
                flash('success','Verificação atualizada.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/dashboard');
        }

        if ($path === '/admin/dashboard') {
            $u=Auth::requireRole('admin');
            View::render('admin_dashboard',[
                'title'=>'Administração',
                'stats'=>Data::adminStats(),
                'verification'=>Data::adminPendingVerifications(),
                'documents'=>Data::adminPendingDocuments(),
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
