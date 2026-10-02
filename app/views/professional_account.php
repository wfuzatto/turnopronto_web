<div class="page-head"><div><h1>Meu perfil</h1><p>Mantenha seus dados profissionais atualizados para receber oportunidades compatíveis.</p></div><span class="status <?=e(($profile['status']??'pending')==='verified'?'confirmed':'filling')?>"><?=e(ucfirst($profile['status']??'pending'))?></span></div>
<form method="post" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Dados pessoais e profissionais</h2><p>Somente dados necessários para operação e contato.</p></div></div>
    <div class="form-grid">
      <div><label>Nome *</label><input name="name" value="<?=e($_POST['name']??$profile['name']??'')?>" required></div>
      <div><label>Telefone</label><input name="phone" value="<?=e($_POST['phone']??$user['phone']??'')?>"></div>
      <div class="full"><label>Atividade / apresentação *</label><input name="headline" value="<?=e($_POST['headline']??$profile['headline']??'')?>" placeholder="Ex.: Garçom • Recepcionista" required></div>
      <div><label>Cidade *</label><input name="city" value="<?=e($_POST['city']??$profile['city']??'')?>" required></div>
      <div><label>UF *</label><input name="state" maxlength="2" value="<?=e($_POST['state']??$profile['state']??'MG')?>" required></div>
      <div class="full"><label>Chave PIX</label><input name="pix_key" value="<?=e($_POST['pix_key']??$profile['pix_key']??'')?>" placeholder="CPF, e-mail, telefone ou chave aleatória"><small class="field-note">O PIX será usado futuramente pelo módulo de repasses.</small></div>
      <div class="full"><label>Sobre você</label><textarea name="bio" rows="4" maxlength="2000" placeholder="Experiência, disponibilidade, diferenciais..."><?=e($_POST['bio']??$profile['bio']??'')?></textarea></div>
    </div>
  </section>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Funções de interesse</h2><p>Escolha as atividades para as quais deseja receber oportunidades.</p></div></div>
    <div class="category-profile-grid"><?php foreach($categories as $cat):?><label class="category-profile"><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((int)$cat['id'],array_map('intval',(array)($_POST['categories']??$selected)),true)?'checked':''?>><span><?=icon('briefcase',18)?><b><?=e($cat['name'])?></b></span></label><?php endforeach;?></div>
  </section>
  <div class="account-save"><a class="btn btn-soft" href="<?=e(url('profissional/documentos'))?>">Meus documentos</a><button class="btn btn-primary" type="submit">Salvar perfil</button></div>
</form>