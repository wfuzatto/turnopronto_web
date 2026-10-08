<?php
$summary=$data['summary']??[];
$nextPayout=(float)($summary['next_payout']??$summary['available']??0);
?>
<div class="page-head"><div><h1>Ganhos</h1><p>Acompanhe valores de turnos, saldo e histórico de repasses.</p></div></div>
<div class="kpi-grid three">
  <div class="kpi"><div class="kpi-icon green"><?=icon('wallet')?></div><div><small>Total registrado</small><strong><?=money((float)($summary['total']??0))?></strong></div></div>
  <div class="kpi"><div class="kpi-icon blue"><?=icon('clock')?></div><div><small>Disponível</small><strong><?=money((float)($summary['available']??0))?></strong></div></div>
  <div class="kpi"><div class="kpi-icon green"><?=icon('check')?></div><div><small>Próximo repasse</small><strong><?=money($nextPayout)?></strong><span><?=$nextPayout>0?'Valor disponível para o próximo processamento':'Nenhum valor pendente para repasse'?></span></div></div>
</div>
<section class="panel">
  <h2>Evolução mensal</h2>
  <?php if(empty($data['months'])):?>
    <div class="mini-empty">Seus ganhos aparecerão aqui após a conclusão do primeiro turno.</div>
  <?php else:?>
    <div class="big-chart">
      <?php $max=max(array_column($data['months'],'amount')?:[1]);foreach($data['months'] as $m):?>
        <div><i style="height:<?=max(8,($m['amount']/$max)*100)?>%"><span><?=money($m['amount'])?></span></i><small><?=e($m['month'])?></small></div>
      <?php endforeach;?>
    </div>
  <?php endif;?>
</section>
