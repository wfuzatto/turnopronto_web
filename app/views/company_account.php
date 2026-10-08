<div class="page-head"><div><h1>Minha conta</h1><p>Dados da empresa e do responsável principal.</p></div><span class="status <?=e(($company['status']??'pending')==='verified'?'confirmed':'filling')?>"><?=e(ucfirst($company['status']??'pending'))?></span></div>
<form method="post" enctype="multipart/form-data" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Foto / logotipo da empresa</h2><p>Esta imagem identifica a empresa na plataforma e pode aparecer nas vagas quando não houver uma foto específica do anúncio.</p></div></div>
    <div class="profile-image-upload">
      <div class="profile-image-preview company <?=!empty($company['logo_url'])?'has-image':''?>" data-profile-image-preview>
        <?php if(!empty($company['logo_url'])):?><img src="<?=e($company['logo_url'])?>" alt="<?=e($company['trade_name']??'Empresa')?>"><?php else:?><span><?=e(mb_strtoupper(mb_substr($company['trade_name']??'E',0,1)))?></span><?php endif;?>
      </div>
      <div class="profile-image-controls">
        <label for="company-profile-image">Escolher nova imagem</label>
        <input id="company-profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-profile-image-input>
        <small>JPG, PNG ou WEBP, até 8 MB. Ao salvar, a nova imagem substitui a atual.</small>
      </div>
    </div>
  </section>

  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Responsável pela conta</h2><p>Informações usadas no acesso e contato operacional.</p></div></div>
    <div class="form-grid">
      <div><label>Nome *</label><input name="name" value="<?=e($_POST['name']??$user['name'])?>" required></div>
      <div><label>WhatsApp *</label><input name="phone" value="<?=e($_POST['phone']??$user['phone']??'')?>" required><small class="field-note">Se o número for alterado, ele precisará ser validado novamente.</small></div>
      <div class="full"><label>E-mail do responsável / usuário</label><input value="<?=e($user['email'])?>" disabled><small class="field-note">Usado para acesso, segurança e comunicações destinadas ao responsável da conta.</small></div>
    </div>
  </section>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Dados empresariais</h2><p>Esses dados identificam quem está publicando os turnos.</p></div></div>
    <div class="form-grid">
      <div><label>Razão social *</label><input name="legal_name" value="<?=e($_POST['legal_name']??$company['legal_name']??'')?>" required></div>
      <div><label>Nome fantasia *</label><input name="trade_name" value="<?=e($_POST['trade_name']??$company['trade_name']??'')?>" required></div>
      <div><label>CNPJ *</label><input name="cnpj" value="<?=e($_POST['cnpj']??$company['cnpj']??'')?>" required></div>
      <div><label>E-mail da empresa *</label><input type="email" name="company_email" value="<?=e($_POST['company_email']??$company['company_email']??'')?>" required><small class="field-note">Usado para formalização de vagas, pagamentos, comprovantes e comunicação institucional.</small></div>
      <div><label>UF *</label><input name="state" maxlength="2" value="<?=e($_POST['state']??$company['state']??'MG')?>" required></div>
      <div class="full"><label>Endereço *</label><input name="address" value="<?=e($_POST['address']??$company['address']??'')?>" required></div>
      <div class="full"><label>Cidade *</label><input name="city" value="<?=e($_POST['city']??$company['city']??'')?>" required></div>
      <div class="full"><label>Link do Google Maps</label><input type="url" name="maps_url" value="<?=e($_POST['maps_url']??$company['maps_url']??'')?>" placeholder="https://maps.app.goo.gl/..." autocomplete="url"><small class="field-note">Cole o link compartilhado da localização principal. Ao clicar no cartão “Sua localização”, o mapa será aberto em uma nova aba.</small></div>
    </div>
  </section>
  <div class="account-save"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
</form>