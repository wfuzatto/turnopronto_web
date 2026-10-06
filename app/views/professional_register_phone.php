<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmar WhatsApp • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell professional-fast-shell">
  <section class="register-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <h1>Seu cadastro já está quase pronto.</h1>
    <p>Agora salvamos seus canais oficiais de contato. Eles serão usados para formalizar candidaturas, turnos, pagamentos, comprovantes e segurança da conta.</p>
    <div class="register-points"><span>✓ Seu CPF já foi validado</span><span>✓ Suas áreas de interesse já foram salvas</span><span>✓ Falta informar e-mail e confirmar o WhatsApp</span></div>
  </section>
  <section class="register-card professional-fast-card">
    <img class="login-mobile-logo" src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto">
    <a class="back-login" href="<?=e(url('cadastro/profissional'))?>">← Voltar</a>
    <div class="onboarding-kicker">Etapa 2 de 2</div>
    <h2>Contato e validação</h2>
    <p>Informe seu e-mail e WhatsApp. Depois do código você entra direto na plataforma e já pode ver oportunidades das áreas escolhidas.</p>
    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
    <form method="post" class="register-form"><?=csrf_field()?>
      <label>E-mail *
        <input type="email" name="email" value="<?=e($_POST['email']??'')?>" autocomplete="email" placeholder="voce@exemplo.com" required autofocus>
        <small class="field-note">Usado para comunicações formais de vagas, pagamentos, recibos e recuperação da conta.</small>
      </label>
      <label>WhatsApp com DDD *
        <input name="phone" data-mask="phone" value="<?=e($_POST['phone']??'')?>" inputmode="tel" autocomplete="tel" placeholder="(35) 99999-9999" required>
      </label>
      <label class="terms-check professional-whatsapp-consent"><input type="checkbox" checked disabled> <span>Usaremos este número somente para validação, segurança, convites e comunicações transacionais do TurnoPronto.</span></label>
      <button class="btn btn-primary btn-block" type="submit">Enviar código por WhatsApp</button>
    </form>
    <p class="register-login">Seu login será feito com <strong>CPF + senha</strong>.</p>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>