<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmar WhatsApp • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell professional-fast-shell">
  <section class="register-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <h1>Seu cadastro já está quase pronto.</h1>
    <p>Usamos o WhatsApp para confirmar que existe uma pessoa real por trás da conta e para avisar sobre vagas e segurança.</p>
    <div class="register-points"><span>✓ Seu CPF já foi validado</span><span>✓ Suas áreas de interesse já foram salvas</span><span>✓ Falta somente confirmar seu telefone</span></div>
  </section>
  <section class="register-card professional-fast-card">
    <img class="login-mobile-logo" src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto">
    <a class="back-login" href="<?=e(url('cadastro/profissional'))?>">← Voltar</a>
    <div class="onboarding-kicker">Etapa 2 de 2</div>
    <h2>Confirme seu WhatsApp</h2>
    <p>Depois do código você entra direto na plataforma e já pode ver oportunidades de <?=e($draft['name']??'seu interesse')?>.</p>
    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
    <form method="post" class="register-form"><?=csrf_field()?>
      <label>WhatsApp com DDD *
        <input name="phone" data-mask="phone" value="<?=e($_POST['phone']??'')?>" inputmode="tel" autocomplete="tel" placeholder="(35) 99999-9999" required autofocus>
      </label>
      <label class="terms-check professional-whatsapp-consent"><input type="checkbox" checked disabled> <span>Usaremos este número somente para validação, segurança, convites e comunicações transacionais do TurnoPronto.</span></label>
      <button class="btn btn-primary btn-block" type="submit">Enviar código por WhatsApp</button>
    </form>
    <p class="register-login">Seu login será feito com <strong>CPF + senha</strong>.</p>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>