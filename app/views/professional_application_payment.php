<?php $error=flash('error'); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informações de pagamento • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head>
<body class="login-page application-onboarding-page">
<div class="application-onboarding-shell">
  <aside class="application-vacancy-summary">
    <a href="<?=e(url('vagas/'.$shift['id']))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <span class="public-jobs-kicker">Sua primeira candidatura</span>
    <h2><?=e($shift['category_name'])?></h2><strong><?=e($shift['company_name'])?></strong>
    <div class="application-vacancy-meta"><span><?=br_date($shift['starts_at'],'d/m/Y')?></span><span><?=date('H:i',strtotime($shift['starts_at']))?> – <?=date('H:i',strtotime($shift['ends_at']))?></span><b><?=money($shift['shift_value'])?></b></div>
    <p>Depois desta etapa sua candidatura será enviada automaticamente.</p>
  </aside>
  <section class="application-onboarding-card">
    <div class="application-step-head"><span>Cadastro para candidatura</span><span>Etapa 3 de 3</span></div>
    <div class="application-stepper"><i class="done">✓</i><span class="done"></span><i class="done">✓</i><span class="done"></span><i class="active">3</i></div>
    <h1>Informações de pagamento</h1><p>Precisamos saber onde fazer o repasse quando você concluir um turno.</p>
    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
    <form method="post" class="application-form"><?=csrf_field()?>
      <div class="application-form-grid">
        <label>Tipo da chave Pix *<select name="pix_key_type" required><?php foreach(['cpf'=>'CPF','email'=>'E-mail','phone'=>'Telefone','random'=>'Chave aleatória'] as $v=>$label):?><option value="<?=e($v)?>" <?=($_POST['pix_key_type']??'cpf')===$v?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
        <label>Chave Pix *<input name="pix_key" value="<?=e($_POST['pix_key']??$profile['cpf']??'')?>" required></label>
      </div>
      <label>Nome do titular *<input name="pix_holder_name" value="<?=e($_POST['pix_holder_name']??$profile['name']??'')?>" required></label>
      <label>CPF do titular *<input name="pix_holder_document" data-mask="cpf" value="<?=e($_POST['pix_holder_document']??$profile['cpf']??'')?>" readonly required><small class="field-note">Por segurança, o pagamento deve estar vinculado ao mesmo CPF do cadastro.</small></label>
      <button class="btn btn-primary btn-block application-next" type="submit">Concluir e enviar candidatura →</button>
    </form>
    <div class="application-why">Depois da candidatura, você poderá completar identidade e endereço sem perder o interesse enviado para esta vaga.</div>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>