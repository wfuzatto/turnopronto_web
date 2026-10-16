<?php
$success=flash('success');
$error=flash('error');
$opportunities=$opportunities??[];
$featured=array_slice($opportunities,0,4);
$categoryNames=[];
foreach($opportunities as $job){
    $name=trim((string)($job['category_name']??''));
    if($name!=='' && !in_array($name,$categoryNames,true)) $categoryNames[]=$name;
}
sort($categoryNames,SORT_NATURAL|SORT_FLAG_CASE);
$heroPhoto='https://plus.unsplash.com/premium_photo-1661391652899-ae0e9df22e14?auto=format&fit=crop&fm=jpg&q=85&w=1800';
$today=date('Y-m-d');
$tomorrow=date('Y-m-d',strtotime('+1 day'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Encontre turnos flexíveis, veja quanto vai receber e candidate-se a oportunidades perto de você.">
  <meta name="turnopronto-public-build" content="direto-v2-20261009">
  <meta name="turnopronto-visual-build" content="ux-v3-20261014">
  <title>Vagas • TurnoPronto</title>
  <link rel="stylesheet" href="<?=e(asset('css/app.css'))?>&landing=direto-v2-20261009">
  <link rel="stylesheet" href="<?=e(asset('css/landing-ux-v3.css'))?>">
  <script src="<?=e(asset('js/app.js'))?>" defer></script>
  <script src="<?=e(asset('js/landing-ux-v3.js'))?>" defer></script>
</head>
<body class="tp-modern-public tp-direct-landing tp3">
  <a class="tp3-skip" href="#todas-vagas">Pular para as vagas</a>
  <header class="tp3-header">
    <div class="tp3-wrap tp3-header-inner">
      <a href="<?=e(url('vagas'))?>" class="tp3-brand" aria-label="Página inicial TurnoPronto"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
      <nav class="tp3-nav" aria-label="Navegação principal">
        <a class="tp3-active" href="#todas-vagas" aria-current="page">Vagas</a>
        <a href="#como-funciona">Como funciona</a>
        <a href="<?=e(url('cadastro/empresa'))?>">Para empresas</a>
        <a href="<?=e(url('suporte'))?>">Ajuda</a>
      </nav>
      <div class="tp3-head-actions">
        <a class="tp3-login" href="<?=e(url('login'))?>">Entrar</a>
        <a class="tp3-company" href="<?=e(url('cadastro/empresa'))?>">Sou empresa</a>
      </div>
    </div>
  </header>

  <main>
    <?php if($success):?><div class="tp3-wrap"><div class="alert success" role="status"><?=e($success)?></div></div><?php endif;?>
    <?php if($error):?><div class="tp3-wrap"><div class="alert error" role="alert"><?=e($error)?></div></div><?php endif;?>
    <section class="tp3-hero" aria-labelledby="tp3-headline">
        <div class="tp3-hero-photo" style="--tp3-photo:url('<?=e($heroPhoto)?>')" role="img" aria-label="Profissional de atendimento em ambiente de trabalho">
          <div class="tp3-pay-card">
            <div class="tp3-pay-title"><span class="tp3-pay-circle" aria-hidden="true"><svg class="tp3-bolt-svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"><path d="M13 2 4 13h7l-1 9 10-12h-7V2Z"/></svg></span><div><strong>Recebimento <em>imediato</em></strong><small>após o fim do turno</small></div></div>
            <ul>
              <li><span aria-hidden="true">✓</span> Trabalhe hoje</li>
              <li><span aria-hidden="true">✓</span> Turnos flexíveis</li>
              <li><span aria-hidden="true">✓</span> Perto de você</li>
              <li><span aria-hidden="true">✓</span> Sem burocracia</li>
            </ul>
          </div>
        </div>
      <div class="tp3-wrap tp3-hero-inner">
        <div class="tp3-hero-copy">
          <h1 id="tp3-headline"><span class="tp3-dark">Veja vagas.</span><br><span class="tp3-blue">Trabalhe hoje.</span><br><span class="tp3-green">Receba na hora.</span></h1>
          <p>Turnos flexíveis perto de você, com pagamento imediato após o fim do turno. Simples, rápido e sem burocracia.</p>
          <form class="tp3-search" data-tp3-search role="search" action="#todas-vagas" method="get">
            <label class="tp3-search-field tp3-search-role">
              <?=icon('search',25)?>
              <span><strong>Qual vaga você procura?</strong><input name="role" type="search" autocomplete="off" placeholder="Ex.: garçom, limpeza, cozinha..." aria-label="Buscar por função, vaga ou empresa"></span>
            </label>
            <label class="tp3-search-field tp3-search-city">
              <?=icon('map',25)?>
              <span><strong>Sua cidade</strong><input name="city" type="search" autocomplete="address-level2" placeholder="Todas as cidades" aria-label="Filtrar por cidade ou estado"></span>
            </label>
            <label class="tp3-search-field tp3-search-date">
              <?=icon('calendar',25)?>
              <span><strong>Quando?</strong><input name="date" type="date" min="<?=e($today)?>" aria-label="Filtrar por data"><small>Qualquer data</small></span>
            </label>
            <button type="submit">Buscar vagas <span aria-hidden="true">→</span></button>
          </form>

        </div>

      </div>
    </section>
    <section class="tp3-discovery tp3-wrap" aria-label="Filtrar por profissão">
      <div class="tp3-categories" data-tp3-categories role="group" aria-label="Categorias de vagas">
        <button type="button" class="tp3-chip active" data-tp3-category="" aria-pressed="true"><?=icon('briefcase',19)?> Todas</button>
        <?php foreach($categoryNames as $category):?>
          <button type="button" class="tp3-chip" data-tp3-category="<?=e($category)?>" aria-pressed="false"><?=icon('briefcase',18)?> <?=e($category)?></button>
        <?php endforeach;?>
      </div>
    </section>

    <section class="tp3-feature-section tp3-wrap" aria-labelledby="tp3-feature-title">
      <div class="tp3-feature-layout">
        <div class="tp3-feature-main">
          <div class="tp3-section-head">
            <div><h2 id="tp3-feature-title"><span class="tp3-star" aria-hidden="true">★</span> Vagas em destaque</h2><p>Turnos com pagamento imediato, perto de você.</p></div>
            <a href="#todas-vagas">Ver todas as vagas <span aria-hidden="true">→</span></a>
          </div>
          <div class="tp3-feature-grid" data-tp3-feature-list>
            <?php foreach($featured as $index=>$s):
              $date=substr((string)$s['starts_at'],0,10);
              $badge=$date===$today?'Hoje':($date===$tomorrow?'Amanhã':'Próximos dias');
              $jobName=(string)($s['title']?:$s['category_name']);
              $searchData=mb_strtolower(implode(' ',[$s['category_name']??'',$jobName,$s['company_name']??'',$s['city']??'',$s['state']??'']),'UTF-8');
            ?>
            <article class="tp3-feature-card" data-tp3-job data-tp3-feature data-search="<?=e($searchData)?>" data-city="<?=e((string)($s['city']??'').' '.(string)($s['state']??''))?>" data-category="<?=e((string)($s['category_name']??''))?>" data-date="<?=e($date)?>">
              <div class="tp3-feature-media">
                <?php if(!empty($s['image_url'])):?><img loading="lazy" src="<?=e($s['image_url'])?>" alt="Foto da vaga <?=e($jobName)?>">
                <?php elseif(!empty($s['company_logo'])):?><img loading="lazy" src="<?=e($s['company_logo'])?>" alt="Logo de <?=e($s['company_name'])?>">
                <?php else:?><span class="tp3-media-placeholder"><?=icon('briefcase',43)?><small><?=e($s['category_name'])?></small></span><?php endif;?>
                <span class="tp3-date-badge <?=$date===$today?'today':($date===$tomorrow?'tomorrow':'upcoming')?>"><?=e($badge)?></span>
              </div>
              <div class="tp3-feature-content">
                <h3><button type="button" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>"><?=e($jobName)?></button></h3>
                <p class="tp3-job-company"><?=e($s['company_name'])?></p>
                <p class="tp3-job-detail"><?=icon('map',16)?> <?=e($s['city'].' - '.$s['state'])?></p>
                <p class="tp3-job-detail"><?=icon('clock',16)?> <?=br_date($s['starts_at'],'d/m/Y')?> · <?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></p>
                <div class="tp3-feature-footer">
                  <div><strong class="tp3-price"><?=money($s['shift_value'])?></strong><span class="tp3-paid"><?=icon('check',13)?> Pagamento imediato</span></div>
                  <button type="button" class="tp3-arrow" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>" aria-label="Ver detalhes de <?=e($jobName)?>">→</button>
                </div>
              </div>
            </article>
            <?php endforeach;?>
          </div>
          <?php if(!$featured):?>
          <div class="tp3-feature-empty" data-tp3-feature-empty>As próximas oportunidades aparecerão aqui assim que forem publicadas.</div>
          <?php else:?>
          <div class="tp3-feature-empty" data-tp3-feature-empty hidden>Nenhuma vaga em destaque corresponde à pesquisa. Veja as demais opções abaixo.</div>
          <?php endif;?>
        </div>

        <aside class="tp3-how" id="como-funciona" aria-labelledby="tp3-how-title">
          <h2 id="tp3-how-title">Como funciona?</h2>
          <ol>
            <li><b class="tp3-step-number tp3-step-blue">1</b><span class="tp3-step-icon"><?=icon('file',20)?></span><div><strong>Encontre uma vaga</strong><small>Busque por cidade, área e data.</small></div></li>
            <li><b class="tp3-step-number tp3-step-green">2</b><span class="tp3-step-icon"><?=icon('check',20)?></span><div><strong>Se candidate</strong><small>Cadastro somente quando demonstrar interesse.</small></div></li>
            <li><b class="tp3-step-number tp3-step-purple">3</b><span class="tp3-step-icon"><?=icon('briefcase',20)?></span><div><strong>Trabalhe seu turno</strong><small>Compareça ao local e cumpra o turno.</small></div></li>
            <li><b class="tp3-step-number tp3-step-orange">4</b><span class="tp3-step-icon"><?=icon('wallet',20)?></span><div><strong>Receba na hora</strong><small>Pagamento ao final do turno.</small></div></li>
          </ol>
          <a href="#todas-vagas" class="tp3-how-cta">Quero trabalhar <span aria-hidden="true">→</span></a>
          <p>Você pode explorar as oportunidades antes de se cadastrar.</p>
        </aside>
      </div>
    </section>

    <section class="tp3-all tp3-wrap" id="todas-vagas" aria-labelledby="tp3-all-title">
      <div class="tp3-all-header">
        <div><h2 id="tp3-all-title"><?=icon('briefcase',24)?> Todas as vagas</h2><p>Turnos disponíveis na sua região. <span>(<strong data-tp3-count><?=count($opportunities)?></strong> disponíveis)</span></p></div>
        <div class="tp3-all-tools">
          <label for="tp3-sort">Ordenar por:</label>
          <select id="tp3-sort" data-tp3-sort aria-label="Ordenar vagas">
            <option value="data">Data mais próxima</option>
            <option value="valor">Maior pagamento</option>
            <option value="titulo">Nome da vaga</option>
          </select>
          <div class="tp3-view-controls" role="group" aria-label="Exibição das vagas">
            <button class="active" type="button" data-tp3-view="list" aria-pressed="true"><?=icon('file',16)?> Lista</button>
            <button type="button" data-tp3-view="grid" aria-pressed="false"><?=icon('briefcase',16)?> Cards</button>
          </div>
        </div>
      </div>
      <div class="tp3-all-list" data-tp3-list>
        <?php foreach($opportunities as $s):
          $jobName=(string)($s['title']?:$s['category_name']);
          $searchData=mb_strtolower(implode(' ',[$s['category_name']??'',$jobName,$s['company_name']??'',$s['city']??'',$s['state']??'']),'UTF-8');
        ?>
        <article class="tp3-row" data-tp3-job data-tp3-list-job data-search="<?=e($searchData)?>" data-city="<?=e((string)($s['city']??'').' '.(string)($s['state']??''))?>" data-category="<?=e((string)($s['category_name']??''))?>" data-date="<?=e(substr((string)$s['starts_at'],0,10))?>" data-price="<?=e((string)$s['shift_value'])?>" data-title="<?=e($jobName)?>">
          <div class="tp3-row-thumb"><?php if(!empty($s['image_url'])):?><img loading="lazy" src="<?=e($s['image_url'])?>" alt="">
            <?php elseif(!empty($s['company_logo'])):?><img loading="lazy" src="<?=e($s['company_logo'])?>" alt="">
            <?php else:?><?=icon('briefcase',24)?><?php endif;?></div>
          <div class="tp3-row-title"><small><?=e($s['category_name'])?></small><h3><button type="button" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>"><?=e($jobName)?></button></h3><span><?=e($s['company_name'])?></span></div>
          <span class="tp3-row-meta"><?=icon('map',16)?> <?=e($s['city'].' - '.$s['state'])?></span>
          <span class="tp3-row-meta"><?=icon('calendar',16)?> <?=br_date($s['starts_at'],'d/m/Y')?></span>
          <span class="tp3-row-meta"><?=icon('clock',16)?> <?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></span>
          <span class="tp3-row-pay"><strong><?=money($s['shift_value'])?></strong><small>por turno</small></span>
          <button type="button" class="tp3-details" data-public-job-modal-open="public-job-modal-<?=e((string)$s['id'])?>">Ver detalhes</button>
          <form method="post" action="<?=e(url('vagas/'.$s['id'].'/interesse'))?>"><?=csrf_field()?><button type="submit" class="tp3-interest">Tenho interesse</button></form>
        </article>
        <?php endforeach;?>
      </div>
      <div class="tp3-empty" data-tp3-empty <?=$opportunities?'hidden':''?>>
        <?=icon('search',28)?>
        <strong><?=$opportunities?'Nenhuma vaga com esses filtros.':'Ainda não há vagas disponíveis.'?></strong>
        <p><?=$opportunities?'Experimente outra cidade, função ou data.':'Assim que uma empresa publicar uma oportunidade, ela aparecerá aqui.'?></p>
        <?php if($opportunities):?><button type="button" data-tp3-clear>Limpar filtros</button><?php endif;?>
      </div>

      <?php foreach($opportunities as $s):?>
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
    </section>
  </main>
  <footer class="tp3-footer"><span>TurnoPronto · Nós cuidamos do extra que você precisa.</span></footer>
</body>
</html>
