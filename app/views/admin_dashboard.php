<div class="page-head"><div><h1>Administração TurnoPronto</h1><p>Visão operacional, revisão humana e trilha de auditoria do marketplace.</p></div></div>
<div class="kpi-grid five">
  <div class="kpi"><div><small>Usuários</small><strong><?=$stats['users']?></strong></div></div>
  <div class="kpi"><div><small>Empresas</small><strong><?=$stats['companies']?></strong></div></div>
  <div class="kpi"><div><small>Profissionais</small><strong><?=$stats['professionals']?></strong></div></div>
  <div class="kpi"><div><small>Vagas</small><strong><?=$stats['shifts']?></strong></div></div>
  <div class="kpi"><div><small>Turnos</small><strong><?=$stats['assignments']?></strong></div></div>
</div>

<div class="admin-grid">
<section class="panel">
  <div class="panel-head"><div><h2>Contestações de reputação</h2><p>Decisões com impacto no perfil profissional passam por revisão humana.</p></div><span class="candidate-total"><?=count($appeals)?> pendente(s)</span></div>
  <?php if(!$appeals):?><div class="mini-empty">Nenhuma contestação aguardando revisão.</div><?php endif;?>
  <?php foreach($appeals as $a):?>
  <article class="appeal-card">
    <div class="avatar-md"><?=e(mb_strtoupper(mb_substr($a['professional_name'],0,1)))?></div>
    <div class="appeal-main"><strong><?=e($a['professional_name'])?></strong><span><?=e($a['description'])?></span><small>Impacto: <?=$a['points_delta']?> ponto(s) · enviado em <?=br_date($a['appealed_at'],'d/m/Y H:i')?></small></div>
    <div class="appeal-actions">
      <form method="post" action="<?=e(url('admin/reputacao/'.$a['id'].'/aceitar'))?>" data-confirm="Aceitar a contestação e reverter os pontos desta ocorrência?"><?=csrf_field()?><button class="btn btn-primary btn-sm">Reverter penalidade</button></form>
      <form method="post" action="<?=e(url('admin/reputacao/'.$a['id'].'/rejeitar'))?>" data-confirm="Manter a ocorrência e encerrar a contestação?"><?=csrf_field()?><button class="btn btn-ghost btn-sm">Manter ocorrência</button></form>
    </div>
  </article>
  <?php endforeach;?>
</section>

<section class="panel">
  <div class="panel-head"><div><h2>Auditoria recente</h2><p>Ações críticas registradas pelo backend.</p></div></div>
  <div class="audit-list">
    <?php foreach($audit as $log):?><div class="audit-row"><span class="audit-dot"></span><div><strong><?=e($log['action'])?></strong><small><?=e($log['user_name']?:'Sistema')?> · <?=e($log['entity_type']?:'evento')?> #<?=e($log['entity_id']?:'—')?></small></div><time><?=br_date($log['created_at'],'d/m H:i')?></time></div><?php endforeach;?>
    <?php if(!$audit):?><div class="mini-empty">A auditoria começará a aparecer conforme as ações forem executadas.</div><?php endif;?>
  </div>
</section>
</div>
