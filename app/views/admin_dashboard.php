<div class="page-head"><div><h1>Administração TurnoPronto</h1><p>Visão operacional, revisão humana e trilha de auditoria do marketplace.</p></div></div>
<div class="kpi-grid five">
  <div class="kpi"><div><small>Usuários</small><strong><?=$stats['users']?></strong></div></div>
  <div class="kpi"><div><small>Empresas</small><strong><?=$stats['companies']?></strong></div></div>
  <div class="kpi"><div><small>Profissionais</small><strong><?=$stats['professionals']?></strong></div></div>
  <div class="kpi"><div><small>Vagas</small><strong><?=$stats['shifts']?></strong></div></div>
  <div class="kpi"><div><small>Turnos</small><strong><?=$stats['assignments']?></strong></div></div>
</div>

<section class="panel verification-panel">
  <div class="panel-head"><div><h2>Documentos empresariais pendentes</h2><p>Documentos exigidos para liberar empresas para publicação de vagas.</p></div><span class="candidate-total"><?=count($companyDocuments)?> pendente(s)</span></div>
  <?php if(!$companyDocuments):?><div class="mini-empty">Nenhum documento empresarial aguardando análise.</div><?php endif;?>
  <?php foreach($companyDocuments as $doc):?><article class="doc-review-row">
    <span class="verification-type company"><?=icon('briefcase',18)?></span>
    <div><strong><?=e($doc['trade_name'])?> · <?=e($doc['label'])?></strong><span><?=e($doc['original_name']?:'Arquivo enviado')?> · <?=e($doc['mime_type']?:'tipo não informado')?></span><small>CNPJ: <?=e($doc['cnpj']?:'—')?> · Responsável: <?=e($doc['owner_name']?:'—')?></small></div>
    <div class="doc-review-actions">
      <a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/arquivo'))?>">Abrir arquivo</a>
      <form method="post" action="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/aprovar'))?>"><?=csrf_field()?><button class="btn btn-primary btn-sm">Aprovar</button></form>
      <form class="reject-doc" method="post" action="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/rejeitar'))?>" data-confirm="Rejeitar este documento empresarial?"><?=csrf_field()?><input name="reason" placeholder="Motivo da rejeição"><button class="btn btn-ghost btn-sm">Rejeitar</button></form>
    </div>
  </article><?php endforeach;?>
</section>

<section class="panel verification-panel">
  <div class="panel-head"><div><h2>Documentos pendentes</h2><p>Arquivos privados enviados pelos profissionais para validação.</p></div><span class="candidate-total"><?=count($documents)?> pendente(s)</span></div>
  <?php if(!$documents):?><div class="mini-empty">Nenhum documento aguardando análise.</div><?php endif;?>
  <?php foreach($documents as $doc):?><article class="doc-review-row">
    <span class="verification-type professional"><?=icon('file',18)?></span>
    <div><strong><?=e($doc['professional_name'])?> · <?=e($doc['label'])?></strong><span><?=e($doc['original_name']?:'Arquivo enviado')?> · <?=e($doc['mime_type']?:'tipo não informado')?></span><small>Enviado em <?=br_date($doc['created_at'],'d/m/Y H:i')?> · perfil <?=e($doc['professional_status'])?></small></div>
    <div class="doc-review-actions">
      <a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?=e(url('admin/documentos/'.$doc['id'].'/arquivo'))?>">Abrir arquivo</a>
      <form method="post" action="<?=e(url('admin/documentos/'.$doc['id'].'/aprovar'))?>"><?=csrf_field()?><button class="btn btn-primary btn-sm">Aprovar</button></form>
      <form class="reject-doc" method="post" action="<?=e(url('admin/documentos/'.$doc['id'].'/rejeitar'))?>" data-confirm="Rejeitar este documento?"><?=csrf_field()?><input name="reason" placeholder="Motivo da rejeição"><button class="btn btn-ghost btn-sm">Rejeitar</button></form>
    </div>
  </article><?php endforeach;?>
</section>

