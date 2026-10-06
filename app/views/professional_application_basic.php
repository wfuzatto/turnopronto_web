<?php $error=flash('error'); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informações básicas • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head>
<body class="login-page application-onboarding-page">
<div class="application-onboarding-shell">
  <aside class="application-vacancy-summary">
    <a href="<?=e(url('vagas/'.$shift['id']))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <span class="public-jobs-kicker">Você escolheu esta vaga</span>
    <h2><?=e($shift['category_name'])?></h2>
    <strong><?=e($shift['company_name'])?></strong>
    <div class="application-vacancy-meta"><span><?=br_date($shift['starts_at'],'d/m/Y')?></span><span><?=date('H:i',strtotime($shift['starts_at']))?> – <?=date('H:i',strtotime($shift['ends_at']))?></span><span><?=e($shift['city'].' - '.$shift['state'])?></span><b><?=money($shift['shift_value'])?></b></div>
  </aside>
  <section class="application-onboarding-card">
    <div class="application-step-head"><a href="<?=e(url('vagas/'.$shift['id']))?>">← Voltar à vaga</a><span>Etapa 1 de 3</span></div>
    <div class="application-stepper"><i class="active">1</i><span></span><i>2</i><span></span><i>3</i></div>
    <h1>Informações básicas</h1><p>Começamos pelo essencial.</p>
    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
    <form method="post" class="application-form"><?=csrf_field()?>
      <label>Nome completo *<input name="name" value="<?=e($_POST['name']??'')?>" autocomplete="name" required autofocus></label>
      <div class="application-form-grid">
        <label>CPF *<input name="cpf" data-mask="cpf" value="<?=e($_POST['cpf']??'')?>" inputmode="numeric" autocomplete="username" required></label>
        <label>Data de nascimento *<input type="date" name="birth_date" value="<?=e($_POST['birth_date']??'')?>" required></label>
      </div>
      <div class="application-form-grid">
        <label>Senha *<span class="password-field"><input type="password" name="password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha"><?=icon('eye',16)?></button></span></label>
        <label>Confirmar senha *<span class="password-field"><input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha"><?=icon('eye',16)?></button></span></label>
      </div>
      <label class="terms-check"><input type="checkbox" name="legal_accepted" value="1" required><span>Li e aceito os <a href="<?=e(url('termos'))?>" target="_blank">Termos de Uso</a> e a <a href="<?=e(url('privacidade'))?>" target="_blank">Política de Privacidade</a>.</span></label>
      <button class="btn btn-primary btn-block application-next" type="submit">Continuar → contato</button>
    </form>
    <div class="application-existing-account">Já tem conta? <a href="<?=e(url('login'))?>">Entrar e continuar nesta vaga</a></div>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>