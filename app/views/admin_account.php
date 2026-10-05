<div class="page-head">
  <div><h1>Minha conta</h1><p>Dados da conta administrativa usada para gerenciar o TurnoPronto.</p></div>
</div>

<form method="post" class="account-layout"><?=csrf_field()?>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Administrador</h2><p>Ao clicar no seu nome no canto superior direito, esta página será aberta.</p></div></div>
    <div class="form-grid">
      <div><label>Nome *</label><input name="name" value="<?=e($_POST['name']??$user['name']??'')?>" required></div>
      <div><label>E-mail</label><input value="<?=e($user['email']??'')?>" disabled></div>
    </div>
  </section>
  <section class="panel account-card-main">
    <div class="panel-head"><div><h2>Alterar senha</h2><p>Preencha somente se quiser trocar a senha administrativa.</p></div></div>
    <div class="form-grid">
      <div class="full"><label>Senha atual</label><input type="password" name="current_password" autocomplete="current-password"></div>
      <div><label>Nova senha</label><input type="password" name="new_password" minlength="8" autocomplete="new-password"></div>
      <div><label>Confirmar nova senha</label><input type="password" name="new_password_confirm" minlength="8" autocomplete="new-password"></div>
    </div>
  </section>
  <div class="account-save"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
</form>