<section class="panel verification-panel">
  <div class="panel-head"><div><h2>Cadastros aguardando aprovação final</h2><p>Esta fila é de <strong>cadastros</strong>, não de vagas. Veja o tipo, a finalidade e o que já foi verificado antes de liberar.</p></div><span class="candidate-total"><?=count($verification['companies'])+count($verification['professionals'])?> pendente(s)</span></div>
  <?php if(!$verification['companies'] && !$verification['professionals']):?><div class="mini-empty">Nenhum cadastro aguardando verificação.</div><?php endif;?>

  <?php foreach($verification['companies'] as $item):?>
    <article class="verification-row verification-row-detailed">
      <span class="verification-type company"><?=icon('briefcase',18)?></span>
      <div class="verification-copy">
        <div class="verification-row-title"><strong><?=e($item['trade_name'])?></strong><span class="verification-kind company">Cadastro empresarial</span></div>
        <span><?=e($item['cnpj'])?> · <?=e($item['city'].' - '.$item['state'])?></span>
        <small>Responsável: <?=e($item['owner_name']?:'—')?> · <?=e($item['owner_email']?:'—')?></small>
        <div class="verification-purpose"><b>O que será aprovado:</b> <?=e($item['verification_purpose'])?></div>
        <div class="verification-checks">
          <?php foreach($item['verification_checks'] as $check):?>
            <span class="verification-check <?=$check['ok']?'ok':'missing'?>"><?=$check['ok']?'✓':'!'?> <?=e($check['label'])?></span>
          <?php endforeach;?>
        </div>
        <div class="verification-readiness <?=$item['ready_for_final']?'ready':'blocked'?>">
          <?=$item['ready_for_final']?'Pronto para aprovação final.':'Ainda existem pré-requisitos pendentes. Aprove os itens faltantes antes de liberar a empresa.'?>
        </div>
      </div>
      <div class="verification-actions verification-actions-stacked">
        <a class="btn btn-primary btn-sm" href="<?=e(url('admin/verificacao/empresa/'.$item['id']))?>">Revisar cadastro</a>
        <small class="verification-review-hint">Abra os dados e documentos antes de decidir.</small>
      </div>
    </article>
  <?php endforeach;?>

  <?php foreach($verification['professionals'] as $item):?>
    <article class="verification-row verification-row-detailed">
      <span class="verification-type professional"><?=icon('users',18)?></span>
      <div class="verification-copy">
        <div class="verification-row-title"><strong><?=e($item['name'])?></strong><span class="verification-kind professional">Cadastro profissional</span></div>
        <span><?=e($item['headline'])?> · <?=e($item['city'].' - '.$item['state'])?></span>
        <small>CPF: <?=e($item['cpf'])?> · <?=e($item['email'])?></small>
        <div class="verification-purpose"><b>O que será aprovado:</b> <?=e($item['verification_purpose'])?></div>
        <div class="verification-checks">
          <?php foreach($item['verification_checks'] as $check):?>
            <span class="verification-check <?=$check['ok']?'ok':'missing'?>"><?=$check['ok']?'✓':'!'?> <?=e($check['label'])?></span>
          <?php endforeach;?>
        </div>
        <div class="verification-readiness <?=$item['ready_for_final']?'ready':'blocked'?>">
          <?=$item['ready_for_final']?'Pronto para aprovação final.':'Documento de identidade e CPF precisam estar aprovados antes da liberação.'?>
        </div>
      </div>
      <div class="verification-actions verification-actions-stacked">
        <a class="btn btn-primary btn-sm" href="<?=e(url('admin/verificacao/profissional/'.$item['id']))?>">Revisar cadastro</a>
        <small class="verification-review-hint">Abra os dados e documentos antes de decidir.</small>
      </div>
    </article>
  <?php endforeach;?>
</section>

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
    <?php foreach($audit as $log):?><div class="audit-row"><span class="audit-dot"></span><div><strong><?=e($log['action_label']??$log['action'])?></strong><small><?=e($log['user_name']?:'Sistema')?> · <?=e($log['entity_label']??$log['entity_type']??'evento')?> <?=!empty($log['entity_id'])?'#'.e($log['entity_id']):''?></small></div><time><?=br_date($log['created_at'],'d/m H:i')?></time></div><?php endforeach;?>
    <?php if(!$audit):?><div class="mini-empty">A auditoria começará a aparecer conforme as ações forem executadas.</div><?php endif;?>
  </div>
</section>
</div>
