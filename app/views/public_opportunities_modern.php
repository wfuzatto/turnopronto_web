<?php
$success=flash('success');
$error=flash('error');
$categories=$categories??[];
$featured=array_slice($opportunities,0,4);
$heroPhoto='https://plus.unsplash.com/premium_photo-1661391652899-ae0e9df22e14?auto=format&fit=crop&fm=jpg&q=85&w=1800';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vagas • TurnoPronto</title><meta name="turnopronto-public-build" content="direto-v2-20261009"><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>&landing=direto-v2-20261009"><script src="<?=e(asset('js/app.js'))?>" defer></script></head>
<body class="tp-modern-public tp-direct-landing <?=!empty($minimal)?'tp-minimal-public':''?>">
<header class="tp-modern-header">
  <div class="tp-modern-wrap tp-modern-header-inner">
    <a href="<?=e(url('vagas'))?>" class="tp-modern-brand"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <nav class="tp-modern-nav">
      <a href="#como-funciona">Como funciona</a>
      <a href="<?=e(url('cadastro/empresa'))?>">Para empresas</a>
      <a href="<?=e(url('suporte'))?>">Ajuda</a>
    </nav>
    <div class="tp-modern-header-actions">
      <a class="btn btn-soft" href="<?=e(url('login'))?>">Entrar</a>
      <a class="btn btn-primary" href="<?=e(url('cadastro/empresa'))?>">Sou empresa</a>
    </div>
  </div>
</header>

