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

<form method="post" enctype="multipart/form-data" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Foto de perfil</h2><p>Use uma foto nítida. Ela será exibida para as empresas ao visualizar seu perfil, candidatura ou interesse em uma vaga.</p></div></div>
    <div class="profile-image-upload">
      <div class="profile-image-preview professional <?=!empty($profile['avatar_url'])?'has-image':''?>" data-profile-image-preview>
        <?php if(!empty($profile['avatar_url'])):?><img src="<?=e($profile['avatar_url'])?>" alt="<?=e($profile['name']??'Profissional')?>"><?php else:?><span><?=e(mb_strtoupper(mb_substr($profile['name']??'P',0,1)))?></span><?php endif;?>
      </div>
      <div class="profile-image-controls">
        <label for="professional-profile-image">Escolher nova foto</label>
        <input id="professional-profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-profile-image-input>
        <small>JPG, PNG ou WEBP, até 8 MB. Ao salvar, a nova foto substitui a atual.</small>
      </div>
    </div>
  </section>

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
<section class="panel" style="margin-top:16px">
  <div class="panel-head"><div><h2>Notificações de vagas</h2>
    <p>Escolha a distância máxima para receber avisos de vagas nas suas áreas de interesse.</p></div></div>
  <form id="notification-radius-form" method="post" action="<?=e(url('profissional/perfil/notificacoes'))?>">
    <?=csrf_field()?>
    <?php $savedRadius=(int)($push_preferences['radius_km']??50); ?>
    <label for="notification-radius-slider">
      <strong>Notificações até: <output id="notification-radius-value"><?=$savedRadius?> km</output></strong>
    </label>
    <input type="range" id="notification-radius-slider" name="radius_km"
           min="5" max="200" step="5" value="<?=$savedRadius?>" style="width:100%;margin:20px 0 5px">
    <div style="display:flex;justify-content:space-between"><small>5 km</small><small>200 km</small></div>
    <input id="notification-origin-lat" type="hidden" name="latitude"
           value="<?=e($push_preferences['latitude']??'')?>">
    <input id="notification-origin-lng" type="hidden" name="longitude"
           value="<?=e($push_preferences['longitude']??'')?>">
    <p id="notification-location-state" class="field-note">
      <?=!empty($push_preferences['configured'])?'Distância ativa a partir do ponto de referência salvo.':'Defina e salve o raio para ativar este filtro.'?>
      A distância é calculada em linha reta; não rastreamos seus deslocamentos continuamente.
    </p>
    <div class="profile-completion-actions">
      <button id="notification-use-location" type="button" class="btn btn-soft">Usar minha localização atual</button>
      <button type="submit" class="btn btn-primary">Salvar distância</button>
    </div>
  </form>
</section>
<script>
(function(){
  const range=document.getElementById('notification-radius-slider');
  const output=document.getElementById('notification-radius-value');
  const lat=document.getElementById('notification-origin-lat');
  const lng=document.getElementById('notification-origin-lng');
  const state=document.getElementById('notification-location-state');
  range.addEventListener('input',()=>{output.textContent=range.value+' km';});
  document.getElementById('notification-use-location').addEventListener('click',()=>{
    if(!navigator.geolocation){state.textContent='Localização indisponível neste navegador.';return;}
    state.textContent='Obtendo sua localização...';
    navigator.geolocation.getCurrentPosition(
      (position)=>{
        lat.value=String(position.coords.latitude);
        lng.value=String(position.coords.longitude);
        state.textContent='Localização de referência atualizada. Clique em Salvar distância.';
      },
      ()=>{state.textContent='Não foi possível obter sua localização. Permita o acesso à localização no navegador e tente novamente.';},
      {enableHighAccuracy:true,timeout:15000,maximumAge:0}
    );
  });
})();
</script>
