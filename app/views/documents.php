<div class="page-head"><div><h1>Documentos</h1><p>Envie documentos para aumentar a confiança e liberar seu perfil para os turnos.</p></div></div>
<div class="kyc-note"><strong>Para a primeira liberação:</strong> basta um documento oficial com foto. O CPF já é validado no cadastro e não exige um segundo arquivo. Os documentos são privados e não ficam expostos às empresas contratantes.</div>
<section class="panel">
  <div class="panel-head"><div><h2>Enviar documento</h2><p>Formatos aceitos: PDF, JPG e PNG, até 5 MB.</p></div></div>
  <form method="post" enctype="multipart/form-data" action="<?=e(url('profissional/documentos/enviar'))?>" class="document-upload-grid"><?=csrf_field()?>
    <label>Tipo<select name="type" required><option value="identity">Documento oficial com foto</option><option value="address">Comprovante de residência</option><option value="food">Manipulação de alimentos</option><option value="other">Outro documento</option></select></label>
    <label>Arquivo<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label>
    <button class="btn btn-primary" type="submit">Enviar para análise</button>
  </form>
</section>
<section class="panel">
  <div class="panel-head"><div><h2>Meus documentos</h2><p>Acompanhe o status de cada envio.</p></div></div>
  <div class="document-list">
    <?php foreach($documents as $d):?><div class="document-card"><div class="doc-icon"><?=icon('file',24)?></div><div class="grow"><strong><?=e($d['label'])?></strong><small><?=e($d['original_name']?:ucfirst($d['type']))?></small><?php if($d['status']==='rejected'&&!empty($d['rejection_reason'])):?><span class="reject-reason"><?=e($d['rejection_reason'])?></span><?php endif;?></div><span class="doc-status <?=e($d['status'])?>"><?=e(['pending'=>'Em análise','verified'=>'Verificado','rejected'=>'Rejeitado'][$d['status']]??ucfirst($d['status']))?></span></div><?php endforeach;?>
    <?php if(!$documents):?><div class="mini-empty">Nenhum documento enviado ainda.</div><?php endif;?>
  </div>
</section>