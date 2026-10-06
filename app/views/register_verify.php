<?php $error=flash('error'); $success=flash('success'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Validar WhatsApp • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset('css/register-verify.css'))?>"></head><body class="login-page">
<div class="register-shell">
  <section class="register-brand"><a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a><h1>Confirme seu WhatsApp.</h1><p>Isso garante que o contato usado para oportunidades e segurança realmente pertence a você.</p></section>
  <section class="register-card">
    <h2>Código de verificação</h2>
    <?php if(!empty($pending['development_bypass'])):?>
      <div class="alert success"><strong>Modo de desenvolvimento:</strong> nenhum WhatsApp foi enviado. Use o código fixo <strong><?=e($pending['development_code']??'000111')?></strong>.</div>
      <p>Digite o código acima para validar normalmente o cadastro de <strong><?=e($pending['phone_masked']??'seu telefone')?></strong>.</p>
    <?php else:?>
      <p>Enviamos um código de 6 dígitos para <strong><?=e($pending['phone_masked']??'seu WhatsApp')?></strong>. Ele expira em 10 minutos.</p>
    <?php endif;?>
    <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
    <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
    <form method="post" class="register-form"><?=csrf_field()?>
      <input type="hidden" name="action" value="verify">
      <label class="verify-code-field" for="verification-code">Código recebido
        <input id="verification-code" class="verify-code-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
      </label>
      <button class="btn btn-primary btn-block" type="submit"><?=($pending['role']??'')==='professional'?'Validar e ver oportunidades':'Validar e concluir cadastro'?></button>
    </form>
    <form method="post" style="margin-top:12px"><?=csrf_field()?><input type="hidden" name="action" value="resend"><button class="btn btn-soft btn-block" type="submit">Reenviar código</button></form>
    <p class="register-login"><a href="<?=e(url(($pending['role']??'')==='professional'?'cadastro/profissional':'cadastro'))?>">Começar novamente</a></p>
  </section>
</div>
</body></html>