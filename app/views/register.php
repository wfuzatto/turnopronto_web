<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar conta • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell">
  <section class="register-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <h1>Nós cuidamos do extra<br>que você precisa.</h1>
    <p>Cadastro com telefone validado, dados de pagamento e informações suficientes para começar com segurança.</p>
    <div class="register-points"><span>✓ WhatsApp validado por código</span><span>✓ CPF/CNPJ conferido</span><span>✓ Pix preparado para repasses e devoluções</span></div>
  </section>
  <section class="register-card">
    <?php if($kind==='choice'):?>
      <a class="back-login" href="<?=e(url('login'))?>">← Voltar para entrar</a>
      <h2>Criar sua conta</h2><p>Escolha como você vai usar o TurnoPronto.</p>
      <div class="account-choice">
        <a href="<?=e(url('cadastro/empresa'))?>" class="account-card"><span class="account-icon"><?=icon('briefcase',28)?></span><strong>Sou empresa</strong><small>Publicar turnos, receber interessados e organizar pagamentos/devoluções.</small><b>Continuar →</b></a>
        <a href="<?=e(url('cadastro/profissional'))?>" class="account-card"><span class="account-icon green"><?=icon('users',28)?></span><strong>Sou profissional</strong><small>Encontrar turnos, receber contatos e receber pagamentos por Pix.</small><b>Continuar →</b></a>
      </div>
    <?php else:?>
      <a class="back-login" href="<?=e(url('cadastro'))?>">← Trocar tipo de conta</a>
      <h2><?=$kind==='company'?'Cadastrar empresa':'Cadastrar profissional'?></h2>
      <p>Ao final enviaremos um código para o WhatsApp informado.</p>
      <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
      <form method="post" class="register-form"><?=csrf_field()?>
        <div class="register-grid">
          <label>Nome completo <?=$kind==='company'?'do responsável':''?> *<input name="name" value="<?=e($_POST['name']??'')?>" autocomplete="name" required></label>
          <label>WhatsApp com DDD *<input name="phone" data-mask="phone" value="<?=e($_POST['phone']??'')?>" inputmode="tel" autocomplete="tel" placeholder="(35) 99999-9999" required></label>
          <label class="wide">E-mail *<input type="email" name="email" value="<?=e($_POST['email']??'')?>" autocomplete="email" required></label>

          <?php if($kind==='company'):?>
            <label>CPF do responsável *<input name="responsible_cpf" data-mask="cpf" value="<?=e($_POST['responsible_cpf']??'')?>" inputmode="numeric" required></label>
            <label>CNPJ *<input name="cnpj" data-mask="cnpj" value="<?=e($_POST['cnpj']??'')?>" inputmode="numeric" required></label>
            <label>Razão social *<input name="legal_name" value="<?=e($_POST['legal_name']??'')?>" required></label>
            <label>Nome fantasia *<input name="trade_name" value="<?=e($_POST['trade_name']??'')?>" required></label>
          <?php else:?>
            <label>CPF *<input name="cpf" data-mask="cpf" value="<?=e($_POST['cpf']??'')?>" inputmode="numeric" required></label>
            <label>RG *<input name="rg" data-mask="rg" value="<?=e($_POST['rg']??'')?>" inputmode="text" autocomplete="off" required></label>
            <label>Data de nascimento *<input type="date" name="birth_date" value="<?=e($_POST['birth_date']??'')?>" required></label>
            <label class="wide">Atividade / apresentação profissional *<input name="headline" value="<?=e($_POST['headline']??'')?>" placeholder="Ex.: Garçom • Recepcionista" required></label>
          <?php endif;?>

          <label>CEP *<input name="postal_code" data-mask="cep" value="<?=e($_POST['postal_code']??'')?>" inputmode="numeric" placeholder="00000-000" required></label>
          <label>UF *<input name="state" maxlength="2" value="<?=e($_POST['state']??'MG')?>" required></label>
          <label class="wide">Endereço *<input name="address" value="<?=e($_POST['address']??'')?>" autocomplete="street-address" required></label>
          <label class="wide">Cidade *<input name="city" value="<?=e($_POST['city']??'')?>" required></label>

          <?php if($kind==='professional'):?>
            <fieldset class="wide category-checks"><legend>Em quais funções você quer trabalhar? *</legend><?php foreach($categories as $cat):?><label><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((string)$cat['id'],array_map('strval',(array)($_POST['categories']??[])),true)?'checked':''?>> <span><?=e($cat['name'])?></span></label><?php endforeach;?></fieldset>
          <?php endif;?>

          <div class="wide register-section-title"><strong>Dados Pix</strong><small><?=$kind==='company'?'Usados para devoluções/reembolsos quando aplicável.':'Usados para receber os pagamentos dos turnos.'?></small></div>
          <label>Tipo da chave Pix *
            <select name="pix_key_type" required>
              <?php $pixType=$_POST['pix_key_type']??''; foreach(['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','phone'=>'Telefone','random'=>'Chave aleatória'] as $v=>$label):?>
                <option value="<?=e($v)?>" <?=$pixType===$v?'selected':''?>><?=e($label)?></option>
              <?php endforeach;?>
            </select>
          </label>
          <label>Chave Pix *<input name="pix_key" value="<?=e($_POST['pix_key']??'')?>" required></label>
          <label>Nome do titular Pix *<input name="pix_holder_name" value="<?=e($_POST['pix_holder_name']??'')?>" required></label>
          <label>CPF/CNPJ do titular Pix *<input name="pix_holder_document" data-mask="cpfcnpj" value="<?=e($_POST['pix_holder_document']??'')?>" inputmode="numeric" required></label>

          <label>Senha *<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
          <label>Confirmar senha *<input type="password" name="password_confirm" minlength="8" required autocomplete="new-password"></label>
        </div>

        <label class="terms-check"><input type="checkbox" name="terms_accepted" value="1" required> <span>Li e aceito os <a href="<?=e(url('termos'))?>" target="_blank">Termos de Uso</a>.</span></label>
        <label class="terms-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>Li e aceito a <a href="<?=e(url('privacidade'))?>" target="_blank">Política de Privacidade</a> e o tratamento dos dados necessários ao serviço.</span></label>
        <label class="terms-check"><input type="checkbox" name="whatsapp_consent" value="1" required> <span>Autorizo mensagens transacionais no WhatsApp para validar meu número e receber comunicações de cadastro, segurança, interesse em vagas e turnos.</span></label>

        <button class="btn btn-primary btn-block" type="submit">Continuar e validar WhatsApp</button>
      </form>
      <p class="register-login">Já tem conta? <a href="<?=e(url('login'))?>">Entrar</a></p>
    <?php endif;?>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>