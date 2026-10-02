<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar conta • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell">
  <section class="register-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <h1>Nós cuidamos do extra<br>que você precisa.</h1>
    <p>Uma conta para encontrar reforço para sua operação ou transformar seu tempo livre em renda extra.</p>
    <div class="register-points"><span>✓ Perfis verificados</span><span>✓ Presença e pontualidade</span><span>✓ Turnos com data, hora e valor claros</span></div>
  </section>
  <section class="register-card">
    <?php if($kind==='choice'):?>
      <a class="back-login" href="<?=e(url('login'))?>">← Voltar para entrar</a>
      <h2>Como você quer usar a TurnoPronto?</h2><p>Escolha o tipo de conta.</p>
      <div class="account-choice">
        <a href="<?=e(url('cadastro/empresa'))?>" class="account-card"><span class="account-icon"><?=icon('briefcase',28)?></span><strong>Sou empresa</strong><small>Quero publicar turnos e encontrar profissionais.</small><b>Continuar →</b></a>
        <a href="<?=e(url('cadastro/profissional'))?>" class="account-card"><span class="account-icon green"><?=icon('users',28)?></span><strong>Sou profissional</strong><small>Quero escolher turnos e fazer renda extra.</small><b>Continuar →</b></a>
      </div>
    <?php else:?>
      <a class="back-login" href="<?=e(url('cadastro'))?>">← Trocar tipo de conta</a>
      <h2><?=$kind==='company'?'Cadastrar empresa':'Cadastrar profissional'?></h2>
      <p><?=$kind==='company'?'Crie o acesso responsável pela conta empresarial.':'Monte seu perfil inicial. A validação é feita antes do primeiro turno.'?></p>
      <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
      <form method="post" class="register-form"><?=csrf_field()?>
        <div class="register-grid">
          <label>Nome do responsável / nome completo *<input name="name" value="<?=e($_POST['name']??'')?>" required></label>
          <label>Telefone *<input name="phone" value="<?=e($_POST['phone']??'')?>" required></label>
          <label class="wide">E-mail *<input type="email" name="email" value="<?=e($_POST['email']??'')?>" required></label>
          <?php if($kind==='company'):?>
            <label>Razão social *<input name="legal_name" value="<?=e($_POST['legal_name']??'')?>" required></label>
            <label>Nome fantasia *<input name="trade_name" value="<?=e($_POST['trade_name']??'')?>" required></label>
            <label>CNPJ *<input name="cnpj" value="<?=e($_POST['cnpj']??'')?>" required></label>
            <label>UF *<input name="state" maxlength="2" value="<?=e($_POST['state']??'MG')?>" required></label>
            <label class="wide">Endereço *<input name="address" value="<?=e($_POST['address']??'')?>" required></label>
            <label class="wide">Cidade *<input name="city" value="<?=e($_POST['city']??'')?>" required></label>
          <?php else:?>
            <label>CPF *<input name="cpf" value="<?=e($_POST['cpf']??'')?>" required></label>
            <label>UF *<input name="state" maxlength="2" value="<?=e($_POST['state']??'MG')?>" required></label>
            <label class="wide">Cidade *<input name="city" value="<?=e($_POST['city']??'')?>" required></label>
            <label class="wide">Apresentação profissional *<input name="headline" value="<?=e($_POST['headline']??'')?>" placeholder="Ex.: Garçom • Recepcionista" required></label>
            <fieldset class="wide category-checks"><legend>Em quais funções você quer trabalhar? *</legend><?php foreach($categories as $cat):?><label><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((string)$cat['id'],array_map('strval',(array)($_POST['categories']??[])),true)?'checked':''?>> <span><?=e($cat['name'])?></span></label><?php endforeach;?></fieldset>
          <?php endif;?>
          <label>Senha *<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
          <label>Confirmar senha *<input type="password" name="password_confirm" minlength="8" required autocomplete="new-password"></label>
        </div>
        <label class="terms-check"><input type="checkbox" required> <span>Li e concordo em prosseguir com o cadastro e a verificação dos dados informados.</span></label>
        <button class="btn btn-primary btn-block" type="submit">Criar minha conta</button>
      </form>
      <p class="register-login">Já tem conta? <a href="<?=e(url('login'))?>">Entrar</a></p>
    <?php endif;?>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>