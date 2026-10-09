<?php
$u=$detail['user'];
$p=$detail['professional'];
$companies=$detail['companies'];
$roleLabel=['admin'=>'Administrador','company'=>'Empresa','professional'=>'Profissional'];
?>
<div class="page-head">
  <div>
    <a class="back-link" href="<?=e(url('admin/usuarios'))?>">← Voltar para usuários</a>
    <h1><?=e($u['name'])?></h1>
    <p>Cadastro do usuário #<?=e((string)$u['id'])?> · <?=e($roleLabel[$u['role']]??ucfirst($u['role']))?></p>
  </div>
  <span class="status <?=e($u['status'])?>"><?=e(ucfirst($u['status']))?></span>
</div>

<div class="admin-user-detail-grid">
  <section class="panel">
    <div class="admin-user-identity">
      <div class="avatar-xl <?=!empty($u['avatar_url'])?'has-photo':''?>"><?php if(!empty($u['avatar_url'])):?><img src="<?=e($u['avatar_url'])?>" alt="<?=e($u['name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($u['name']?:'U',0,1)))?><?php endif;?></div>
      <div><h2><?=e($u['name'])?></h2><p><?=e($u['email']?:'Sem e-mail')?></p><small><?=e($u['phone']?:'Sem telefone cadastrado')?></small></div>
    </div>
    <div class="admin-user-info-grid">
      <div><span>Tipo de conta</span><strong><?=e($roleLabel[$u['role']]??ucfirst($u['role']))?></strong></div>
      <div><span>Status</span><strong><?=e(ucfirst($u['status']))?></strong></div>
      <div><span>WhatsApp verificado</span><strong><?=!empty($u['phone_verified_at'])?'Sim':'Não'?></strong></div>
      <div><span>Último acesso</span><strong><?=!empty($u['last_login_at'])?br_date($u['last_login_at'],'d/m/Y H:i'):'Nunca'?></strong></div>
      <div><span>Criado em</span><strong><?=br_date($u['created_at'],'d/m/Y H:i')?></strong></div>
      <div><span>Atualizado em</span><strong><?=br_date($u['updated_at'],'d/m/Y H:i')?></strong></div>
    </div>
  </section>

  <?php if($p):?>
  <section class="panel">
    <div class="panel-head"><div><h2>Perfil profissional</h2><p>Dados ligados ao cadastro de profissional.</p></div><a class="btn btn-soft btn-sm" href="<?=e(url('admin/verificacao/profissional/'.$p['id']))?>">Abrir cadastro completo</a></div>
    <div class="admin-user-info-grid">
      <div><span>Profissional</span><strong>#<?=e((string)$p['id'])?></strong></div>
      <div><span>Status</span><strong><?=e(ucfirst($p['status']))?></strong></div>
      <div><span>Apresentação</span><strong><?=e($p['headline']?:'—')?></strong></div>
      <div><span>Local</span><strong><?=e(trim(($p['city']??'').' - '.($p['state']??''),' -')?:'—')?></strong></div>
      <div><span>Acompanhando vagas</span><strong><?=e((string)$p['following_count'])?></strong></div>
      <div><span>Candidaturas</span><strong><?=e((string)$p['application_count'])?></strong></div>
      <div><span>Turnos</span><strong><?=e((string)$p['assignment_count'])?></strong></div>
      <div><span>Concluídos</span><strong><?=e((string)$p['completed_shifts'])?></strong></div>
    </div>
  </section>
  <?php endif;?>

  <?php if($companies):?>
  <section class="panel admin-user-company-panel">
    <div class="panel-head"><div><h2>Vínculo empresarial</h2><p>Empresas às quais este usuário está vinculado.</p></div></div>
    <?php foreach($companies as $company):?>
      <div class="admin-linked-company">
        <div class="avatar-md <?=!empty($company['logo_url'])?'has-photo':''?>"><?php if(!empty($company['logo_url'])):?><img src="<?=e($company['logo_url'])?>" alt="<?=e($company['trade_name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($company['trade_name']?:'E',0,1)))?><?php endif;?></div>
        <div><strong><?=e($company['trade_name'])?></strong><span><?=e($company['legal_name'])?></span><small><?=e($company['member_role'])?> · <?=e((string)$company['shift_count'])?> vaga(s)</small></div>
        <a class="btn btn-soft btn-sm" href="<?=e(url('admin/verificacao/empresa/'.$company['id']))?>">Abrir empresa</a>
      </div>
    <?php endforeach;?>
  </section>
  <?php endif;?>

  <?php if(!$p && !$companies):?>
  <section class="panel admin-user-standalone">
    <div class="empty-icon"><?=icon('users',24)?></div>
    <h2>Usuário sem cadastro de candidato ou empresa</h2>
    <p>Esta conta existe na plataforma, mas não possui perfil profissional nem vínculo empresarial. Agora ela pode ser acessada normalmente por este menu.</p>
  </section>
  <?php endif;?>
</div>
