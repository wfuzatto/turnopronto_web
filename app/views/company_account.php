<div class="page-head"><div><h1>Minha conta</h1><p>Dados da empresa e do responsável principal.</p></div><span class="status <?=e(($company['status']??'pending')==='verified'?'confirmed':'filling')?>"><?=e(ucfirst($company['status']??'pending'))?></span></div>
<form method="post" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Responsável pela conta</h2><p>Informações usadas no acesso e contato operacional.</p></div></div>
    <div class="form-grid">
      <div><label>Nome *</label><input name="name" value="<?=e($_POST['name']??$user['name'])?>" required></div>
      <div><label>Telefone</label><input name="phone" value="<?=e($_POST['phone']??$user['phone']??'')?>"></div>
      <div class="full"><label>E-mail</label><input value="<?=e($user['email'])?>" disabled><small class="field-note">Alteração de e-mail será liberada junto com a verificação por e-mail.</small></div>
    </div>
  </section>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Dados empresariais</h2><p>Esses dados identificam quem está publicando os turnos.</p></div></div>
    <div class="form-grid">
      <div><label>Razão social *</label><input name="legal_name" value="<?=e($_POST['legal_name']??$company['legal_name']??'')?>" required></div>
      <div><label>Nome fantasia *</label><input name="trade_name" value="<?=e($_POST['trade_name']??$company['trade_name']??'')?>" required></div>
      <div><label>CNPJ *</label><input name="cnpj" value="<?=e($_POST['cnpj']??$company['cnpj']??'')?>" required></div>
      <div><label>UF *</label><input name="state" maxlength="2" value="<?=e($_POST['state']??$company['state']??'MG')?>" required></div>
      <div class="full"><label>Endereço *</label><input name="address" value="<?=e($_POST['address']??$company['address']??'')?>" required></div>
      <div class="full"><label>Cidade *</label><input name="city" value="<?=e($_POST['city']??$company['city']??'')?>" required></div>
    </div>
  </section>
  <div class="account-save"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
</form>