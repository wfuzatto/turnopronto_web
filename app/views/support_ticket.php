<?php
$ticket=$detail['ticket'];
$messages=$detail['messages'];
$isAdmin=$segment==='admin';
$isCompany=$ticket['segment']==='company';
$prefix=$isAdmin?'admin':($isCompany?'empresa':'profissional');
$statusLabels=['open'=>'Aberto','in_progress'=>'Em atendimento','answered'=>'Respondido','closed'=>'Encerrado'];
$statusClass=['open'=>'open','in_progress'=>'progress','answered'=>'answered','closed'=>'closed'];
$categoryLabel=$detail['categories'][$ticket['category']]??$ticket['category'];
$requester=$isCompany?($ticket['company_name']?:$ticket['requester_name']):$ticket['requester_name'];
?>
<div class="support-page support-thread-page">
  <div class="page-head support-page-head">
    <div>
      <a class="admin-review-back" href="<?=e(url($prefix.'/suporte'))?>">← Voltar para suporte</a>
      <div class="support-segment-tag <?=$isCompany?'company':'professional'?>"><?=icon($isCompany?'briefcase':'users',15)?> <?=$isCompany?'Empresa':'Profissional'?></div>
      <h1>Chamado #<?=e((string)$ticket['id'])?></h1>
      <p><?=e($ticket['subject'])?></p>
    </div>
    <span class="support-status <?=$statusClass[$ticket['status']]??'open'?>"><?=e($statusLabels[$ticket['status']]??$ticket['status'])?></span>
  </div>

  <div class="support-thread-layout">
    <section class="panel support-conversation">
      <div class="support-conversation-head">
        <div><strong><?=e($categoryLabel)?></strong><small>Aberto em <?=date('d/m/Y H:i',strtotime($ticket['created_at']))?></small></div>
        <?php if(!empty($ticket['context_ref'])):?><span>Referência: <?=e($ticket['context_ref'])?></span><?php endif;?>
      </div>

      <div class="support-messages">
        <?php foreach($messages as $message):
          $fromSupport=$message['author_role']==='admin';
        ?>
          <article class="support-message <?=$fromSupport?'support':'requester'?>">
            <div class="support-message-author">
              <span class="avatar-sm"><?=$fromSupport?'TP':e(mb_strtoupper(mb_substr($message['author_name']?:$requester,0,1)))?></span>
              <div><strong><?=$fromSupport?'Equipe TurnoPronto':e($message['author_name']?:$requester)?></strong><small><?=$fromSupport?'Suporte':($isCompany?'Empresa':'Profissional')?> · <?=date('d/m/Y H:i',strtotime($message['created_at']))?></small></div>
            </div>
            <p><?=nl2br(e($message['body']))?></p>
          </article>
        <?php endforeach;?>
      </div>

      <?php if($ticket['status']!=='closed' || $isAdmin):?>
        <form class="support-reply-form" method="post" action="<?=e(url($prefix.'/suporte/chamados/'.$ticket['id'].'/mensagens'))?>">
          <?=csrf_field()?>
          <label><?=$isAdmin?'Responder chamado':'Enviar nova mensagem'?>
            <textarea name="message" rows="4" maxlength="5000" placeholder="<?=$isAdmin?'Escreva a orientação para o cliente...':'Acrescente informações que ajudem o suporte...'?>" required></textarea>
          </label>
          <div><small><?=$isAdmin?'A resposta será notificada ao usuário.':'Nossa equipe verá sua mensagem na fila de atendimento.'?></small><button class="btn btn-primary" type="submit">Enviar mensagem</button></div>
        </form>
      <?php else:?>
        <div class="support-closed-note"><?=icon('check',17)?><span>Este chamado foi encerrado. Se precisar tratar um novo assunto, abra outro chamado.</span></div>
      <?php endif;?>
    </section>

    <aside class="support-thread-side">
      <section class="panel support-ticket-info">
        <h3>Detalhes do chamado</h3>
        <?php if($isAdmin):?>
          <div><small>Solicitante</small><strong><?=e($requester)?></strong></div>
          <div><small>E-mail</small><strong><?=e($ticket['requester_email']?:'—')?></strong></div>
          <div><small>Telefone</small><strong><?=e($ticket['requester_phone']?:'—')?></strong></div>
        <?php endif;?>
        <div><small>Segmento</small><strong><?=$isCompany?'Empresa':'Profissional'?></strong></div>
        <div><small>Categoria</small><strong><?=e($categoryLabel)?></strong></div>
        <?php if(!empty($ticket['context_ref'])):?><div><small>Referência</small><strong><?=e($ticket['context_ref'])?></strong></div><?php endif;?>
      </section>

      <?php if($isAdmin):?>
        <section class="panel support-admin-actions">
          <h3>Atendimento</h3>
          <p>Atualize o status para organizar a fila operacional.</p>
          <form method="post" action="<?=e(url('admin/suporte/chamados/'.$ticket['id'].'/status'))?>">
            <?=csrf_field()?>
            <select name="status">
              <?php foreach($statusLabels as $key=>$label):?><option value="<?=e($key)?>" <?=$ticket['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?>
            </select>
            <button class="btn btn-primary btn-block" type="submit">Atualizar status</button>
          </form>
        </section>
      <?php endif;?>
    </aside>
  </div>
</div>