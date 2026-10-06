<?php
$role=(string)($user['role']??'');
$typeIcon=function(string $type): string {
    return match($type){
        'Profissional'=>'users',
        'Empresa'=>'briefcase',
        'Vaga'=>'calendar',
        default=>'search',
    };
};
?>
<div class="search-results-page">
  <div class="page-head">
    <div>
      <h1>Busca</h1>
      <p><?=$query!==''?'Resultados para “'.e($query).'”.':'Digite pelo menos 2 caracteres para pesquisar.'?></p>
    </div>
    <?php if($query!==''):?><span class="candidate-total"><?=count($results)?> resultado(s)</span><?php endif;?>
  </div>

  <?php if(mb_strlen($query)<2):?>
    <section class="panel search-empty-state">
      <?=icon('search',28)?>
      <strong>O que você está procurando?</strong>
      <p><?=$role==='company'
        ?'Pesquise profissionais, suas vagas, funções ou cidades.'
        :($role==='admin'
          ?'Pesquise empresas, profissionais ou vagas cadastradas.'
          :'Pesquise por função, empresa, cidade, endereço ou palavra-chave da vaga.')?></p>
    </section>
  <?php elseif(!$results):?>
    <section class="panel search-empty-state">
      <?=icon('search',28)?>
      <strong>Nenhum resultado para “<?=e($query)?>”</strong>
      <p>Tente um nome, função, cidade ou outra palavra-chave.</p>
    </section>
  <?php else:?>
    <section class="panel global-search-results">
      <?php foreach($results as $item):?>
        <a class="global-search-result-row" href="<?=e(url((string)$item['url']))?>">
          <span class="global-search-result-icon"><?=icon($typeIcon((string)$item['type']),18)?></span>
          <span class="global-search-result-copy">
            <span class="global-search-result-type"><?=e($item['type'])?></span>
            <strong><?=e($item['title'])?></strong>
            <small><?=e($item['subtitle'])?></small>
          </span>
          <span class="global-search-result-meta"><?=e($item['meta'])?></span>
          <span class="global-search-result-arrow">→</span>
        </a>
      <?php endforeach;?>
    </section>
  <?php endif;?>
</div>