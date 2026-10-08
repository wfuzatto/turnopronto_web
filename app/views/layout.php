<?php

header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<?php
$current=request_path();
$me=$user??Auth::user();
$role=$me['role']??null;
$companyName=$role==='company'?(Data::companyProfile((int)$me['id'])['trade_name']??$me['name']):($me['name']??'TurnoPronto');
$notificationMenu=$me?Data::notificationMenu((int)$me['id'],6):['unread'=>0,'items'=>[]];
$navCompany=[
 ['empresa/dashboard','home','Dashboard'],['empresa/verificacao','shield','Verificação'],['empresa/vagas/nova','plus','Publicar vaga'],['empresa/vagas','briefcase','Minhas vagas'],['empresa/profissionais','users','Profissionais'],['empresa/escalas','calendar','Escalas'],['empresa/financeiro','wallet','Financeiro'],['empresa/avaliacoes','star','Avaliações'],['empresa/suporte','help','Suporte']
];
$navPro=[
 ['profissional/inicio','home','Início'],['profissional/oportunidades','briefcase','Oportunidades'],['profissional/turnos','calendar','Meus turnos'],['profissional/agenda','calendar','Agenda'],['profissional/ganhos','chart','Ganhos'],['profissional/reputacao','star','Reputação'],['profissional/documentos','file','Documentos'],['profissional/suporte','help','Suporte']
];
$navAdmin=[
 ['admin/dashboard','home','Painel'],
 ['admin/dashboard#vagas','briefcase','Vagas'],
 ['admin/dashboard#candidatos','users','Candidatos'],
 ['admin/dashboard#empresas','file','Empresas'],
 ['admin/dashboard#locais','map','Locais'],
 ['admin/dashboard#relatorios','chart','Relatórios'],
 ['admin/configuracoes','settings','Configurações'],
 ['admin/suporte','help','Suporte']
];
$nav=$role==='company'?$navCompany:($role==='professional'?$navPro:$navAdmin);
?><!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title??'TurnoPronto')?> • TurnoPronto</title>
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>" data-tp-css="main">
<meta name="turnopronto-layout-build" content="2026.10.02.data-fix">
</head><body data-tp-layout="2026.10.02.data-fix">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?=e(url(Auth::check()?Auth::dashboardPath($me):'login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <nav class="side-nav">
      <?php foreach($nav as [$href,$ico,$label]): $active=str_starts_with(trim($current,'/'),$href) || ($href==='empresa/vagas'&&$current==='/empresa/vagas'); ?>
      <a class="side-link <?=$active?'active':''?>" href="<?=e(url($href))?>"><?=icon($ico,20)?><span><?=e($label)?></span></a>
      <?php endforeach;?>
    </nav>
    <div class="side-promo">
      <div class="promo-avatar">TP</div>
      <strong><?=$role==='company'?'Equipe extra quando você precisa':'Mais oportunidades para você!'?></strong>
      <p><?=$role==='company'?'Profissionais qualificados para o seu negócio.':'Complete seu perfil e aumente suas chances de ser chamado.'?></p>
      <a href="<?=e(url($role==='company'?'empresa/vagas/nova':'profissional/oportunidades'))?>" class="btn btn-primary btn-block"><?=$role==='company'?'Publicar nova vaga':'Ver oportunidades'?> →</a>
    </div>
  </aside>
  <section class="workspace">
    <header class="topbar">
      <button class="mobile-menu" data-sidebar-toggle aria-label="Menu">☰</button>
      <form class="top-search" action="<?=e(url('buscar'))?>" method="get" role="search" data-global-search>
        <?=icon('search',18)?>
        <input name="q" value="<?=e($current==='/buscar'?(string)($_GET['q']??''):'')?>" placeholder="<?=$role==='company'?'Buscar profissionais, vagas ou palavras-chave...':($role==='admin'?'Buscar empresas, profissionais ou vagas...':'Buscar oportunidades, cidades ou estabelecimentos...')?>" autocomplete="off" spellcheck="false" data-global-search-input>
        <button class="top-search-submit" type="submit" aria-label="Buscar"><?=icon('search',16)?></button>
        <div class="global-search-dropdown" data-global-search-dropdown hidden></div>
      </form>
      <?php
        $accountHref=$role==='company'?'empresa/conta':($role==='professional'?'profissional/perfil':'admin/conta');
        $notificationIcon=function(string $type): string {
            return match($type){
                'matching_shift'=>'briefcase',
                'invitation','application'=>'users',
                'application_approved'=>'check',
                'application_rejected'=>'file',
                'support_ticket','support_reply'=>'help',
                default=>'bell',
            };
        };
      ?>
      <div class="top-actions">
        <div class="notification-shell" data-notifications>
          <button class="icon-btn notification-toggle" type="button" data-notifications-toggle aria-label="Notificações" aria-expanded="false">
            <?=icon('bell',21)?>
            <span class="notif" data-notification-badge <?=$notificationMenu['unread']<=0?'hidden':''?>><?=$notificationMenu['unread']>99?'99+':(int)$notificationMenu['unread']?></span>
          </button>
          <div class="notification-dropdown" data-notifications-panel hidden>
            <div class="notification-dropdown-head">
              <div><strong>Notificações</strong><small data-notification-summary><?=$notificationMenu['unread']?> não lida(s)</small></div>
              <?php if($notificationMenu['unread']>0):?><form method="post" action="<?=e(url('notificacoes/ler-todas'))?>"><?=csrf_field()?><input type="hidden" name="back" value="<?=e(ltrim($current,'/'))?>"><button type="submit">Marcar todas como lidas</button></form><?php endif;?>
            </div>
            <div class="notification-dropdown-list" data-notification-list>
              <?php if(!$notificationMenu['items']):?><div class="notification-empty">Nenhuma notificação por enquanto.</div><?php endif;?>
              <?php foreach($notificationMenu['items'] as $n):?>
                <a class="notification-item <?=empty($n['read_at'])?'unread':''?>" data-notification-id="<?=e((string)$n['id'])?>" href="<?=e(url('notificacoes/'.$n['id'].'/abrir'))?>">
                  <span class="notification-item-icon"><?=icon($notificationIcon((string)$n['type']),17)?></span>
                  <span class="notification-item-copy"><strong><?=e($n['title'])?></strong><span><?=e($n['body'])?></span><small><?=date('d/m/Y H:i',strtotime($n['created_at']))?></small></span>
                </a>
              <?php endforeach;?>
            </div>
            <a class="notification-all-link" href="<?=e(url('notificacoes'))?>">Ver todas as notificações →</a>
          </div>
        </div>
        <a class="user-chip" href="<?=e(url($accountHref))?>" title="Abrir meu cadastro">
          <div class="avatar-sm"><?=e(mb_strtoupper(mb_substr($companyName,0,1)))?></div>
          <div><strong><?=e($companyName)?></strong><small><?=$role==='company'?'Conta Empresarial':($role==='professional'?'Profissional':'Administrador')?></small></div>
        </a>
        <a class="icon-btn" title="Sair" href="<?=e(url('logout'))?>"><?=icon('logout',19)?></a>
      </div>
    </header>
    <main class="main-content">
      <?php if($msg=flash('success')):?><div class="alert success"><?=e($msg)?></div><?php endif;?>
      <?php if($msg=flash('error')):?><div class="alert error"><?=e($msg)?></div><?php endif;?>
      <?=$content?>
    </main>
  </section>
</div>
<script>window.TP_BASE=<?=json_encode(base_path())?>;</script>
<script src="<?=e(asset('js/app.js'))?>"></script>
</body></html>
