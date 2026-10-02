<?php
header('X-TurnoPronto-Layout: 2026.10.02.2');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<?php
$current=request_path();
$me=$user??Auth::user();
$role=$me['role']??null;
$companyName=$role==='company'?(Data::companyProfile((int)$me['id'])['trade_name']??$me['name']):($me['name']??'TurnoPronto');
$navCompany=[
 ['empresa/dashboard','home','Dashboard'],['empresa/vagas/nova','plus','Publicar vaga'],['empresa/vagas','briefcase','Minhas vagas'],['empresa/profissionais','users','Profissionais'],['empresa/escalas','calendar','Escalas'],['empresa/financeiro','wallet','Financeiro'],['empresa/avaliacoes','star','Avaliações'],['suporte','help','Suporte']
];
$navPro=[
 ['profissional/inicio','home','Início'],['profissional/oportunidades','briefcase','Oportunidades'],['profissional/turnos','calendar','Meus turnos'],['profissional/agenda','calendar','Agenda'],['profissional/ganhos','chart','Ganhos'],['profissional/reputacao','star','Reputação'],['profissional/documentos','file','Documentos'],['suporte','help','Suporte']
];
$nav=$role==='company'?$navCompany:($role==='professional'?$navPro:[['admin/dashboard','home','Dashboard'],['suporte','help','Suporte']]);
?><!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title??'TurnoPronto')?> • TurnoPronto</title>
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>" data-tp-css="main">
<meta name="turnopronto-layout-build" content="2026.10.02.2">
</head><body data-tp-layout="2026.10.02">
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
      <div class="top-search"><?=icon('search',18)?><input placeholder="<?=$role==='company'?'Buscar profissionais, vagas ou palavras-chave...':'Buscar oportunidades, cidades ou estabelecimentos...'?>"></div>
      <?php $accountHref=$role==='company'?'empresa/conta':($role==='professional'?'profissional/perfil':'admin/dashboard'); ?><div class="top-actions"><button class="icon-btn"><?=icon('bell',21)?><span class="notif">3</span></button><a class="user-chip" href="<?=e(url($accountHref))?>"><div class="avatar-sm"><?=e(mb_strtoupper(mb_substr($companyName,0,1)))?></div><div><strong><?=e($companyName)?></strong><small><?=$role==='company'?'Conta Empresarial':($role==='professional'?'Profissional':'Administrador')?></small></div></a><a class="icon-btn" title="Sair" href="<?=e(url('logout'))?>"><?=icon('logout',19)?></a></div>
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
