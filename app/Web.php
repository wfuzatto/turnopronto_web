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
            if ($method === 'GET' && Auth::check()) {
                redirect(Auth::dashboardPath(Auth::user()));
            }
            if ($method === 'POST') {
                verify_csrf();
                if (!Database::available()) {
                    flash('error','Serviço temporariamente indisponível. Tente novamente em alguns instantes.');
                    redirect('login');
                }
                $identifier=(string)($_POST['identifier'] ?? $_POST['email'] ?? '');
                if (Auth::attempt($identifier,(string)($_POST['password'] ?? ''))) {
                    $logged=Auth::user();
                    $followShift=(int)($_SESSION['professional_follow_shift']??0);
                    if(($logged['role']??'')==='professional' && $followShift>0){
                        try{
                            Data::followShift((int)$logged['id'],$followShift);
                            unset($_SESSION['professional_follow_shift']);
                            flash('success','Você está acompanhando esta vaga. A empresa poderá ver seu interesse e você receberá atualizações.');
                            redirect('profissional/vagas/'.$followShift);
                        }catch(Throwable $e){
                            unset($_SESSION['professional_follow_shift']);
                            flash('error',$e->getMessage());
                        }
                    }
                    $targetShift=(int)($_SESSION['professional_target_shift']??0);
                    if(($logged['role']??'')==='professional' && $targetShift>0){
                        $shift=Data::publicShift($targetShift);
                        if($shift){
                            $state=Data::professionalOnboardingState((int)$logged['id']);
                            if($state['application_ready']){
                                try{
                                    $result=Data::acceptShift((int)$logged['id'],$targetShift);
                                    unset($_SESSION['professional_target_shift']);
                                    flash('success',($result['status']??'')==='verification_pending'
                                        ?'Candidatura enviada. Complete sua verificação antes de ser confirmado para o turno.'
                                        :'Candidatura enviada com sucesso.');
                                    redirect('profissional/vagas/'.$targetShift);
                                }catch(Throwable $e){
                                    flash('error',$e->getMessage());
                                    redirect('profissional/vagas/'.$targetShift);
                                }
                            }
                            if($state['basic_complete'] && $state['contact_complete']){
                                flash('success','Sua conta foi encontrada. Falta apenas confirmar os dados de pagamento para enviar esta candidatura.');
                                redirect('cadastro/profissional/pagamento');
                            }
                            $_SESSION['professional_after_onboarding']='profissional/vagas/'.$targetShift;
                            flash('success','Sua conta foi encontrada. Complete os dados que faltam para continuar com esta vaga.');
                            redirect('profissional/completar?step='.$state['next_step']);
                        }
                    }
                    redirect(Auth::dashboardPath($logged));
                }
                flash('error','CPF/e-mail ou senha inválidos.');
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

        if (preg_match('#^/media/perfis/([a-f0-9]{40}\.(?:jpg|png|webp))$#',$path,$m) && $method==='GET') {
            $name=(string)$m[1];
            $absolute=dirname(__DIR__).'/storage/uploads/profile_images/'.$name;
            if(!is_file($absolute)){
                http_response_code(404);
                exit;
            }
            $ext=mb_strtolower((string)pathinfo($name,PATHINFO_EXTENSION));
            $mime=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext]??'application/octet-stream';
            header('Content-Type: '.$mime);
            header('Content-Length: '.(string)filesize($absolute));
            header('Cache-Control: public, max-age=604800, immutable');
            header('X-Content-Type-Options: nosniff');
            readfile($absolute);
            exit;
        }

        if (preg_match('#^/media/vagas/([a-f0-9]{40}\.(?:jpg|png|webp))$#',$path,$m) && $method==='GET') {
            $name=(string)$m[1];
            $absolute=dirname(__DIR__).'/storage/uploads/shift_images/'.$name;
            if(!is_file($absolute)){
                http_response_code(404);
                exit;
            }
            $ext=mb_strtolower((string)pathinfo($name,PATHINFO_EXTENSION));
            $mime=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext]??'application/octet-stream';
            header('Content-Type: '.$mime);
            header('Content-Length: '.(string)filesize($absolute));
            header('Cache-Control: public, max-age=604800, immutable');
            header('X-Content-Type-Options: nosniff');
            readfile($absolute);
            exit;
        }

        if ($path === '/vagas' && $method==='GET') {
            if(!Database::available()) View::render('not_installed',['title'=>'Serviço indisponível'],false);
            $appearance=Data::platformAppearance();
            // Landing V2 fixa para visitantes; skins anteriores permanecem acessíveis para pré-visualização.
            // Evita que uma configuração antiga no banco substitua silenciosamente a nova página pública.
            header('Cache-Control: no-store, max-age=0, must-revalidate');
            header('X-TurnoPronto-Landing: direto-v2-20261009');
            $skin='modern';
            $preview=(string)($_GET['preview_skin']??'');
            if(in_array($preview,['classic','modern','minimal'],true)) $skin=$preview;
            $view=$skin==='classic'?'public_opportunities':($skin==='minimal'?'public_opportunities_minimal':'public_opportunities_modern');
            View::render($view,[
                'title'=>'Vagas',
                'opportunities'=>Data::publicOpportunities(),
                'categories'=>Data::categories(),
                'appearance'=>$appearance,
                'user'=>Auth::check()?Auth::user():null,
            ],false);
        }

        if (preg_match('#^/vagas/(\d+)$#',$path,$m) && $method==='GET') {
            if(!Database::available()) View::render('not_installed',['title'=>'Serviço indisponível'],false);
            $shift=Data::publicShift((int)$m[1]);
            if(!$shift){
                http_response_code(404);
                View::render('public_not_found',['title'=>'Vaga não encontrada'],false);
            }
            $viewer=Auth::check()?Auth::user():null;
            $following=$viewer && ($viewer['role']??'')==='professional'
                ? Data::isFollowingShift((int)$viewer['id'],(int)$m[1])
                : false;
            View::render('public_shift_detail',[
                'title'=>$shift['category_name'],
                'shift'=>$shift,
                'user'=>$viewer,
                'following'=>$following,
            ],false);
        }

        if (preg_match('#^/vagas/(\d+)/acompanhar$#',$path,$m) && $method==='POST') {
            verify_csrf();
            $shift=Data::publicShift((int)$m[1]);
            if(!$shift){
                flash('error','Esta vaga não está mais disponível para acompanhamento.');
                redirect('vagas');
            }

            if(!Auth::check()){
                $_SESSION['professional_follow_shift']=(int)$m[1];
                flash('success','Entre com sua conta profissional para acompanhar esta vaga. Acompanhar não confirma o turno.');
                redirect('login');
            }

            $u=Auth::user();
            if(($u['role']??'')!=='professional'){
                flash('error','O acompanhamento de vagas está disponível para contas de profissionais.');
                redirect('vagas/'.$m[1]);
            }

            try{
                if((string)($_POST['action']??'follow')==='unfollow'){
                    Data::unfollowShift((int)$u['id'],(int)$m[1]);
                    flash('success','Você deixou de acompanhar esta vaga.');
                }else{
                    Data::followShift((int)$u['id'],(int)$m[1]);
                    flash('success','Você está acompanhando esta vaga. A empresa poderá ver seu interesse e seus dados de contato.');
                }
            }catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('vagas/'.$m[1]);
        }

        if (preg_match('#^/vagas/(\d+)/interesse$#',$path,$m) && $method==='POST') {
            verify_csrf();
            $shift=Data::publicShift((int)$m[1]);
            if(!$shift){
                flash('error','Esta vaga não está mais disponível.');
                redirect('vagas');
            }

            if(Auth::check()){
                $u=Auth::user();
                if(($u['role']??'')!=='professional'){
                    flash('error','Para se candidatar, entre com uma conta de profissional.');
                    redirect('vagas/'.$m[1]);
                }
                $state=Data::professionalOnboardingState((int)$u['id']);
                if(!$state['application_ready']){
                    $_SESSION['professional_target_shift']=(int)$m[1];
                    flash('success','Complete as informações que faltam para enviar sua candidatura.');
                    redirect('cadastro/profissional/pagamento');
                }
                try{
                    $result=Data::acceptShift((int)$u['id'],(int)$m[1]);
                    flash('success',($result['status']??'')==='verification_pending'
                        ?'Candidatura enviada. Você já demonstrou interesse; conclua sua verificação antes da confirmação do turno.'
                        :'Candidatura enviada com sucesso.');
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
                redirect('profissional/vagas/'.$m[1]);
            }

            $_SESSION['professional_target_shift']=(int)$m[1];
            unset($_SESSION['professional_registration_draft'],$_SESSION['registration_pending']);
            redirect('cadastro/profissional');
        }

        if ($path === '/cadastro/profissional') {
            if (!Database::available()) redirect('login');
            $shiftId=(int)($_SESSION['professional_target_shift']??($_GET['shift']??0));
            $shift=$shiftId>0?Data::publicShift($shiftId):null;
            if(!$shift){
                flash('success','Escolha uma vaga primeiro. Você só precisará se cadastrar quando quiser se candidatar.');
                redirect('vagas');
            }
            $_SESSION['professional_target_shift']=$shiftId;

            if($method==='POST'){
                verify_csrf();
                try{
                    $_SESSION['professional_registration_draft']=Registration::prepareProfessionalApplicationDraft($_POST,(int)$shift['category_id']);
                    redirect('cadastro/profissional/contato');
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }

            View::render('professional_application_basic',[
                'title'=>'Informações básicas',
                'shift'=>$shift,
            ],false);
        }

        if (in_array($path,['/cadastro/profissional/contato','/cadastro/profissional/whatsapp'],true)) {
            if (!Database::available()) redirect('login');
            $draft=(array)($_SESSION['professional_registration_draft']??[]);
            $shiftId=(int)($_SESSION['professional_target_shift']??0);
            $shift=$shiftId>0?Data::publicShift($shiftId):null;
            if(empty($draft['cpf']) || !$shift){
                flash('error','Escolha uma vaga e comece pelas informações básicas.');
                redirect('vagas');
            }
            if($method==='POST'){
                verify_csrf();
                try{
                    $pending=Registration::startProfessionalFromDraft($draft,(string)($_POST['phone']??''),(string)($_POST['email']??''));
                    $_SESSION['registration_pending']=$pending;
                    $_SESSION['registration_after_verify']='cadastro/profissional/pagamento';
                    unset($_SESSION['professional_registration_draft']);
                    redirect('cadastro/verificar');
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('professional_register_phone',[
                'title'=>'Informações de contato',
                'draft'=>$draft,
                'shift'=>$shift,
                'step_total'=>3,
            ],false);
        }

        if (in_array($path,['/cadastro','/cadastro/empresa'],true)) {
            if (!Database::available()) {
                flash('error','Serviço temporariamente indisponível. Tente novamente em alguns instantes.');
                redirect('login');
            }
            $kind=$path==='/cadastro/empresa'?'company':'choice';
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
                    $after=(string)($_SESSION['registration_after_verify']??'');
                    unset($_SESSION['registration_after_verify']);
                    if(($verified['user']['role']??'')==='professional' && $after==='cadastro/profissional/pagamento' && !empty($_SESSION['professional_target_shift'])){
                        flash('success','WhatsApp confirmado. Falta apenas a etapa de pagamento para enviar sua candidatura.');
                        redirect('cadastro/profissional/pagamento');
                    }
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

        if ($path === '/cadastro/profissional/pagamento') {
            $u=Auth::requireRole('professional');
            $shiftId=(int)($_SESSION['professional_target_shift']??0);
            $shift=$shiftId>0?Data::publicShift($shiftId):null;
            if(!$shift){
                flash('success','Seu cadastro foi salvo. Escolha uma vaga para continuar.');
                redirect('profissional/oportunidades');
            }

            $state=Data::professionalOnboardingState((int)$u['id']);
            if($method==='POST'){
                verify_csrf();
                try{
                    Data::updateProfessionalOnboardingStep((int)$u['id'],'payment',$_POST);
                    $result=Data::acceptShift((int)$u['id'],$shiftId);
                    unset($_SESSION['professional_target_shift']);
                    flash('success',($result['status']??'')==='verification_pending'
                        ?'Candidatura enviada! Seu cadastro básico está pronto. A verificação de identidade pode ser concluída depois, antes da confirmação do turno.'
                        :'Candidatura enviada com sucesso.');
                    redirect('profissional/vagas/'.$shiftId);
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('professional_application_payment',[
                'title'=>'Informações de pagamento',
                'shift'=>$shift,
                'profile'=>$state['profile'],
            ],false);
        }

        if ($path === '/logout') {
            Auth::logout();
            redirect('login');
        }

        if ($path === '/' || $path === '') {
            if(!Auth::check()) redirect('vagas');
            redirect(Auth::dashboardPath(Auth::user()));
        }

        if (!Database::available()) {
            View::render('not_installed',['title'=>'Serviço indisponível']);
        }

        if ($path === '/buscar' && $method==='GET') {
            $u=Auth::requireRole('company','professional','admin');
            $q=trim((string)($_GET['q']??''));
            $results=Data::globalSearch((int)$u['id'],(string)$u['role'],$q,30);
            if((string)($_GET['format']??'')==='json'){
                foreach($results as &$item) $item['url']=url((string)$item['url']);
                unset($item);
                json_response(['ok'=>true,'data'=>['query'=>$q,'items'=>$results]]);
            }
            View::render('search_results',[
                'title'=>$q!==''?'Busca: '.$q:'Busca',
                'query'=>$q,
                'results'=>$results,
                'user'=>$u,
            ]);
        }

        if ($path === '/notificacoes/feed') {
            $u=Auth::requireRole('company','professional','admin');
            $menu=Data::notificationMenu((int)$u['id'],6);
            foreach($menu['items'] as &$item){
                $item['open_url']=url('notificacoes/'.$item['id'].'/abrir');
            }
            unset($item);
            json_response(['ok'=>true,'data'=>$menu]);
        }

        if ($path === '/notificacoes') {
            $u=Auth::requireRole('company','professional','admin');
            View::render('notifications',[
                'title'=>'Notificações',
                'notifications'=>Data::notifications((int)$u['id']),
                'user'=>$u
            ]);
        }

        if ($path === '/notificacoes/ler-todas' && $method==='POST') {
            $u=Auth::requireRole('company','professional','admin');
            verify_csrf();
            Data::markAllNotificationsRead((int)$u['id']);
            $back=trim((string)($_POST['back']??'notificacoes'));
            redirect($back!==''?$back:'notificacoes');
        }

        if (preg_match('#^/notificacoes/(\d+)/abrir$#',$path,$m)) {
            $u=Auth::requireRole('company','professional','admin');
            try {
                $target=Data::openNotification((int)$u['id'],(int)$m[1]);
                redirect($target!==''?$target:Auth::dashboardPath($u));
            } catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('notificacoes');
            }
        }

        if ($path === '/empresa/dashboard') {
            $u=Auth::requireRole('company');
            View::render('company_dashboard',['title'=>'Dashboard','data'=>Data::companyDashboard((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/empresa/verificacao/whatsapp/enviar' && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                $result=Data::requestCompanyPhoneVerification((int)$u['id']);
                if(!empty($result['already_verified'])){
                    flash('success','Seu WhatsApp já está verificado.');
                }elseif(!empty($result['development_bypass'])){
                    flash('success','Código gerado em modo de desenvolvimento: '.($result['development_code']??'000111').'.');
                }else{
                    flash('success','Código enviado para '.$result['phone_masked'].'.');
                }
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/verificacao');
        }

        if ($path === '/empresa/verificacao/whatsapp/confirmar' && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                Data::confirmCompanyPhoneVerification((int)$u['id'],(string)($_POST['code']??''));
                flash('success','WhatsApp verificado com sucesso.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/verificacao');
        }

        if ($path === '/empresa/verificacao/documentos/enviar' && $method==='POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                Data::uploadCompanyVerificationDocument((int)$u['id'],(string)($_POST['type']??''),$_FILES['document']??[]);
                flash('success','Documento enviado para análise.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('empresa/verificacao');
        }

        if ($path === '/empresa/verificacao') {
            $u=Auth::requireRole('company');
            View::render('company_verification',[
                'title'=>'Verificação da empresa',
                'verification'=>Data::companyVerificationSummary((int)$u['id']),
                'user'=>$u
            ]);
        }

        if ($path === '/empresa/conta') {
            $u=Auth::requireRole('company');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateCompanyAccount((int)$u['id'],$_POST,$_FILES['profile_image']??null);
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

        if ($path === '/empresa/categorias' && $method === 'POST') {
            $u=Auth::requireRole('company');
            verify_csrf();
            try {
                $category=Data::createJobCategory((int)$u['id'],(string)($_POST['name']??''));
                $created=(bool)($category['created']??false);
                json_response(['ok'=>true,'created'=>$created,'category'=>$category],$created?201:200);
            } catch(InvalidArgumentException|RuntimeException $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            } catch(Throwable $e){
                json_response(['ok'=>false,'error'=>'Não foi possível cadastrar a categoria agora. Tente novamente.'],500);
            }
        }

        if ($path === '/empresa/vagas/nova') {
            $u=Auth::requireRole('company');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::createShift((int)$u['id'],$_POST,$_FILES['shift_image']??null);
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
                    Data::updateShift((int)$u['id'],(int)$m[1],$_POST,$_FILES['shift_image']??null);
                    flash('success','Vaga atualizada. Profissionais inscritos, confirmados e interessados foram notificados sobre as alterações.');
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
                'followers'=>Data::companyShiftFollowers((int)$u['id'],(int)$m[1]),
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
            redirect($u['role']==='company'?'empresa/suporte':($u['role']==='professional'?'profissional/suporte':'admin/suporte'));
        }

        if (in_array($path,['/empresa/suporte','/profissional/suporte','/admin/suporte'],true) && $method==='GET') {
            $expectedRole=str_starts_with($path,'/empresa/')?'company':(str_starts_with($path,'/profissional/')?'professional':'admin');
            $u=Auth::requireRole($expectedRole);
            View::render('support',[
                'title'=>'Suporte',
                'segment'=>$expectedRole,
                'support'=>Data::supportDashboard((int)$u['id'],$expectedRole),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/(empresa|profissional)/suporte/chamados$#',$path,$m) && $method==='POST') {
            $role=$m[1]==='empresa'?'company':'professional';
            $u=Auth::requireRole($role);
            verify_csrf();
            try{
                $ticketId=Data::createSupportTicket((int)$u['id'],$role,$_POST);
                flash('success','Chamado aberto. Nossa equipe já pode acompanhar sua solicitação.');
                redirect($m[1].'/suporte/chamados/'.$ticketId);
            }catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect($m[1].'/suporte');
            }
        }

        if (preg_match('#^/(empresa|profissional|admin)/suporte/chamados/(\d+)$#',$path,$m) && $method==='GET') {
            $role=$m[1]==='empresa'?'company':($m[1]==='profissional'?'professional':'admin');
            $u=Auth::requireRole($role);
            try{
                View::render('support_ticket',[
                    'title'=>'Chamado #'.(int)$m[2],
                    'segment'=>$role,
                    'detail'=>Data::supportTicket((int)$u['id'],$role,(int)$m[2]),
                    'user'=>$u
                ]);
            }catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect($m[1].'/suporte');
            }
        }

        if (preg_match('#^/(empresa|profissional|admin)/suporte/chamados/(\d+)/atualizacoes$#',$path,$m) && $method==='GET') {
            $role=$m[1]==='empresa'?'company':($m[1]==='profissional'?'professional':'admin');
            $u=Auth::requireRole($role);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            try{
                $updates=Data::supportTicketUpdates(
                    (int)$u['id'],
                    $role,
                    (int)$m[2],
                    max(0,(int)($_GET['after']??0))
                );
                echo json_encode(['ok'=>true,'data'=>$updates],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }catch(Throwable $e){
                http_response_code(403);
                echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            exit;
        }

        if (preg_match('#^/(empresa|profissional|admin)/suporte/chamados/(\d+)/mensagens$#',$path,$m) && $method==='POST') {
            $role=$m[1]==='empresa'?'company':($m[1]==='profissional'?'professional':'admin');
            $u=Auth::requireRole($role);
            verify_csrf();
            $wantsJson=str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json')
                || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';
            try{
                $updates=Data::addSupportMessage((int)$u['id'],$role,(int)$m[2],(string)($_POST['message']??''));
                if($wantsJson){
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    echo json_encode(['ok'=>true,'data'=>$updates],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
            }catch(Throwable $e){
                if($wantsJson){
                    http_response_code(422);
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    exit;
                }
                flash('error',$e->getMessage());
            }
            redirect($m[1].'/suporte/chamados/'.$m[2]);
        }

        if (preg_match('#^/admin/suporte/chamados/(\d+)/status$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try{
                Data::setSupportTicketStatus((int)$u['id'],(int)$m[1],(string)($_POST['status']??''));
                flash('success','Status do chamado atualizado.');
            }catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/suporte/chamados/'.$m[1]);
        }

        if ($path === '/profissional/inicio') {
            $u=Auth::requireRole('professional');
            View::render('professional_dashboard',['title'=>'Início','data'=>Data::professionalHome((int)$u['id']),'user'=>$u]);
        }

        if ($path === '/profissional/completar') {
            $u=Auth::requireRole('professional');
            $state=Data::professionalOnboardingState((int)$u['id']);

            if($method==='POST'){
                verify_csrf();
                $step=(string)($_POST['step']??'');
                try{
                    if($step==='document'){
                        Data::uploadProfessionalDocument((int)$u['id'],'identity',$_FILES['document']??[]);
                    }else{
                        Data::updateProfessionalOnboardingStep((int)$u['id'],$step,$_POST);
                    }

                    $state=Data::professionalOnboardingState((int)$u['id']);
                    if(in_array($state['next_step'],['identity','location','payment','document'],true)){
                        redirect('profissional/completar?step='.$state['next_step']);
                    }

                    $after=(string)($_SESSION['professional_after_onboarding']??'');
                    unset($_SESSION['professional_after_onboarding']);
                    if($state['can_apply']){
                        flash('success',$state['identity_verified']
                            ?'Perfil pronto. Você já pode continuar.'
                            :'Dados concluídos. Seu documento está em análise, mas você já pode demonstrar interesse nas vagas.');
                        if(preg_match('#^profissional/vagas/\d+$#',$after)) redirect($after);
                    }
                    redirect('profissional/inicio');
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }

            $requested=(string)($_GET['step']??'');
            $step=in_array($requested,['identity','location','payment','document','review','final_review','done'],true)
                ? $requested
                : (string)$state['next_step'];
            if(!in_array($step,['identity','location','payment','document','review','final_review','done'],true)) $step='identity';

            View::render('professional_complete',[
                'title'=>'Completar perfil',
                'state'=>$state,
                'step'=>$step,
                'user'=>$u
            ]);
        }

        if ($path === '/profissional/perfil') {
            $u=Auth::requireRole('professional');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateProfessionalAccount((int)$u['id'],$_POST,$_FILES['profile_image']??null);
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
                'onboarding'=>Data::professionalOnboardingState((int)$u['id']),
                'user'=>$u
            ]);
        }

        if ($path === '/profissional/oportunidades') {
            $u=Auth::requireRole('professional');
            View::render('opportunities',[
                'title'=>'Oportunidades',
                'opportunities'=>Data::opportunities((int)$u['id']),
                'onboarding'=>Data::professionalOnboardingState((int)$u['id']),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/profissional/vagas/(\d+)$#',$path,$m)) {
            $u=Auth::requireRole('professional');
            $shift=Data::shift((int)$m[1]);
            if(!$shift){
                http_response_code(404);
                View::render('placeholder',['title'=>'Não encontrada','heading'=>'Vaga não encontrada','user'=>$u]);
            }
            View::render('shift_detail',[
                'title'=>$shift['category_name'],
                'shift'=>$shift,
                'onboarding'=>Data::professionalOnboardingState((int)$u['id']),
                'following'=>Data::isFollowingShift((int)$u['id'],(int)$m[1]),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/profissional/vagas/(\d+)/acompanhar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();
            try{
                if((string)($_POST['action']??'follow')==='unfollow'){
                    Data::unfollowShift((int)$u['id'],(int)$m[1]);
                    flash('success','Você deixou de acompanhar esta vaga.');
                }else{
                    Data::followShift((int)$u['id'],(int)$m[1]);
                    flash('success','Você está acompanhando esta vaga. A empresa poderá ver seu interesse e seus dados de contato.');
                }
            }catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('profissional/vagas/'.$m[1]);
        }

        if (preg_match('#^/profissional/vagas/(\d+)/aceitar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('professional');
            verify_csrf();

            $state=Data::professionalOnboardingState((int)$u['id']);
            if(!$state['application_ready']){
                $_SESSION['professional_target_shift']=(int)$m[1];
                flash('success','Falta pouco. Complete as informações de pagamento para enviar sua candidatura.');
                redirect('cadastro/profissional/pagamento');
            }

            try {
                $result=Data::acceptShift((int)$u['id'],(int)$m[1]);
                if(!empty($result['assignment_id'])){
                    flash('success','Turno confirmado!');
                    redirect('profissional/turno/'.$result['assignment_id']);
                }
                if(($result['status']??'')==='verification_pending'){
                    flash('success','Interesse enviado. Sua identidade ainda está em análise; quando o perfil for liberado, você poderá ser confirmado para o turno.');
                }else{
                    flash('success','Candidatura enviada. A empresa fará a confirmação.');
                }
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
            $back=(string)($_POST['back']??'');
            redirect($back==='profissional/completar'?'profissional/completar':'profissional/documentos');
        }

        if ($path === '/profissional/documentos') {
            $u=Auth::requireRole('professional');
            View::render('documents',['title'=>'Documentos','documents'=>Data::documents((int)$u['id']),'user'=>$u]);
        }

        if (preg_match('#^/admin/verificacao/(empresa|profissional)/(\d+)/campo$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try{
                $result=Data::adminUpdateVerificationField(
                    (int)$u['id'],
                    $m[1]==='empresa'?'company':'professional',
                    (int)$m[2],
                    (string)($_POST['field']??''),
                    (string)($_POST['value']??'')
                );
                json_response(['ok'=>true,'field'=>$result]);
            }catch(Throwable $e){
                json_response(['ok'=>false,'error'=>$e->getMessage()],422);
            }
        }

        if (preg_match('#^/admin/verificacao/empresa/(\d+)$#',$path,$m) && $method==='GET') {
            $u=Auth::requireRole('admin');
            try{
                View::render('admin_verification_detail',[
                    'title'=>'Revisar cadastro empresarial',
                    'detail'=>Data::adminCompanyVerificationDetail((int)$m[1]),
                    'user'=>$u
                ]);
            }catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('admin/dashboard');
            }
        }

        if (preg_match('#^/admin/verificacao/profissional/(\d+)$#',$path,$m) && $method==='GET') {
            $u=Auth::requireRole('admin');
            try{
                View::render('admin_verification_detail',[
                    'title'=>'Revisar cadastro profissional',
                    'detail'=>Data::adminProfessionalVerificationDetail((int)$m[1]),
                    'user'=>$u
                ]);
            }catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('admin/dashboard');
            }
        }

        if (preg_match('#^/admin/verificacao/empresa/(\d+)/documentos/enviar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                Data::adminUploadCompanyVerificationDocument(
                    (int)$u['id'],
                    (int)$m[1],
                    (string)($_POST['type']??''),
                    $_FILES['document']??[]
                );
                flash('success','Documento anexado pelo administrador. Revise o arquivo e aprove quando estiver correto.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/verificacao/empresa/'.$m[1]);
        }

        if (preg_match('#^/admin/documentos/empresa/(\d+)/arquivo$#',$path,$m) && $method==='GET') {
            Auth::requireRole('admin');
            $doc=Data::adminCompanyDocument((int)$m[1]);
            if(!$doc || empty($doc['file_path'])){ http_response_code(404); exit('Documento não encontrado.'); }
            $root=realpath(dirname(__DIR__).'/storage/uploads/company_documents');
            $file=realpath(dirname(__DIR__).'/'.$doc['file_path']);
            if(!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)){ http_response_code(404); exit('Arquivo não encontrado.'); }
            header('Content-Type: '.($doc['mime_type']?:'application/octet-stream'));
            header('Content-Disposition: inline; filename="'.preg_replace('/[^a-zA-Z0-9._-]/','_',($doc['original_name']?:'documento')).'"');
            header('Content-Length: '.filesize($file));
            readfile($file);
            exit;
        }

        if (preg_match('#^/admin/documentos/empresa/(\d+)/(aprovar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                Data::setCompanyDocumentVerification((int)$u['id'],(int)$m[1],$m[2]==='aprovar'?'verified':'rejected',trim((string)($_POST['reason']??'')));
                flash('success','Documento empresarial revisado.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            $back=trim((string)($_POST['back']??''));
            redirect(preg_match('#^admin/verificacao/empresa/\d+$#',$back)?$back:'admin/dashboard');
        }

        if (preg_match('#^/admin/verificacao/profissional/(\d+)/documentos/enviar$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                Data::adminUploadProfessionalDocument(
                    (int)$u['id'],
                    (int)$m[1],
                    (string)($_POST['type']??''),
                    $_FILES['document']??[]
                );
                flash('success','Documento anexado pelo administrador. Revise o arquivo e aprove quando estiver correto.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            redirect('admin/verificacao/profissional/'.$m[1]);
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
            $back=trim((string)($_POST['back']??''));
            redirect(preg_match('#^admin/verificacao/profissional/\d+$#',$back)?$back:'admin/dashboard');
        }

        if (preg_match('#^/admin/verificacao/(empresa|profissional)/(\d+)/(aprovar|rejeitar)$#',$path,$m) && $method==='POST') {
            $u=Auth::requireRole('admin');
            verify_csrf();
            try {
                $decision=$m[3]==='aprovar'?'verified':'rejected';
                if($m[1]==='empresa') Data::setCompanyVerification((int)$u['id'],(int)$m[2],$decision);
                else Data::setProfessionalVerification((int)$u['id'],(int)$m[2],$decision);
                flash('success',$decision==='verified'?'Cadastro aprovado e liberado.':'Cadastro rejeitado.');
            } catch(Throwable $e){
                flash('error',$e->getMessage());
            }
            $back=trim((string)($_POST['back']??''));
            $expected='admin/verificacao/'.$m[1].'/'.$m[2];
            redirect($back===$expected?$back:'admin/dashboard');
        }

        if ($path === '/admin/vagas') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Vagas',
                'section'=>'shifts',
                'items'=>Data::adminShifts(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/candidatos') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Candidatos',
                'section'=>'candidates',
                'items'=>Data::adminProfessionals(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/empresas') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Empresas',
                'section'=>'companies',
                'items'=>Data::adminCompanies(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/usuarios') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Usuários',
                'section'=>'users',
                'items'=>Data::adminUsers(),
                'user'=>$u
            ]);
        }

        if (preg_match('#^/admin/usuarios/(\d+)$#',$path,$m) && $method==='GET') {
            $u=Auth::requireRole('admin');
            try{
                View::render('admin_user_detail',[
                    'title'=>'Usuário',
                    'detail'=>Data::adminUserDetail((int)$m[1]),
                    'user'=>$u
                ]);
            }catch(Throwable $e){
                flash('error',$e->getMessage());
                redirect('admin/usuarios');
            }
        }

        if ($path === '/admin/locais') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Locais',
                'section'=>'locations',
                'items'=>Data::adminLocations(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/relatorios') {
            $u=Auth::requireRole('admin');
            View::render('admin_directory',[
                'title'=>'Relatórios',
                'section'=>'reports',
                'report'=>Data::adminReportSummary(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/configuracoes') {
            $u=Auth::requireRole('admin');
            if($method==='POST'){
                verify_csrf();
                try{
                    $payload=$_POST;
                    $activate=(string)($payload['activate']??'');
                    if(in_array($activate,['classic','modern','minimal'],true)) $payload['web_skin']=$activate;
                    Data::savePlatformAppearance((int)$u['id'],$payload);
                    flash('success','Configurações de aparência atualizadas.');
                    redirect('admin/configuracoes');
                }catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('admin_platform_settings',[
                'title'=>'Configurações da plataforma',
                'appearance'=>Data::platformAppearance(),
                'user'=>$u
            ]);
        }

        if ($path === '/admin/conta') {
            $u=Auth::requireRole('admin');
            if($method==='POST'){
                verify_csrf();
                try {
                    Data::updateAdminAccount((int)$u['id'],$_POST);
                    flash('success','Conta administrativa atualizada.');
                    redirect('admin/conta');
                } catch(Throwable $e){
                    flash('error',$e->getMessage());
                }
            }
            View::render('admin_account',[
                'title'=>'Minha conta',
                'user'=>$u
            ]);
        }

        if ($path === '/admin/dashboard') {
            $u=Auth::requireRole('admin');
            View::render('admin_dashboard',[
                'title'=>'Administração',
                'stats'=>Data::adminStats(),
                'verification'=>Data::adminPendingVerifications(),
                'companyDocuments'=>Data::adminPendingCompanyDocuments(),
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
