<?php $error=flash('error'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar conta • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="register-shell <?=$kind==='professional'?'professional-fast-shell':''?>">
  <section class="register-brand">
    <a href="<?=e(url('login'))?>"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"></a>
    <?php if($kind==='professional'):?>
      <h1>Encontre oportunidades primeiro.<br>Complete o restante quando precisar.</h1>
      <p>Você não precisa preencher um formulário enorme para conhecer o TurnoPronto.</p>
      <div class="register-points"><span>✓ CPF válido e único</span><span>✓ Vagas pelas áreas que você escolher</span><span>✓ Dados adicionais somente ao se candidatar</span></div>
    <?php else:?>
      <h1>Nós cuidamos do extra<br>que você precisa.</h1>
      <p>Cadastro empresarial com dados suficientes para publicar vagas e operar com segurança.</p>
      <div class="register-points"><span>✓ WhatsApp validado por código</span><span>✓ CPF/CNPJ conferido</span><span>✓ Dados de pagamento preparados</span></div>
    <?php endif;?>
  </section>

  <section class="register-card <?=$kind==='professional'?'professional-fast-card':''?>">
    <img class="login-mobile-logo" src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto">

    <?php if($kind==='choice'):?>
      <a class="back-login" href="<?=e(url('login'))?>">← Voltar para entrar</a>
      <h2>Criar sua conta</h2><p>Escolha como você vai usar o TurnoPronto.</p>
      <div class="account-choice">
        <a href="<?=e(url('cadastro/empresa'))?>" class="account-card"><span class="account-icon"><?=icon('briefcase',28)?></span><strong>Sou empresa</strong><small>Publicar turnos, receber interessados e organizar pagamentos/devoluções.</small><b>Continuar →</b></a>
        <a href="<?=e(url('cadastro/profissional'))?>" class="account-card"><span class="account-icon green"><?=icon('users',28)?></span><strong>Sou profissional</strong><small>Crie sua conta rapidamente e veja vagas das áreas que interessam a você.</small><b>Continuar →</b></a>
      </div>

    <?php elseif($kind==='professional'):?>
      <a class="back-login" href="<?=e(url('cadastro'))?>">← Trocar tipo de conta</a>
      <div class="onboarding-kicker">Etapa 1 de 2 · cadastro rápido</div>
      <h2>Comece em menos de 1 minuto</h2>
      <p>Primeiro, só o necessário para criar sua conta e mostrar oportunidades relevantes.</p>
      <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

      <form method="post" class="register-form professional-fast-form"><?=csrf_field()?>
        <div class="register-grid">
          <label class="wide">Nome completo *<input name="name" value="<?=e($_POST['name']??'')?>" autocomplete="name" required autofocus></label>
          <label class="wide">CPF *<input name="cpf" data-mask="cpf" value="<?=e($_POST['cpf']??'')?>" inputmode="numeric" autocomplete="username" placeholder="000.000.000-00" required><small class="field-note">Seu CPF será seu login no TurnoPronto.</small></label>

          <fieldset class="wide category-checks professional-interest-checks">
            <legend>Em quais áreas você quer encontrar vagas? *</legend>
            <p class="field-note">Escolha uma ou mais. Você poderá alterar depois.</p>
            <?php foreach($categories as $cat):?><label><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((string)$cat['id'],array_map('strval',(array)($_POST['categories']??[])),true)?'checked':''?>> <span><?=e($cat['name'])?></span></label><?php endforeach;?>
          </fieldset>

          <div class="register-field"><label for="register-password">Senha *</label><span class="password-field"><input id="register-password" type="password" name="password" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha" aria-pressed="false"><?=icon('eye',16)?></button></span></div>
          <div class="register-field"><label for="register-password-confirm">Confirmar senha *</label><span class="password-field"><input id="register-password-confirm" type="password" name="password_confirm" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha" aria-pressed="false"><?=icon('eye',16)?></button></span></div>
        </div>

        <label class="terms-check professional-legal-check"><input type="checkbox" name="legal_accepted" value="1" required> <span>Li e aceito os <a href="<?=e(url('termos'))?>" target="_blank">Termos de Uso</a> e a <a href="<?=e(url('privacidade'))?>" target="_blank">Política de Privacidade</a>.</span></label>

        <button class="btn btn-primary btn-block" type="submit">Continuar → confirmar WhatsApp</button>
      </form>
      <div class="progressive-registration-note"><?=icon('shield',17)?><span>RG, endereço, documento e Pix serão solicitados somente quando você quiser se candidatar a uma vaga.</span></div>
      <p class="register-login">Já tem conta? <a href="<?=e(url('login'))?>">Entrar com CPF</a></p>

    <?php else:?>
      <a class="back-login" href="<?=e(url('cadastro'))?>">← Trocar tipo de conta</a>
      <h2>Cadastrar empresa</h2>
      <?php if((bool)app_config('debug') && (bool)app_config('registration.development_whatsapp_bypass')):?>
        <p><strong>Modo de desenvolvimento:</strong> a validação continuará ativa, mas nenhum WhatsApp será enviado. Use o código fixo <strong><?=e((string)(app_config('registration.development_code')??'000111'))?></strong>.</p>
      <?php else:?>
        <p>Ao final enviaremos um código para o WhatsApp informado.</p>
      <?php endif;?>
      <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

      <form method="post" class="register-form"><?=csrf_field()?>
        <div class="register-grid">
          <label>Nome completo do responsável *<input name="name" value="<?=e($_POST['name']??'')?>" autocomplete="name" required></label>
          <label>WhatsApp com DDD *<input name="phone" data-mask="phone" value="<?=e($_POST['phone']??'')?>" inputmode="tel" autocomplete="tel" placeholder="(35) 99999-9999" required></label>
          <label class="wide">E-mail *<input type="email" name="email" value="<?=e($_POST['email']??'')?>" autocomplete="email" required></label>
          <label>CPF do responsável *<input name="responsible_cpf" data-mask="cpf" value="<?=e($_POST['responsible_cpf']??'')?>" inputmode="numeric" required></label>
          <label>CNPJ *<input name="cnpj" data-mask="cnpj" value="<?=e($_POST['cnpj']??'')?>" inputmode="numeric" required></label>
          <label>Razão social *<input name="legal_name" value="<?=e($_POST['legal_name']??'')?>" required></label>
          <label>Nome fantasia *<input name="trade_name" value="<?=e($_POST['trade_name']??'')?>" required></label>
          <label>CEP *<input name="postal_code" data-mask="cep" value="<?=e($_POST['postal_code']??'')?>" inputmode="numeric" placeholder="00000-000" required></label>
          <label>UF *<input name="state" maxlength="2" value="<?=e($_POST['state']??'MG')?>" required></label>
          <label class="wide">Endereço *<input name="address" value="<?=e($_POST['address']??'')?>" autocomplete="street-address" required></label>
          <label class="wide">Cidade *<input name="city" value="<?=e($_POST['city']??'')?>" required></label>
          <label class="wide">Link do Google Maps<input type="url" name="maps_url" value="<?=e($_POST['maps_url']??'')?>" placeholder="https://maps.app.goo.gl/..." autocomplete="url"><small class="field-note">Cole o link compartilhado da localização principal da empresa.</small></label>

          <div class="wide register-section-title"><strong>Dados Pix</strong><small>Usados para devoluções/reembolsos quando aplicável.</small></div>
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

          <div class="register-field"><label for="register-password">Senha *</label><span class="password-field"><input id="register-password" type="password" name="password" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha" aria-pressed="false"><?=icon('eye',16)?></button></span></div>
          <div class="register-field"><label for="register-password-confirm">Confirmar senha *</label><span class="password-field"><input id="register-password-confirm" type="password" name="password_confirm" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar senha" aria-pressed="false"><?=icon('eye',16)?></button></span></div>
        </div>

        <label class="terms-check"><input type="checkbox" name="terms_accepted" value="1" required> <span>Li e aceito os <a href="<?=e(url('termos'))?>" target="_blank">Termos de Uso</a>.</span></label>
        <label class="terms-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>Li e aceito a <a href="<?=e(url('privacidade'))?>" target="_blank">Política de Privacidade</a>.</span></label>
        <label class="terms-check"><input type="checkbox" name="whatsapp_consent" value="1" required> <span>Autorizo mensagens transacionais no WhatsApp para validar meu número e receber comunicações de cadastro, segurança e turnos.</span></label>

        <button class="btn btn-primary btn-block" type="submit">Continuar e validar WhatsApp</button>
      </form>
      <p class="register-login">Já tem conta? <a href="<?=e(url('login'))?>">Entrar</a></p>
    <?php endif;?>
  </section>
</div>
<script src="<?=e(asset('js/app.js'))?>"></script></body></html>