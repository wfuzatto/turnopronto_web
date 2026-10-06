<?php
$error=flash('error');
$success=flash('success');
$p=$state['profile'];
$labels=[
  'identity'=>'Identificação',
  'location'=>'Localização',
  'payment'=>'Pagamento',
  'document'=>'Documento',
];
$currentNumber=['identity'=>1,'location'=>2,'payment'=>3,'document'=>4][$step]??4;
?>
<div class="professional-complete-page">
  <div class="page-head">
    <div>
      <h1>Complete seu perfil</h1>
      <p>Pedimos cada informação somente quando ela passa a ser necessária para você trabalhar.</p>
    </div>
    <span class="profile-progress-chip"><?=$state['progress']?>% completo</span>
  </div>

  <?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
  <?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

  <section class="panel progressive-overview">
    <div class="progressive-title">
      <div><strong>Falta pouco para se candidatar</strong><span>Seus dados ficam salvos a cada etapa.</span></div>
      <b><?=$state['progress']?>%</b>
    </div>
    <div class="progressive-bar"><i style="width:<?=$state['progress']?>%"></i></div>
    <div class="progressive-steps">
      <span class="<?=$state['identity_complete']?'done':($step==='identity'?'active':'' )?>">1. Identificação</span>
      <span class="<?=$state['location_complete']?'done':($step==='location'?'active':'' )?>">2. Endereço</span>
      <span class="<?=$state['payment_complete']?'done':($step==='payment'?'active':'' )?>">3. Pagamento</span>
      <span class="<?=$state['identity_submitted']?'done':($step==='document'?'active':'' )?>">4. Documento</span>
    </div>
  </section>

  <?php if($step==='identity'):?>
    <form method="post" class="panel progressive-form"><?=csrf_field()?><input type="hidden" name="step" value="identity">
      <div class="progressive-form-head"><span>1</span><div><h2>Identificação</h2><p>Precisamos destes dados apenas quando você decide se candidatar a uma vaga.</p></div></div>
      <div class="form-grid">
        <div><label>CPF</label><input value="<?=e($p['cpf']??'')?>" disabled></div>
        <div><label>Documento de identidade (RG/CIN) *</label><input name="rg" value="<?=e($_POST['rg']??$p['rg']??'')?>" autocomplete="off" required></div>
        <div><label>Data de nascimento *</label><input type="date" name="birth_date" value="<?=e($_POST['birth_date']??$p['birth_date']??'')?>" required></div>
        <div><label>E-mail <small>(opcional)</small></label><input type="email" name="email" value="<?=e($_POST['email']??$p['email']??'')?>" autocomplete="email" placeholder="voce@exemplo.com"></div>
        <div class="full"><label>Como você quer se apresentar? <small>(opcional)</small></label><input name="headline" value="<?=e($_POST['headline']??$p['headline']??'')?>" placeholder="Ex.: Garçom, recepcionista, auxiliar de eventos"></div>
      </div>
      <div class="progressive-actions"><button class="btn btn-primary" type="submit">Salvar e continuar →</button></div>
    </form>
  <?php elseif($step==='location'):?>
    <form method="post" class="panel progressive-form"><?=csrf_field()?><input type="hidden" name="step" value="location">
      <div class="progressive-form-head"><span>2</span><div><h2>Onde você está?</h2><p>Usamos sua localização para mostrar oportunidades relevantes e organizar o turno.</p></div></div>
      <div class="form-grid">
        <div><label>CEP *</label><input name="postal_code" data-mask="cep" inputmode="numeric" value="<?=e($_POST['postal_code']??$p['postal_code']??'')?>" placeholder="00000-000" required></div>
        <div><label>UF *</label><input name="state" maxlength="2" value="<?=e($_POST['state']??$p['state']??'MG')?>" required></div>
        <div class="full"><label>Endereço *</label><input name="address" value="<?=e($_POST['address']??$p['address']??'')?>" autocomplete="street-address" required></div>
        <div class="full"><label>Cidade *</label><input name="city" value="<?=e($_POST['city']??$p['city']??'')?>" required></div>
      </div>
      <div class="progressive-actions"><a class="btn btn-soft" href="<?=e(url('profissional/completar?step=identity'))?>">← Voltar</a><button class="btn btn-primary" type="submit">Salvar e continuar →</button></div>
    </form>
  <?php elseif($step==='payment'):?>
    <form method="post" class="panel progressive-form"><?=csrf_field()?><input type="hidden" name="step" value="payment">
      <div class="progressive-form-head"><span>3</span><div><h2>Como você vai receber?</h2><p>O Pix só passa a ser necessário agora, porque você está se preparando para trabalhar.</p></div></div>
      <div class="form-grid">
        <div><label>Tipo da chave Pix *
          <select name="pix_key_type" required>
            <?php $pixType=$_POST['pix_key_type']??$p['pix_key_type']??'cpf'; foreach(['cpf'=>'CPF','email'=>'E-mail','phone'=>'Telefone','random'=>'Chave aleatória'] as $v=>$label):?>
              <option value="<?=e($v)?>" <?=$pixType===$v?'selected':''?>><?=e($label)?></option>
            <?php endforeach;?>
          </select>
        </label></div>
        <div><label>Chave Pix *</label><input name="pix_key" value="<?=e($_POST['pix_key']??$p['pix_key']??'')?>" required></div>
        <div><label>Nome do titular *</label><input name="pix_holder_name" value="<?=e($_POST['pix_holder_name']??$p['pix_holder_name']??$p['name']??'')?>" required></div>
        <div><label>CPF do titular *</label><input name="pix_holder_document" data-mask="cpf" value="<?=e($_POST['pix_holder_document']??$p['pix_holder_document']??$p['cpf']??'')?>" readonly required><small class="field-note">Por segurança, o pagamento é feito somente ao titular desta conta.</small></div>
      </div>
      <div class="progressive-actions"><a class="btn btn-soft" href="<?=e(url('profissional/completar?step=location'))?>">← Voltar</a><button class="btn btn-primary" type="submit">Salvar e continuar →</button></div>
    </form>
  <?php elseif($step==='document'):?>
    <form method="post" enctype="multipart/form-data" class="panel progressive-form"><?=csrf_field()?><input type="hidden" name="step" value="document">
      <div class="progressive-form-head"><span>4</span><div><h2>Confirme sua identidade</h2><p>Envie um documento oficial com foto. Isso reduz perfis falsos e aumenta a confiança das empresas.</p></div></div>
      <div class="identity-upload-box">
        <?=icon('file',28)?>
        <div><strong>Documento oficial com foto</strong><span>RG ou CIN em PDF, JPG ou PNG · máximo 5 MB</span></div>
        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
      </div>
      <div class="progressive-actions"><a class="btn btn-soft" href="<?=e(url('profissional/completar?step=payment'))?>">← Voltar</a><button class="btn btn-primary" type="submit">Enviar e continuar</button></div>
    </form>
  <?php else:?>
    <section class="panel progressive-form progressive-waiting">
      <div class="progressive-status-icon"><?=$state['identity_verified']?icon('check',30):icon('clock',30)?></div>
      <?php if($state['identity_verified'] && $state['profile_verified']):?>
        <h2>Perfil verificado</h2>
        <p>Seu cadastro está completo e liberado para confirmação de turnos.</p>
      <?php elseif($state['identity_verified']):?>
        <h2>Documentação conferida</h2>
        <p>Seus dados estão completos. Falta apenas a liberação final da equipe TurnoPronto.</p>
      <?php else:?>
        <h2>Documento enviado</h2>
        <p>Seu documento está em análise. Enquanto isso, você já pode navegar e demonstrar interesse nas vagas.</p>
      <?php endif;?>
      <div class="progressive-actions centered"><a class="btn btn-primary" href="<?=e(url('profissional/oportunidades'))?>">Ver oportunidades</a><a class="btn btn-soft" href="<?=e(url('profissional/perfil'))?>">Meu perfil</a></div>
    </section>
  <?php endif;?>
</div>