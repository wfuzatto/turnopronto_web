<div class="page-head"><div><h1>Minha conta</h1><p>Dados da empresa e do responsável principal.</p></div><span class="status <?=e(($company['status']??'pending')==='verified'?'confirmed':'filling')?>"><?=e(ucfirst($company['status']??'pending'))?></span></div>
<form method="post" class="account-layout"><?=csrf_field()?>
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
      <div><label>CEP *</label><input name="postal_code" data-mask="cep" inputmode="numeric" value="<?=e($_POST['postal_code']??$company['postal_code']??'')?>" placeholder="00000-000" required></div>
      <div><label>UF *</label><input name="state" maxlength="2" value="<?=e($_POST['state']??$company['state']??'MG')?>" required></div>
      <div class="full"><label>Endereço *</label><input name="address" value="<?=e($_POST['address']??$company['address']??'')?>" required></div>
      <div class="full"><label>Cidade *</label><input name="city" value="<?=e($_POST['city']??$company['city']??'')?>" required></div>
      <div class="full"><label>Link do Google Maps</label><input type="url" name="maps_url" value="<?=e($_POST['maps_url']??$company['maps_url']??'')?>" placeholder="https://maps.app.goo.gl/..." autocomplete="url"><small class="field-note">Cole o link compartilhado da localização principal. Ao clicar no cartão “Sua localização”, o mapa será aberto em uma nova aba.</small></div>
    </div>
  </section>
  <div class="account-save"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
</form>