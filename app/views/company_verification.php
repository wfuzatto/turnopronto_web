<?php
$error=flash('error');
$success=flash('success');
$v=$verification;
$company=$v['company'];
$status=$company['status']??'pending';
$statusLabel=['pending'=>'Pendente','verified'=>'Verificado','rejected'=>'Rejeitado'][$status]??ucfirst($status);
$challenge=$v['phone_challenge']??null;
?>
<div class="verification-page">
  <div class="page-head">
    <div>
      <h1>Verificação da empresa</h1>
      <p>Conclua cada etapa para liberar a publicação de vagas. Você pode acompanhar tudo por aqui.</p>
    </div>
    <span class="status <?=e($status==='verified'?'confirmed':($status==='rejected'?'cancelled':'filling'))?>"><?=e($statusLabel)?></span>
  </div>

  <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

  <section class="panel verification-overview">
    <div class="verification-overview-head">
      <div>
        <h2><?=$v['company_verified']?'Cadastro liberado':'Sua verificação está em andamento'?></h2>
        <p><?=$v['company_verified']
          ?'Sua empresa já está verificada e pode usar os recursos liberados para contas empresariais.'
          :($v['ready_for_final']
            ?'Todas as etapas sob sua responsabilidade foram concluídas. Agora falta apenas a análise final da equipe TurnoPronto.'
            :'Veja abaixo o que já foi concluído e o que ainda precisa ser enviado ou confirmado.')?></p>
      </div>
      <strong><?=$v['progress']?>%</strong>
    </div>
    <div class="verification-progress-meta"><span><?=$v['completed_steps']?> de <?=$v['total_steps']?> etapas concluídas</span><span><?=e($statusLabel)?></span></div>
    <div class="verification-progress" aria-label="Progresso da verificação"><i style="width:<?=$v['progress']?>%"></i></div>
  </section>

  <div class="verification-checklist">
    <article class="verification-step-card <?=$v['phone_verified']?'complete':''?>">
      <span class="verification-step-icon"><?=$v['phone_verified']?icon('check',20):icon('shield',20)?></span>
      <div class="verification-step-main">
        <h3>1. WhatsApp do responsável</h3>
        <p><?=$v['phone_verified']
          ?'Número '.$v['phone_masked'].' confirmado por código.'
          :'Confirme '.$v['phone_masked'].' com o código de 6 dígitos enviado pelo WhatsApp.'?></p>
        <?php if(!$v['phone_verified'] && $challenge && !empty($challenge['development_code'])):?><span class="verification-dev-code">Modo de desenvolvimento: <?=e($challenge['development_code'])?></span><?php endif;?>
      </div>
      <div class="verification-step-side">
        <span class="doc-status <?=$v['phone_verified']?'verified':'pending'?>"><?=$v['phone_verified']?'Verificado':'Pendente'?></span>
        <?php if(!$v['phone_verified']):?>
          <div class="verification-step-actions">
            <form method="post" action="<?=e(url('empresa/verificacao/whatsapp/enviar'))?>"><?=csrf_field()?>
              <button class="btn btn-soft btn-sm" type="submit"><?=$challenge?'Reenviar código':'Enviar código'?></button>
            </form>
            <?php if($challenge && empty($challenge['expired'])):?>
              <form method="post" action="<?=e(url('empresa/verificacao/whatsapp/confirmar'))?>" class="verification-code-form"><?=csrf_field()?>
                <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
                <button class="btn btn-primary btn-sm" type="submit">Confirmar</button>
              </form>
            <?php endif;?>
          </div>
        <?php endif;?>
      </div>
    </article>

    <article class="verification-step-card <?=$v['data_complete']?'complete':''?>">
      <span class="verification-step-icon"><?=$v['data_complete']?icon('check',20):icon('file',20)?></span>
      <div class="verification-step-main">
        <h3>2. Dados da empresa e do responsável</h3>
        <p><?=$v['data_complete']?'Dados cadastrais obrigatórios preenchidos.':'Ainda existem informações obrigatórias que precisam ser completadas.'?></p>
        <?php if(!$v['data_complete']):?><ul class="missing-list"><?php foreach($v['missing_data'] as $field):?><li><?=e($field)?></li><?php endforeach;?></ul><?php endif;?>
      </div>
      <div class="verification-step-side">
        <span class="doc-status <?=$v['data_complete']?'verified':'pending'?>"><?=$v['data_complete']?'Completo':'Falta preencher'?></span>
        <?php if(!$v['data_complete']):?><a class="btn btn-soft btn-sm" href="<?=e(url('empresa/conta'))?>">Completar dados</a><?php endif;?>
      </div>
    </article>

    <?php $step=3; foreach($v['required_documents'] as $type=>$label):
      $doc=$v['documents'][$type]??null;
      $docStatus=$doc['status']??'missing';
      $isVerified=$docStatus==='verified'||($v['company_verified']&&!$doc);
      $isRejected=$docStatus==='rejected';
      $cardClass=$isVerified?'complete':($isRejected?'rejected':'');
      $statusText=$isVerified?'Verificado':($docStatus==='pending'?'Em análise':($isRejected?'Rejeitado':'Não enviado'));
    ?>
    <article class="verification-step-card <?=$cardClass?>">
      <span class="verification-step-icon"><?=$isVerified?icon('check',20):icon('file',20)?></span>
      <div class="verification-step-main">
        <h3><?=$step?>. <?=e($label)?></h3>
        <p><?php if($isVerified):?>Documento aprovado.<?php elseif($docStatus==='pending'):?>Arquivo recebido e aguardando conferência da equipe.<?php elseif($isRejected):?>O último envio foi recusado. Corrija e envie novamente.<?php else:?>Envie PDF, JPG ou PNG com até 5 MB.<?php endif;?></p>
        <?php if($doc && !empty($doc['original_name'])):?><p><strong>Arquivo:</strong> <?=e($doc['original_name'])?></p><?php endif;?>
        <?php if($isRejected && !empty($doc['rejection_reason'])):?><p><strong>Motivo:</strong> <?=e($doc['rejection_reason'])?></p><?php endif;?>
      </div>
      <div class="verification-step-side">
        <span class="doc-status <?=e($isVerified?'verified':($docStatus==='pending'?'pending':($isRejected?'rejected':'pending')))?>"><?=e($statusText)?></span>
        <?php if(!$v['company_verified'] && in_array($docStatus,['missing','rejected'],true)):?>
          <form method="post" enctype="multipart/form-data" action="<?=e(url('empresa/verificacao/documentos/enviar'))?>" class="verification-upload-form">
            <?=csrf_field()?><input type="hidden" name="type" value="<?=e($type)?>">
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            <button class="btn btn-primary btn-sm" type="submit"><?=$isRejected?'Reenviar':'Enviar'?></button>
          </form>
        <?php endif;?>
      </div>
    </article>
    <?php $step++; endforeach;?>

    <article class="verification-step-card <?=$v['company_verified']?'complete':''?>">
      <span class="verification-step-icon"><?=$v['company_verified']?icon('check',20):icon('shield',20)?></span>
      <div class="verification-step-main">
        <h3>6. Análise final TurnoPronto</h3>
        <p><?php if($v['company_verified']):?>Análise concluída. Sua empresa está liberada.<?php elseif($v['ready_for_final']):?>As etapas anteriores estão concluídas. A equipe administrativa já pode fazer a aprovação final.<?php else:?>Esta etapa será liberada automaticamente quando WhatsApp, dados e os três documentos obrigatórios estiverem verificados.<?php endif;?></p>
      </div>
      <div class="verification-step-side">
        <span class="doc-status <?=$v['company_verified']?'verified':'pending'?>"><?=$v['company_verified']?'Aprovado':($v['ready_for_final']?'Aguardando análise':'Bloqueado')?></span>
        <?php if(!$v['company_verified']):?><small class="verification-final-note">Não é necessário sair da página ou refazer o cadastro.</small><?php endif;?>
      </div>
    </article>
  </div>
</div>
