<?php $success=flash('success'); $error=flash('error'); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vagas • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"><script src="<?=e(asset('js/app.js'))?>" defer></script></head>
<body class="public-jobs-page">
<header class="public-jobs-header">
  <a href="<?=e(url('vagas'))?>" class="public-jobs-brand"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
  <div class="public-jobs-header-actions">
    <span>Você pode ver todas as vagas sem cadastro.</span>
    <a class="btn btn-soft" href="<?=e(url('login'))?>">Entrar</a>
    <a class="btn btn-primary" href="<?=e(url('cadastro/empresa'))?>">Sou empresa</a>
  </div>
</header>
<main class="public-jobs-main">
  <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
  <section class="public-jobs-hero">
    <div>
      <span class="public-jobs-kicker">Oportunidades de trabalho extra</span>
      <h1>Veja as vagas primeiro.<br>Cadastre-se só quando quiser se candidatar.</h1>
      <p>Explore horários, valores, empresas e locais sem preencher nada. O cadastro começa apenas quando você clicar em <strong>Tenho interesse</strong>.</p>
    </div>
    <div class="public-jobs-flow">
      <span><b>1</b> Informações básicas</span>
      <span><b>2</b> Contato</span>
      <span><b>3</b> Pagamento</span>
    </div>
  </section>

  <section class="public-jobs-section">
    <div class="public-jobs-section-head"><div><h2>Vagas disponíveis</h2><p><?=count($opportunities)?> oportunidade(s) aberta(s)</p></div></div>
    <?php if(!$opportunities):?><div class="panel public-jobs-empty"><strong>Nenhuma vaga disponível agora.</strong><p>Novas oportunidades aparecem aqui assim que forem publicadas.</p></div><?php endif;?>
    <div class="public-job-list">
      <?php foreach($opportunities as $s):?>
        <article class="public-job-card">
          <div class="public-job-icon <?=!empty($s['image_url'])?'has-photo':''?>"><?php if(!empty($s['image_url'])):?><img src="<?=e($s['image_url'])?>" alt="<?=e($s['title']?:$s['category_name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?><?php endif;?></div>
          <div class="public-job-main">
            <span class="public-job-category"><?=e($s['category_name'])?></span>
            <h3><?=e($s['title']?:$s['category_name'])?></h3>
            <strong><?=e($s['company_name'])?></strong>
            <div class="public-job-meta">
              <span><?=icon('calendar',15)?> <?=br_date($s['starts_at'],'d/m/Y')?></span>
              <span><?=icon('clock',15)?> <?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></span>
              <span><?=icon('map',15)?> <?=e($s['city'].' - '.$s['state'])?></span>
            </div>
          </div>
          <div class="public-job-value"><strong><?=money($s['shift_value'])?></strong><small>por turno</small></div>
          <div class="public-job-actions">
            <button class="btn btn-soft" type="button" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>">Ver detalhes</button>
            <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>"><?=csrf_field()?><button class="btn btn-primary" type="submit">Tenho interesse</button></form>
          </div>
        </article>

        <dialog class="public-job-modal" id="public-job-modal-<?=e((string)$s['id'])?>" aria-labelledby="public-job-modal-title-<?=e((string)$s['id'])?>">
          <div class="public-job-modal-card">
            <div class="public-job-modal-top">
              <div class="public-job-icon large <?=!empty($s['image_url'])?'has-photo':''?>"><?php if(!empty($s['image_url'])):?><img src="<?=e($s['image_url'])?>" alt="<?=e($s['title']?:$s['category_name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?><?php endif;?></div>
              <div class="public-job-modal-heading">
                <span class="public-job-category"><?=e($s['category_name'])?></span>
                <h2 id="public-job-modal-title-<?=e((string)$s['id'])?>"><?=e($s['title']?:$s['category_name'])?></h2>
                <p><?=e($s['company_name'])?></p>
              </div>
              <button class="public-job-modal-close" type="button" data-public-job-modal-close aria-label="Fechar detalhes">×</button>
            </div>

            <div class="public-job-modal-price">
              <span>Remuneração</span>
              <strong><?=money($s['shift_value'])?></strong>
              <small>por turno</small>
            </div>

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

            <div class="public-job-modal-footer">
              <div>
                <strong>Gostou desta vaga?</strong>
                <span>Você pode acompanhar sem compromisso ou iniciar sua candidatura.</span>
              </div>
              <div class="public-job-modal-actions">
                <form method="post" action="<?=e(url('vagas/'.$s['id'].'/acompanhar'))?>">
                  <?=csrf_field()?>
                  <input type="hidden" name="action" value="follow">
                  <button class="btn btn-soft" type="submit">Acompanhar vaga</button>
                </form>
                <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>">
                  <?=csrf_field()?>
                  <button class="btn btn-primary" type="submit">Tenho interesse</button>
                </form>
              </div>
            </div>
          </div>
        </dialog>
      <?php endforeach;?>
    </div>
  </section>
</main>
<footer class="public-jobs-footer">TurnoPronto · Nós cuidamos do extra que você precisa.</footer>
</body></html>