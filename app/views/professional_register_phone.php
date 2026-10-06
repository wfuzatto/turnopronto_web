<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contato e validação • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell professional-fast-shell professional-contact-shell">
  <section class="register-brand professional-contact-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <div class="contact-brand-copy">
      <span class="onboarding-kicker">Etapa 2 de 2</span>
      <h1>Seu cadastro está<br>quase pronto.</h1>
      <p>Agora precisamos apenas dos seus canais oficiais de contato para segurança, vagas e pagamentos.</p>
      <div class="register-points">
        <span>✓ CPF validado</span>
        <span>✓ Áreas de interesse salvas</span>
        <span>✓ Falta confirmar seu contato</span>
      </div>
    </div>
  </section>

  <section class="register-card professional-fast-card professional-contact-card">
    <img class="login-mobile-logo" src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto">

    <div class="contact-step-top">
      <a class="back-login" href="<?=e(url('cadastro/profissional'))?>">← Voltar</a>
      <span class="onboarding-kicker">Etapa 2 de 2</span>
    </div>

    <div class="contact-heading">
      <span class="contact-icon"><?=icon('shield',22)?></span>
      <div>
        <h2>Contato e validação</h2>
        <p>Informe seu e-mail e WhatsApp. Depois do código, você entra direto na plataforma.</p>
      </div>
    </div>

    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

    <form method="post" class="register-form professional-fast-form professional-contact-form">
      <?=csrf_field()?>

      <div class="register-grid contact-form-grid">
        <label class="wide">
          E-mail *
          <input type="email" name="email" value="<?=e($_POST['email']??'')?>" autocomplete="email" placeholder="voce@exemplo.com" required autofocus>
          <small class="field-note">Usado para formalização de vagas, pagamentos, recibos e recuperação da conta.</small>
        </label>

        <label class="wide">
          WhatsApp com DDD *
          <input name="phone" data-mask="phone" value="<?=e($_POST['phone']??'')?>" inputmode="tel" autocomplete="tel" placeholder="(35) 99999-9999" required>
          <small class="field-note">Usado para validação, segurança, convites e comunicações operacionais.</small>
        </label>
      </div>

      <div class="contact-security-note">
        <?=icon('shield',17)?>
        <span>Seus dados de contato não ficam expostos publicamente para outras pessoas na plataforma.</span>
      </div>

      <button class="btn btn-primary btn-block contact-submit" type="submit">Enviar código por WhatsApp</button>
    </form>

    <div class="contact-login-note">Seu login será feito com <strong>CPF + senha</strong>.</div>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>