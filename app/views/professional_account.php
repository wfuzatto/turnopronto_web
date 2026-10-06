<?php
$progress=$onboarding['progress']??0;
$status=$profile['status']??'pending';
?>
<div class="page-head">
  <div><h1>Meu perfil</h1><p>Você pode entrar e explorar vagas com um cadastro simples. Complete o restante somente quando precisar.</p></div>
  <span class="status <?=e($status==='verified'?'confirmed':'filling')?>"><?=e($status==='verified'?'Verificado':'Em construção')?></span>
</div>

<section class="panel profile-completion-card">
  <div class="profile-completion-top">
    <div><h2>Seu perfil está <?=$progress?>% completo</h2><p>Os dados são solicitados por etapas para deixar o cadastro mais rápido e simples.</p></div>
    <strong><?=$progress?>%</strong>
  </div>
  <div class="progressive-bar"><i style="width:<?=$progress?>%"></i></div>
  <div class="profile-completion-grid">
    <span class="<?=!empty($onboarding['steps']['account'])?'done':''?>">✓ Conta criada</span>
    <span class="<?=!empty($onboarding['steps']['whatsapp'])?'done':''?>">✓ WhatsApp</span>
    <span class="<?=!empty($onboarding['steps']['interests'])?'done':''?>">✓ Áreas de interesse</span>
    <span class="<?=!empty($onboarding['steps']['identity_data'])?'done':''?>">Identificação</span>
    <span class="<?=!empty($onboarding['steps']['location'])?'done':''?>">Endereço</span>
    <span class="<?=!empty($onboarding['steps']['payment'])?'done':''?>">Pagamento</span>
    <span class="<?=!empty($onboarding['steps']['identity_document'])?'done':''?>">Documento</span>
  </div>
  <div class="profile-completion-actions">
    <?php if(!$onboarding['can_apply']):?>
      <a class="btn btn-primary" href="<?=e(url('profissional/completar?step='.$onboarding['next_step']))?>">Completar o que falta</a>
      <span>Você só precisa concluir isso quando quiser se candidatar.</span>
    <?php elseif(!$onboarding['profile_verified']):?>
      <a class="btn btn-primary" href="<?=e(url('profissional/completar?step='.$onboarding['next_step']))?>">Ver status da verificação</a>
      <span>Você já pode demonstrar interesse em vagas enquanto sua identidade é analisada.</span>
    <?php else:?>
      <span class="profile-ready"><?=icon('check',18)?> Perfil pronto para trabalhar.</span>
    <?php endif;?>
  </div>
</section>

<form method="post" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Informações do perfil</h2><p>Estes dados ajudam as empresas a entender quem você é. Os campos adicionais ficam no fluxo de candidatura.</p></div></div>
    <div class="form-grid">
      <div><label>Nome *</label><input name="name" value="<?=e($_POST['name']??$profile['name']??'')?>" required></div>
      <div><label>CPF</label><input value="<?=e($profile['cpf']??'')?>" disabled></div>
      <div><label>WhatsApp</label><input value="<?=e($profile['phone']??'')?>" disabled><small class="field-note">Número validado no cadastro.</small></div>
      <div><label>E-mail *</label><input type="email" name="email" value="<?=e($_POST['email']??$profile['email']??'')?>" placeholder="voce@exemplo.com" required><small class="field-note">Canal formal para vagas, pagamentos e recuperação da conta.</small></div>
      <div class="full"><label>Atividade / apresentação</label><input name="headline" value="<?=e($_POST['headline']??$profile['headline']??'')?>" placeholder="Ex.: Garçom • Recepcionista"></div>
      <div class="full"><label>Sobre você</label><textarea name="bio" rows="4" maxlength="2000" placeholder="Experiência, disponibilidade, diferenciais..."><?=e($_POST['bio']??$profile['bio']??'')?></textarea></div>
    </div>
  </section>

  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Áreas de interesse</h2><p>As oportunidades e notificações serão filtradas pelas funções escolhidas aqui.</p></div></div>
    <div class="category-profile-grid"><?php foreach($categories as $cat):?><label class="category-profile"><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((int)$cat['id'],array_map('intval',(array)($_POST['categories']??$selected)),true)?'checked':''?>><span><?=icon('briefcase',18)?><b><?=e($cat['name'])?></b></span></label><?php endforeach;?></div>
  </section>

  <div class="account-save">
    <a class="btn btn-soft" href="<?=e(url('profissional/documentos'))?>">Meus documentos</a>
    <button class="btn btn-primary" type="submit">Salvar perfil</button>
  </div>
</form>