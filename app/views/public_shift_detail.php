<?php $success=flash('success'); $error=flash('error'); $following=(bool)($following??false); $viewer=$user??null; ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($shift['category_name'])?> • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head>
<body class="public-jobs-page">
<header class="public-jobs-header">
  <a href="<?=e(url('vagas'))?>" class="public-jobs-brand"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
  <div class="public-jobs-header-actions"><a class="btn btn-soft" href="<?=e(url('vagas'))?>">← Ver outras vagas</a><a class="btn btn-soft" href="<?=e(url('login'))?>">Entrar</a></div>
</header>
<main class="public-jobs-main public-job-detail-main">
  <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
  <section class="panel public-job-detail">
    <div class="public-job-detail-head">
      <div class="public-job-icon large"><?=e(mb_strtoupper(mb_substr($shift['category_name'],0,1)))?></div>
      <div><span class="public-job-category"><?=e($shift['category_name'])?></span><h1><?=e($shift['title']?:$shift['category_name'])?></h1><p><?=e($shift['company_name'])?></p></div>
      <div class="public-job-detail-value"><strong><?=money($shift['shift_value'])?></strong><small>por turno</small></div>
    </div>
    <div class="public-job-detail-grid">
      <div><small>Data</small><strong><?=br_date($shift['starts_at'],'d/m/Y')?></strong></div>
      <div><small>Horário</small><strong><?=date('H:i',strtotime($shift['starts_at']))?> – <?=date('H:i',strtotime($shift['ends_at']))?></strong></div>
      <div><small>Local</small><strong><?=e($shift['city'].' - '.$shift['state'])?></strong></div>
      <div><small>Vagas</small><strong><?=e((string)$shift['required_workers'])?> pessoa(s)</strong></div>
    </div>
    <?php if(!empty($shift['description'])):?><div class="public-job-description"><h2>Sobre a vaga</h2><p><?=nl2br(e($shift['description']))?></p></div><?php endif;?>
    <?php if(!empty($shift['dress_code'])):?><div class="public-job-description"><h2>Orientação de vestimenta</h2><p><?=e($shift['dress_code'])?></p></div><?php endif;?>
    <div class="public-job-interest-box public-job-interest-box-expanded">
      <div>
        <strong>Gostou da vaga?</strong>
        <p>Você pode apenas acompanhar para receber alterações, sem assumir o compromisso do turno.</p>
        <small>Ao acompanhar, a empresa verá seu nome e seus dados de contato para poder conversar com você sobre esta oportunidade.</small>
      </div>
      <div class="public-job-interest-actions">
        <?php if(!$viewer || ($viewer['role']??'')==='professional'):?>
          <form method="post" action="<?=e(url('vagas/'.$shift['id'].'/acompanhar'))?>">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="<?=$following?'unfollow':'follow'?>">
            <button class="btn <?=$following?'btn-soft':'btn-ghost'?>" type="submit"><?=$following?'✓ Acompanhando':'Acompanhar vaga'?></button>
          </form>
        <?php endif;?>
        <form method="post" action="<?=e(url('vagas/'.$shift['id'].'/interesse'))?>">
          <?=csrf_field()?>
          <button class="btn btn-primary" type="submit">Quero me candidatar →</button>
        </form>
      </div>
    </div>
  </section>
</main>
</body></html>