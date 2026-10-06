<?php
$error=flash('error');
$success=flash('success');
$kind=$detail['kind']??'';
$isCompany=$kind==='company';

$statusLabel=function(string $status): string {
    return match($status){
        'verified'=>'Aprovado',
        'pending'=>'Em análise',
        'rejected'=>'Rejeitado',
        default=>'Não enviado',
    };
};
$statusClass=function(string $status): string {
    return match($status){
        'verified'=>'verified',
        'rejected'=>'rejected',
        default=>'pending',
    };
};

if($isCompany){
    $record=$detail['company'];
    $summary=$detail['summary']??[];
    $entityId=(int)$record['id'];
    $back='admin/verificacao/empresa/'.$entityId;
    $finalAction='admin/verificacao/empresa/'.$entityId;
    $titleName=$record['trade_name']??'Empresa';
    $subtitle='Cadastro empresarial';
}else{
    $record=$detail['professional'];
    $entityId=(int)$record['id'];
    $back='admin/verificacao/profissional/'.$entityId;
    $finalAction='admin/verificacao/profissional/'.$entityId;
    $titleName=$record['name']??'Profissional';
    $subtitle='Cadastro profissional';
}
?>
<div class="admin-review-page">
  <div class="page-head admin-review-head">
    <div>
      <a class="admin-review-back" href="<?=e(url('admin/dashboard'))?>">← Voltar para aprovações</a>
      <h1>Revisar <?=e($subtitle)?></h1>
      <p>Confira os dados e abra cada documento antes de tomar a decisão final.</p>
    </div>
    <span class="status filling"><?=e(ucfirst((string)($record['status']??'pending')))?></span>
  </div>

  <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

  <section class="panel admin-review-summary">
    <div class="admin-review-identity">
      <span class="verification-type <?=$isCompany?'company':'professional'?>"><?=$isCompany?icon('briefcase',22):icon('users',22)?></span>
      <div>
        <span class="verification-kind <?=$isCompany?'company':'professional'?>"><?=e($subtitle)?></span>
        <h2><?=e($titleName)?></h2>
        <?php if($isCompany):?>
          <p><?=e($record['legal_name']??'')?> · CNPJ <?=e($record['cnpj']??'—')?></p>
        <?php else:?>
          <p><?=e($record['headline']??'')?> · CPF <?=e($record['cpf']??'—')?></p>
        <?php endif;?>
      </div>
    </div>
    <div class="admin-review-readiness <?=$detail['ready_for_final']?'ready':'blocked'?>">
      <?=icon($detail['ready_for_final']?'check':'shield',18)?>
      <div><strong><?=$detail['ready_for_final']?'Pronto para aprovação final':'Ainda não pode ser aprovado'?></strong>
      <span><?=$detail['ready_for_final']?'Os pré-requisitos obrigatórios foram concluídos.':'Revise e aprove os itens pendentes abaixo antes de liberar o cadastro.'?></span></div>
    </div>
  </section>

  <?php if($isCompany):?>
  <section class="panel admin-review-section">
    <div class="panel-head"><div><h2>Dados da empresa</h2><p>Informações empresariais e do responsável pelo cadastro.</p></div></div>
    <div class="admin-review-grid">
      <div><small>Nome fantasia</small><strong><?=e($record['trade_name']??'—')?></strong></div>
      <div><small>Razão social</small><strong><?=e($record['legal_name']??'—')?></strong></div>
      <div><small>CNPJ</small><strong><?=e($record['cnpj']??'—')?></strong></div>
      <div><small>CPF do responsável</small><strong><?=e($record['responsible_cpf']??'—')?></strong></div>
      <div><small>Responsável</small><strong><?=e($record['owner_name']??'—')?></strong></div>
      <div><small>E-mail</small><strong><?=e($record['owner_email']??'—')?></strong></div>
      <div><small>WhatsApp</small><strong><?=e($record['owner_phone']??'—')?></strong><span class="review-inline-status <?=!empty($record['phone_verified_at'])?'ok':'missing'?>"><?=!empty($record['phone_verified_at'])?'Verificado':'Não verificado'?></span></div>
      <div><small>Endereço</small><strong><?=e(trim(($record['address']??'').' · '.($record['postal_code']??''),' ·'))?></strong></div>
      <div><small>Cidade / UF</small><strong><?=e(($record['city']??'—').' / '.($record['state']??'—'))?></strong></div>
      <div><small>Pix</small><strong><?=e(($record['pix_key_type']??'—').' · '.($record['pix_key']??'—'))?></strong></div>
      <div><small>Titular Pix</small><strong><?=e($record['pix_holder_name']??'—')?></strong></div>
      <div><small>Documento titular Pix</small><strong><?=e($record['pix_holder_document']??'—')?></strong></div>
    </div>
    <?php if(!empty($record['maps_url'])):?><a class="btn btn-soft btn-sm admin-review-map" target="_blank" rel="noopener noreferrer" href="<?=e($record['maps_url'])?>">Abrir localização no Google Maps ↗</a><?php endif;?>
    <?php if(!empty($summary['missing_data'])):?><div class="admin-review-warning"><strong>Dados obrigatórios ainda ausentes:</strong> <?=e(implode(', ',$summary['missing_data']))?></div><?php endif;?>
  </section>

  <section class="panel admin-review-section">
    <div class="panel-head"><div><h2>Documentação empresarial</h2><p>Abra o arquivo e confira seu conteúdo antes de aprovar cada documento.</p></div></div>
    <div class="admin-doc-list">
      <?php foreach(($summary['required_documents']??[]) as $type=>$label):
        $doc=$summary['documents'][$type]??null;
        $status=$doc['status']??'missing';
      ?>
      <article class="admin-doc-card">
        <span class="verification-type company"><?=icon('file',19)?></span>
        <div class="admin-doc-copy">
          <div class="admin-doc-title"><strong><?=e($label)?></strong><span class="doc-status <?=$statusClass($status)?>"><?=$statusLabel($status)?></span></div>
          <?php if($doc):?>
            <span><?=e($doc['original_name']?:'Arquivo enviado')?> · <?=e($doc['mime_type']?:'tipo não informado')?></span>
            <small>Enviado em <?=br_date($doc['created_at'],'d/m/Y H:i')?><?php if(!empty($doc['verified_at'])):?> · revisado em <?=br_date($doc['verified_at'],'d/m/Y H:i')?><?php endif;?></small>
            <?php if($status==='rejected' && !empty($doc['rejection_reason'])):?><div class="admin-doc-reason">Motivo: <?=e($doc['rejection_reason'])?></div><?php endif;?>
          <?php else:?>
            <span>Nenhum arquivo enviado.</span>
          <?php endif;?>
        </div>
        <div class="admin-doc-actions">
          <?php if($doc && !empty($doc['file_path'])):?><a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/arquivo'))?>">Abrir documento</a><?php endif;?>
          <?php if($doc && $status==='pending'):?>
            <form method="post" action="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/aprovar'))?>"><?=csrf_field()?><input type="hidden" name="back" value="<?=e($back)?>"><button class="btn btn-primary btn-sm" type="submit">Aprovar documento</button></form>
            <form method="post" action="<?=e(url('admin/documentos/empresa/'.$doc['id'].'/rejeitar'))?>" data-confirm="Rejeitar este documento?"><?=csrf_field()?><input type="hidden" name="back" value="<?=e($back)?>"><input class="admin-reject-reason" name="reason" placeholder="Motivo da rejeição" required><button class="btn btn-ghost btn-sm" type="submit">Rejeitar</button></form>
          <?php elseif(($record['status']??'pending')!=='verified' && in_array($status,['missing','rejected'],true)):?>
            <form class="admin-support-upload" method="post" enctype="multipart/form-data" action="<?=e(url('admin/verificacao/empresa/'.$entityId.'/documentos/enviar'))?>">
              <?=csrf_field()?>
              <input type="hidden" name="type" value="<?=e($type)?>">
              <label>
                <span>Recebeu pelo suporte?</span>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
              </label>
              <button class="btn btn-primary btn-sm" type="submit"><?=$status==='rejected'?'Substituir arquivo':'Anexar documento'?></button>
            </form>
          <?php endif;?>
        </div>
      </article>
      <?php endforeach;?>
    </div>
  </section>

  <?php else:?>
  <section class="panel admin-review-section">
    <div class="panel-head"><div><h2>Dados do profissional</h2><p>Confira os dados pessoais, profissionais e de pagamento.</p></div></div>
    <div class="admin-review-grid">
      <div><small>Nome</small><strong><?=e($record['name']??'—')?></strong></div>
      <div><small>E-mail</small><strong><?=e($record['email']??'—')?></strong></div>
      <div><small>WhatsApp</small><strong><?=e($record['phone']??'—')?></strong></div>
      <div><small>CPF</small><strong><?=e($record['cpf']??'—')?></strong></div>
      <div><small>RG</small><strong><?=e($record['rg']??'—')?></strong></div>
      <div><small>Data de nascimento</small><strong><?=!empty($record['birth_date'])?br_date($record['birth_date'],'d/m/Y'):'—'?></strong></div>
      <div><small>Atividade principal</small><strong><?=e($record['headline']??'—')?></strong></div>
      <div><small>Cidade / UF</small><strong><?=e(($record['city']??'—').' / '.($record['state']??'—'))?></strong></div>
      <div><small>Endereço</small><strong><?=e(trim(($record['address']??'').' · '.($record['postal_code']??''),' ·'))?></strong></div>
      <div><small>Pix</small><strong><?=e(($record['pix_key_type']??'—').' · '.($record['pix_key']??'—'))?></strong></div>
      <div><small>Titular Pix</small><strong><?=e($record['pix_holder_name']??'—')?></strong></div>
      <div><small>Documento titular Pix</small><strong><?=e($record['pix_holder_document']??'—')?></strong></div>
    </div>
    <?php if(!empty($detail['categories'])):?><div class="admin-review-categories"><small>Áreas de interesse</small><div><?php foreach($detail['categories'] as $cat):?><span><?=e($cat['name'])?></span><?php endforeach;?></div></div><?php endif;?>
    <?php if(!empty($record['bio'])):?><div class="admin-review-bio"><small>Apresentação</small><p><?=nl2br(e($record['bio']))?></p></div><?php endif;?>
  </section>

  <section class="panel admin-review-section">
    <div class="panel-head"><div><h2>Documentação do profissional</h2><p>Documento de identidade e CPF precisam ser conferidos antes da aprovação final.</p></div></div>
    <div class="admin-doc-list">
      <?php foreach(['identity'=>'Documento de identidade','cpf'=>'CPF'] as $type=>$label):
        $doc=$detail['latest_documents'][$type]??null;
        $status=$doc['status']??'missing';
      ?>
      <article class="admin-doc-card">
        <span class="verification-type professional"><?=icon('file',19)?></span>
        <div class="admin-doc-copy">
          <div class="admin-doc-title"><strong><?=e($label)?></strong><span class="doc-status <?=$statusClass($status)?>"><?=$statusLabel($status)?></span></div>
          <?php if($doc):?>
            <span><?=e($doc['original_name']?:'Arquivo enviado')?> · <?=e($doc['mime_type']?:'tipo não informado')?></span>
            <small>Enviado em <?=br_date($doc['created_at'],'d/m/Y H:i')?><?php if(!empty($doc['verified_at'])):?> · revisado em <?=br_date($doc['verified_at'],'d/m/Y H:i')?><?php endif;?></small>
            <?php if($status==='rejected' && !empty($doc['rejection_reason'])):?><div class="admin-doc-reason">Motivo: <?=e($doc['rejection_reason'])?></div><?php endif;?>
          <?php else:?>
            <span>Nenhum arquivo enviado.</span>
          <?php endif;?>
        </div>
        <div class="admin-doc-actions">
          <?php if($doc && !empty($doc['file_path'])):?><a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?=e(url('admin/documentos/'.$doc['id'].'/arquivo'))?>">Abrir documento</a><?php endif;?>
          <?php if($doc && $status==='pending'):?>
            <form method="post" action="<?=e(url('admin/documentos/'.$doc['id'].'/aprovar'))?>"><?=csrf_field()?><input type="hidden" name="back" value="<?=e($back)?>"><button class="btn btn-primary btn-sm" type="submit">Aprovar documento</button></form>
            <form method="post" action="<?=e(url('admin/documentos/'.$doc['id'].'/rejeitar'))?>" data-confirm="Rejeitar este documento?"><?=csrf_field()?><input type="hidden" name="back" value="<?=e($back)?>"><input class="admin-reject-reason" name="reason" placeholder="Motivo da rejeição" required><button class="btn btn-ghost btn-sm" type="submit">Rejeitar</button></form>
          <?php elseif(($record['status']??'pending')!=='verified' && in_array($status,['missing','rejected'],true)):?>
            <form class="admin-support-upload" method="post" enctype="multipart/form-data" action="<?=e(url('admin/verificacao/profissional/'.$entityId.'/documentos/enviar'))?>">
              <?=csrf_field()?>
              <input type="hidden" name="type" value="<?=e($type)?>">
              <label>
                <span>Recebeu pelo suporte?</span>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
              </label>
              <button class="btn btn-primary btn-sm" type="submit"><?=$status==='rejected'?'Substituir arquivo':'Anexar documento'?></button>
            </form>
          <?php endif;?>
        </div>
      </article>
      <?php endforeach;?>
    </div>
  </section>
  <?php endif;?>

  <section class="panel admin-final-decision">
    <div>
      <h2>Decisão final</h2>
      <p>Somente aprove depois de conferir os dados acima e abrir a documentação enviada.</p>
    </div>
    <div class="admin-final-actions">
      <form method="post" action="<?=e(url($finalAction.'/aprovar'))?>"><?=csrf_field()?><button class="btn btn-primary" type="submit" <?=$detail['ready_for_final']?'':'disabled title="Existem pré-requisitos pendentes"'?>>Aprovar e liberar cadastro</button></form>
      <form method="post" action="<?=e(url($finalAction.'/rejeitar'))?>" data-confirm="Rejeitar este cadastro?"><?=csrf_field()?><button class="btn btn-ghost" type="submit">Rejeitar cadastro</button></form>
    </div>
  </section>
</div>
