<?php $p=$data['profile'];?>
<div class="page-head"><div><h1>Reputação</h1><p>Sua reputação é baseada principalmente em fatos verificáveis, não apenas em estrelas.</p></div></div>
<div class="score-hero panel"><div class="score-circle"><strong><?=round($p['reliability_score'])?>%</strong><span>Confiabilidade</span></div><div class="score-copy"><h2>Histórico operacional</h2><p><?=$p['completed_shifts']?> turnos concluídos. Ocorrências antigas podem perder peso e penalizações negativas permitem contestação e revisão humana.</p><div class="score-bars"><div><span>Presença</span><b><?=round($p['attendance_score'])?>%</b><i><em style="width:<?=round($p['attendance_score'])?>%"></em></i></div><div><span>Pontualidade</span><b><?=round($p['punctuality_score'])?>%</b><i><em style="width:<?=round($p['punctuality_score'])?>%"></em></i></div><div><span>Avaliação</span><b><?=number_format((float)$p['rating'],1,',','.')?> / 5</b><i><em style="width:<?=($p['rating']/5)*100?>%"></em></i></div></div></div></div>
<section class="panel">
  <div class="panel-head"><div><h2>Eventos recentes</h2><p>Fatos que ajudam a explicar seu indicador de confiabilidade.</p></div></div>
  <?php if(!$data['events']):?><p class="muted">Nenhuma ocorrência registrada.</p><?php endif;?>
  <?php foreach($data['events'] as $event):?>
  <div class="event-row reputation-event">
    <span class="activity-icon <?=$event['points_delta']>0?'green':((float)$event['points_delta']<0?'red':'blue')?>"><?=$event['points_delta']>=0?'✓':'!'?></span>
    <div><strong><?=e($event['description'])?></strong><small><?=br_date($event['occurred_at'],'d/m/Y H:i')?> · <?=e($event['event_type'])?></small></div>
    <div class="event-actions">
      <b class="<?=$event['points_delta']<0?'trend down':'trend up'?>"><?=($event['points_delta']>0?'+':'').$event['points_delta']?></b>
      <?php if((float)$event['points_delta']<0 && empty($event['appeal_status'])):?><form method="post" action="<?=e(url('profissional/reputacao/'.$event['id'].'/contestar'))?>"><?=csrf_field()?><button class="btn btn-ghost btn-sm">Contestar</button></form>
      <?php elseif(!empty($event['appeal_status'])):?><span class="appeal-pill <?=e($event['appeal_status'])?>"><?=e(['pending'=>'Em revisão','accepted'=>'Revertida','rejected'=>'Mantida'][$event['appeal_status']]??$event['appeal_status'])?></span><?php endif;?>
    </div>
  </div>
  <?php endforeach;?>
</section>