<main>
  <?php if($success):?><div class="tp-modern-wrap"><div class="alert success"><?=e($success)?></div></div><?php endif;?>
  <?php if($error):?><div class="tp-modern-wrap"><div class="alert error"><?=e($error)?></div></div><?php endif;?>

  <section class="tp-modern-hero">
    <div class="tp-modern-wrap tp-modern-hero-grid">
      <div class="tp-modern-hero-copy">
        <h1><span class="tp-direct-dark">Veja vagas.</span><br><span class="tp-direct-blue">Trabalhe hoje.</span><br><span class="tp-direct-green">Receba na hora.</span></h1>
        <p>Turnos flexíveis perto de você, com pagamento imediato<br class="tp-direct-desktop-break"> após o fim do turno. Simples, rápido e sem burocracia.</p>

        <form class="tp-modern-search" action="#todas-vagas" method="get" data-modern-job-search role="search" aria-label="Buscar vagas">
          <label><?=icon('map',22)?><span><small>Qual cidade?</small><input name="city" placeholder="Ex.: São Paulo - SP" autocomplete="address-level2" aria-label="Cidade"></span></label>
          <label><?=icon('briefcase',22)?><span><small>Qual função?</small><input name="role" placeholder="Ex.: cozinha, limpeza, garçom" autocomplete="off" aria-label="Função"></span></label>
          <button type="submit">Buscar vagas <b aria-hidden="true">›</b></button>
        </form>

        <div class="tp-direct-benefits" id="como-funciona">
          <div class="tp-direct-benefit"><span class="tp-direct-benefit-icon tp-direct-lightning" aria-hidden="true"><svg class="tp-direct-bolt-svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"><path d="M13 2 4 13h7l-1 9 10-12h-7V2Z"/></svg></span><div><strong>Pagamento imediato</strong><small>após o fim do turno</small></div></div>
          <div class="tp-direct-benefit"><span class="tp-direct-benefit-icon"><?=icon('calendar',26)?></span><div><strong>Turnos flexíveis</strong><small>diurnos e noturnos</small></div></div>
          <div class="tp-direct-benefit"><span class="tp-direct-benefit-icon"><?=icon('map',26)?></span><div><strong>Vagas perto de você</strong><small>na sua cidade</small></div></div>
          <div class="tp-direct-benefit"><span class="tp-direct-benefit-icon"><?=icon('users',26)?></span><div><strong>Cadastro gratuito</strong><small>e sem burocracia</small></div></div>
        </div>
      </div>

      <div class="tp-modern-hero-visual" aria-label="Oportunidades para trabalhar em sua região">
        <div class="tp-modern-photo" style="background-image:url('<?=e($heroPhoto)?>')"></div>
        <div class="tp-direct-pay-card">
          <span class="tp-direct-pay-accent" aria-hidden="true"><i></i><i></i><i></i></span>
          <div class="tp-direct-pay-heading"><span class="tp-direct-pay-bolt" aria-hidden="true"><svg class="tp-direct-bolt-svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"><path d="M13 2 4 13h7l-1 9 10-12h-7V2Z"/></svg></span><div><strong>Recebimento<br><em>imediato</em></strong><small>após o fim do turno</small></div></div>
          <ul class="tp-direct-pay-checks">
            <li><span aria-hidden="true">✓</span> Trabalhe hoje</li>
            <li><span aria-hidden="true">✓</span> Termine seu turno</li>
            <li><span aria-hidden="true">✓</span> Receba na hora</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="tp-modern-featured">
    <div class="tp-modern-wrap">
      <div class="tp-modern-section-head">
        <div><h2>Vagas em destaque</h2><p>Turnos com pagamento imediato, perto de você.</p></div>
        <a href="#todas-vagas">Ver todas as vagas <span aria-hidden="true">→</span></a>
      </div>
      <div class="tp-modern-feature-grid">
        <?php foreach($featured as $index=>$s):?>
          <article class="tp-modern-feature-card" data-modern-job data-shift-id="<?=e((string)$s['id'])?>" data-search="<?=e(mb_strtolower(($s['category_name']??'').' '.($s['title']??'').' '.($s['company_name']??'').' '.($s['city']??'').' '.($s['state']??''),'UTF-8'))?>" data-category="<?=e(mb_strtolower((string)($s['category_name']??''),'UTF-8'))?>">
            <div class="tp-modern-feature-top">
              <div class="tp-modern-job-thumb thumb-<?=($index%3)+1?>"><?php if(!empty($s['image_url'])):?><img src="<?=e($s['image_url'])?>" alt="<?=e($s['title']?:$s['category_name'])?>"><?php elseif(!empty($s['company_logo'])):?><img src="<?=e($s['company_logo'])?>" alt="<?=e($s['company_name'])?>"><?php else:?><span><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?></span><?php endif;?></div>
              <div class="tp-modern-job-info">
                <span class="tp-modern-role-pill"><?=e($s['category_name'])?></span>
                <h3><button class="tp-direct-job-title" type="button" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>" aria-label="Ver detalhes da vaga <?=e($s['title']?:$s['category_name'])?>"><?=e($s['title']?:$s['category_name'])?></button></h3>
                <span><?=icon('map',14)?> <?=e($s['company_name'])?></span>
                <span><?=icon('map',14)?> <?=e($s['city'].' - '.$s['state'])?></span>
              </div>
            </div>
            <div class="tp-modern-job-time">
              <span><?=icon('calendar',16)?> <?=br_date($s['starts_at'],'d/m/Y')?></span>
              <span><?=icon('clock',16)?> <?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></span>
            </div>
            <div class="tp-modern-job-price"><strong><?=money($s['shift_value'])?></strong><span>por turno</span></div>
            <div class="tp-direct-job-payout"><span class="tp-direct-payout-icon" aria-hidden="true"><svg class="tp-direct-bolt-svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"><path d="M13 2 4 13h7l-1 9 10-12h-7V2Z"/></svg></span> Receba ao final do turno</div>
            <div class="tp-modern-job-actions">
              <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>"><?=csrf_field()?><button class="btn btn-primary" type="submit">Tenho interesse <span aria-hidden="true">→</span></button></form>
            </div>
          </article>
        <?php endforeach;?>
        <?php if(!$featured):?>
          <p class="tp-direct-no-vacancies">Novas oportunidades serão publicadas aqui em breve.</p>
        <?php endif;?>
      </div>
    </div>
  </section>

  <section class="tp-modern-all" id="todas-vagas">
    <div class="tp-modern-wrap">
      <div class="tp-modern-all-head">
        <div><h2>Todas as vagas</h2><p>Turnos com pagamento imediato na sua região. <span class="tp-direct-count">(<span data-modern-job-count><?=count($opportunities)?></span> disponíveis)</span></p></div>
      </div>

      <div class="tp-modern-all-list" data-modern-job-list>
        <?php foreach($opportunities as $s):?>
          <article class="tp-modern-row" data-modern-job data-modern-list-job data-shift-id="<?=e((string)$s['id'])?>" data-search="<?=e(mb_strtolower(($s['category_name']??'').' '.($s['title']??'').' '.($s['company_name']??'').' '.($s['city']??'').' '.($s['state']??''),'UTF-8'))?>" data-category="<?=e(mb_strtolower((string)($s['category_name']??''),'UTF-8'))?>">
            <div class="tp-modern-mini-thumb"><?php if(!empty($s['image_url'])):?><img src="<?=e($s['image_url'])?>" alt="<?=e($s['title']?:$s['category_name'])?>"><?php elseif(!empty($s['company_logo'])):?><img src="<?=e($s['company_logo'])?>" alt="<?=e($s['company_name'])?>"><?php else:?><span><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?></span><?php endif;?></div>
            <div class="tp-modern-row-role"><span><?=e($s['category_name'])?></span><strong><?=e($s['title']?:$s['category_name'])?></strong><small><?=icon('briefcase',13)?> <?=e($s['company_name'])?></small></div>
            <div class="tp-modern-row-meta"><?=icon('map',16)?> <?=e($s['city'].' - '.$s['state'])?></div>
            <div class="tp-modern-row-meta"><?=icon('calendar',16)?> <?=br_date($s['starts_at'],'d/m/Y')?></div>
            <div class="tp-modern-row-meta"><?=icon('clock',16)?> <?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></div>
            <div class="tp-modern-row-price"><strong><?=money($s['shift_value'])?></strong><small>por turno</small></div>
            <button class="btn btn-soft" type="button" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>">Ver detalhes</button>
            <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>"><?=csrf_field()?><button class="btn btn-primary" type="submit">Tenho interesse</button></form>
          </article>

          <dialog class="public-job-modal" id="public-job-modal-<?=e((string)$s['id'])?>" aria-labelledby="public-job-modal-title-<?=e((string)$s['id'])?>">
            <div class="public-job-modal-card">
              <div class="public-job-modal-top">
                <div class="public-job-icon large <?=!empty($s['image_url'])?'has-photo':''?>"><?php if(!empty($s['image_url'])):?><img src="<?=e($s['image_url'])?>" alt="<?=e($s['title']?:$s['category_name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?><?php endif;?></div>
                <div class="public-job-modal-heading"><span class="public-job-category"><?=e($s['category_name'])?></span><h2 id="public-job-modal-title-<?=e((string)$s['id'])?>"><?=e($s['title']?:$s['category_name'])?></h2><p><?=e($s['company_name'])?></p></div>
                <button class="public-job-modal-close" type="button" data-public-job-modal-close aria-label="Fechar detalhes">×</button>
              </div>
              <div class="public-job-modal-price"><span>Remuneração</span><strong><?=money($s['shift_value'])?></strong><small>por turno</small></div>
              <div class="public-job-detail-grid public-job-modal-grid">
                <div><small>Data</small><strong><?=br_date($s['starts_at'],'d/m/Y')?></strong></div>
                <div><small>Horário</small><strong><?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></strong></div>
                <div><small>Local</small><strong><?=e($s['city'].' - '.$s['state'])?></strong></div>
                <div><small>Vagas</small><strong><?=e((string)$s['required_workers'])?> pessoa(s)</strong></div>
              </div>
              <?php if(!empty($s['address'])):?><div class="public-job-modal-address"><?=icon('map',16)?><span><?=e($s['address'])?> · <?=e($s['city'].' - '.$s['state'])?></span></div><?php endif;?>
              <?php if(!empty($s['description'])):?><div class="public-job-description"><h3>Sobre a vaga</h3><p><?=nl2br(e($s['description']))?></p></div><?php endif;?>
              <?php if(!empty($s['dress_code'])):?><div class="public-job-description"><h3>Orientação de vestimenta</h3><p><?=e($s['dress_code'])?></p></div><?php endif;?>
              <?php if(!empty($s['notes'])):?><div class="public-job-description"><h3>Orientações</h3><p><?=nl2br(e($s['notes']))?></p></div><?php endif;?>
              <div class="public-job-modal-footer"><div><strong>Gostou desta vaga?</strong><span>Você pode acompanhar sem compromisso ou iniciar sua candidatura.</span></div><div class="public-job-modal-actions">
                <form method="post" action="<?=e(url('vagas/'.$s['id'].'/acompanhar'))?>"><?=csrf_field()?><input type="hidden" name="action" value="follow"><button class="btn btn-soft" type="submit">Acompanhar vaga</button></form>
                <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>"><?=csrf_field()?><button class="btn btn-primary" type="submit">Tenho interesse</button></form>
              </div></div>
            </div>
          </dialog>
        <?php endforeach;?>
      </div>
      <div class="tp-modern-empty" data-modern-empty hidden>Nenhuma vaga encontrada com os filtros informados.</div>
    </div>
  </section>
</main>
<footer class="tp-modern-footer">TurnoPronto · Nós cuidamos do extra que você precisa.</footer>
</body></html>