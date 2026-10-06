<?php
$isAdmin=$segment==='admin';
$isCompany=$segment==='company';
$statusLabels=['open'=>'Aberto','in_progress'=>'Em atendimento','answered'=>'Respondido','closed'=>'Encerrado'];
$statusClass=['open'=>'open','in_progress'=>'progress','answered'=>'answered','closed'=>'closed'];
$prefix=$isAdmin?'admin':($isCompany?'empresa':'profissional');
$segmentTitle=$isCompany?'Suporte para empresas':'Suporte para profissionais';
$segmentDescription=$isCompany
    ?'Atendimento para cadastro empresarial, vagas, profissionais, escalas e financeiro.'
    :'Atendimento para cadastro, documentos, oportunidades, turnos, pagamentos e reputação.';
?>
<div class="support-page <?=$isAdmin?'admin-support-page':($isCompany?'company-support-page':'professional-support-page')?>">
  <div class="page-head support-page-head">
    <div>
      <div class="support-segment-tag <?=$isAdmin?'admin':($isCompany?'company':'professional')?>">
        <?=icon($isAdmin?'shield':($isCompany?'briefcase':'users'),15)?>
        <?=$isAdmin?'Operação TurnoPronto':($isCompany?'Empresa':'Profissional')?>
      </div>
      <h1><?=$isAdmin?'Central de suporte':e($segmentTitle)?></h1>
      <p><?=$isAdmin?'Acompanhe e responda chamados separados por segmento.':e($segmentDescription)?></p>
    </div>
    <?php if(!$isAdmin):?>
      <a class="btn btn-soft" href="#novo-chamado">Abrir novo chamado</a>
    <?php endif;?>
  </div>

  <?php if($isAdmin):?>
    <div class="support-kpis">
      <div class="panel support-kpi"><span><?=icon('help',20)?></span><div><small>Abertos</small><strong><?=($support['stats']['open']??0)+($support['stats']['in_progress']??0)?></strong></div></div>
      <div class="panel support-kpi company"><span><?=icon('briefcase',20)?></span><div><small>Empresas</small><strong><?=e((string)($support['stats']['company']??0))?></strong></div></div>
      <div class="panel support-kpi professional"><span><?=icon('users',20)?></span><div><small>Profissionais</small><strong><?=e((string)($support['stats']['professional']??0))?></strong></div></div>
      <div class="panel support-kpi answered"><span><?=icon('check',20)?></span><div><small>Respondidos</small><strong><?=e((string)($support['stats']['answered']??0))?></strong></div></div>
    </div>

    <section class="panel support-ticket-list-panel">
      <div class="panel-head"><div><h2>Fila de atendimento</h2><p>Empresas e profissionais permanecem identificados por segmento durante todo o atendimento.</p></div><span class="candidate-total"><?=count($support['tickets'])?> chamado(s)</span></div>
      <?php if(!$support['tickets']):?><div class="mini-empty">Nenhum chamado de suporte registrado.</div><?php endif;?>
      <div class="support-ticket-list">
        <?php foreach($support['tickets'] as $ticket):
          $ticketCategories=Data::supportCategories((string)$ticket['segment']);
          $requester=$ticket['segment']==='company'
              ?($ticket['company_name']?:$ticket['requester_name'])
              :$ticket['requester_name'];
        ?>
          <a class="support-ticket-row" href="<?=e(url('admin/suporte/chamados/'.$ticket['id']))?>">
            <span class="support-ticket-segment <?=$ticket['segment']==='company'?'company':'professional'?>"><?=icon($ticket['segment']==='company'?'briefcase':'users',18)?></span>
            <span class="support-ticket-main">
              <span class="support-ticket-meta"><b><?=$ticket['segment']==='company'?'Empresa':'Profissional'?></b><small>#<?=e((string)$ticket['id'])?> · <?=e($ticketCategories[$ticket['category']]??$ticket['category'])?></small></span>
              <strong><?=e($ticket['subject'])?></strong>
              <small><?=e($requester)?><?php if(!empty($ticket['context_ref'])):?> · Ref. <?=e($ticket['context_ref'])?><?php endif;?></small>
            </span>
            <span class="support-ticket-side"><span class="support-status <?=$statusClass[$ticket['status']]??'open'?>"><?=e($statusLabels[$ticket['status']]??$ticket['status'])?></span><small><?=date('d/m/Y H:i',strtotime($ticket['last_message_at']))?></small></span>
          </a>
        <?php endforeach;?>
      </div>
    </section>
  <?php else:?>
    <div class="support-kpis user">
      <div class="panel support-kpi"><span><?=icon('help',20)?></span><div><small>Abertos</small><strong><?=($support['stats']['open']??0)+($support['stats']['in_progress']??0)?></strong></div></div>
      <div class="panel support-kpi answered"><span><?=icon('check',20)?></span><div><small>Respondidos</small><strong><?=e((string)($support['stats']['answered']??0))?></strong></div></div>
      <div class="panel support-kpi closed"><span><?=icon('file',20)?></span><div><small>Encerrados</small><strong><?=e((string)($support['stats']['closed']??0))?></strong></div></div>
    </div>

    <div class="support-user-layout">
      <section class="panel support-new-ticket" id="novo-chamado">
        <div class="panel-head"><div><h2>Novo chamado</h2><p><?=$isCompany?'Conte o que aconteceu na operação da empresa.':'Conte o que aconteceu para nossa equipe entender rapidamente.'?></p></div></div>
        <form method="post" action="<?=e(url($prefix.'/suporte/chamados'))?>" class="support-form">
          <?=csrf_field()?>
          <label>Assunto *
            <select name="category" required>
              <option value="">Selecione</option>
              <?php foreach($support['categories'] as $key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?>
            </select>
          </label>
          <label>Título do chamado *
            <input name="subject" maxlength="190" placeholder="<?=$isCompany?'Ex.: Não consigo alterar uma vaga publicada':'Ex.: Pagamento do turno ainda não apareceu'?>" required>
          </label>
          <label><?=$isCompany?'Referência da vaga, turno, cobrança ou pagamento':'Referência da vaga, turno ou pagamento'?> <small>(opcional)</small>
            <input name="context_ref" maxlength="190" placeholder="Ex.: vaga #123, turno #88, pagamento XYZ">
          </label>
          <label>Descreva o que aconteceu *
            <textarea name="message" rows="6" maxlength="5000" placeholder="Inclua o máximo de contexto útil: o que você tentou fazer, o que aconteceu e qual resultado esperava." required></textarea>
          </label>
          <div class="support-form-note"><?=icon('shield',16)?><span>Não envie senha, código de verificação ou dados completos de cartão.</span></div>
          <button class="btn btn-primary" type="submit">Abrir chamado</button>
        </form>
      </section>

      <section class="panel support-ticket-list-panel">
        <div class="panel-head"><div><h2>Meus chamados</h2><p>Acompanhe respostas e continue a conversa pelo próprio chamado.</p></div></div>
        <?php if(!$support['tickets']):?><div class="support-empty"><?=icon('help',26)?><strong>Nenhum chamado ainda</strong><p>Quando precisar de ajuda, abra um chamado pelo formulário ao lado.</p></div><?php endif;?>
        <div class="support-ticket-list">
          <?php foreach($support['tickets'] as $ticket):?>
            <a class="support-ticket-row compact" href="<?=e(url($prefix.'/suporte/chamados/'.$ticket['id']))?>">
              <span class="support-ticket-segment <?=$isCompany?'company':'professional'?>"><?=icon($isCompany?'briefcase':'users',18)?></span>
              <span class="support-ticket-main">
                <span class="support-ticket-meta"><small>#<?=e((string)$ticket['id'])?> · <?=e($support['categories'][$ticket['category']]??$ticket['category'])?></small></span>
                <strong><?=e($ticket['subject'])?></strong>
                <?php if(!empty($ticket['context_ref'])):?><small>Ref. <?=e($ticket['context_ref'])?></small><?php endif;?>
              </span>
              <span class="support-ticket-side"><span class="support-status <?=$statusClass[$ticket['status']]??'open'?>"><?=e($statusLabels[$ticket['status']]??$ticket['status'])?></span><small><?=date('d/m/Y H:i',strtotime($ticket['last_message_at']))?></small></span>
            </a>
          <?php endforeach;?>
        </div>
      </section>
    </div>
  <?php endif;?>
</div>