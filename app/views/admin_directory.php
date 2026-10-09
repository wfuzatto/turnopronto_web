<?php
$section=$section??'users';
$roleLabel=['admin'=>'Administrador','company'=>'Empresa','professional'=>'Profissional'];
$statusLabel=['active'=>'Ativo','inactive'=>'Inativo','blocked'=>'Bloqueado','pending'=>'Pendente','verified'=>'Verificado','rejected'=>'Rejeitado','published'=>'Publicada','filling'=>'Em preenchimento','confirmed'=>'Confirmada','cancelled'=>'Cancelada','draft'=>'Rascunho','completed'=>'Concluído'];
?>
<?php if($section==='users'):?>
<div class="page-head">
  <div><h1>Usuários</h1><p>Todos os usuários da plataforma, inclusive administradores e contas que não são candidatos.</p></div>
  <span class="admin-count-badge"><?=count($items)?> usuário(s)</span>
</div>
<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por nome, e-mail, telefone ou empresa"></div>
    <select data-filter-status>
      <option value="">Todos os tipos</option>
      <option value="admin">Administrador</option>
      <option value="company">Empresa</option>
      <option value="professional">Profissional</option>
    </select>
  </div>
  <div class="table-wrap">
    <table class="tp-table admin-directory-table">
      <thead><tr><th>Usuário</th><th>Tipo</th><th>Vínculo</th><th>Status</th><th>Último acesso</th><th>Cadastro</th><th></th></tr></thead>
      <tbody>
      <?php foreach($items as $item):
        $search=mb_strtolower(trim(($item['name']??'').' '.($item['email']??'').' '.($item['phone']??'').' '.($item['company_name']??'').' '.($item['headline']??'')));
      ?>
        <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($item['role'])?>">
          <td>
            <div class="role-cell">
              <div class="avatar-md <?=!empty($item['avatar_url'])?'has-photo':''?>"><?php if(!empty($item['avatar_url'])):?><img src="<?=e($item['avatar_url'])?>" alt="<?=e($item['name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($item['name']?:'U',0,1)))?><?php endif;?></div>
              <div><strong><?=e($item['name'])?></strong><small><?=e($item['email']?:'Sem e-mail')?> · <?=e($item['phone']?:'Sem telefone')?></small></div>
            </div>
          </td>
          <td><span class="admin-role-pill <?=e($item['role'])?>"><?=e($roleLabel[$item['role']]??ucfirst($item['role']))?></span></td>
          <td>
            <?php if(!empty($item['professional_id'])):?><strong>Profissional #<?=e((string)$item['professional_id'])?></strong><small><?=e($item['headline']?:'Sem apresentação')?></small>
            <?php elseif(!empty($item['company_id'])):?><strong><?=e($item['company_name']?:'Empresa')?></strong><small><?=e($item['member_role']?:'membro')?></small>
            <?php else:?><span class="muted">Usuário sem cadastro de candidato/empresa</span><?php endif;?>
          </td>
          <td><span class="status <?=e($item['status'])?>"><?=e($statusLabel[$item['status']]??ucfirst($item['status']))?></span></td>
          <td><?=!empty($item['last_login_at'])?br_date($item['last_login_at'],'d/m/Y H:i'):'Nunca'?></td>
          <td><?=br_date($item['created_at'],'d/m/Y H:i')?></td>
          <td><a class="btn btn-soft btn-sm" href="<?=e(url('admin/usuarios/'.$item['id']))?>">Abrir usuário</a></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <div class="empty-filter" data-filter-empty hidden>Nenhum usuário encontrado.</div>
</section>

<?php elseif($section==='candidates'):?>
<div class="page-head"><div><h1>Candidatos / profissionais</h1><p>Cadastros profissionais, interesses, candidaturas e turnos realizados.</p></div><span class="admin-count-badge"><?=count($items)?> profissional(is)</span></div>
<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por nome, CPF, função ou cidade"></div>
    <select data-filter-status><option value="">Todos os status</option><option value="pending">Pendente</option><option value="verified">Verificado</option><option value="rejected">Rejeitado</option></select>
  </div>
  <div class="table-wrap"><table class="tp-table admin-directory-table">
    <thead><tr><th>Profissional</th><th>Local</th><th>Interesses</th><th>Candidaturas</th><th>Turnos</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($items as $item): $search=mb_strtolower(trim($item['name'].' '.($item['cpf']??'').' '.($item['headline']??'').' '.($item['city']??'').' '.($item['state']??''))); ?>
      <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($item['status'])?>">
        <td><div class="role-cell"><div class="avatar-md <?=!empty($item['avatar_url'])?'has-photo':''?>"><?php if(!empty($item['avatar_url'])):?><img src="<?=e($item['avatar_url'])?>" alt="<?=e($item['name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($item['name']?:'P',0,1)))?><?php endif;?></div><div><strong><?=e($item['name'])?></strong><small><?=e($item['headline']?:$item['email'])?></small></div></div></td>
        <td><strong><?=e(trim(($item['city']??'').' - '.($item['state']??''),' -'))?></strong><small><?=e($item['phone']?:'')?></small></td>
        <td><strong><?=e((string)$item['following_count'])?></strong></td>
        <td><strong><?=e((string)$item['application_count'])?></strong></td>
        <td><strong><?=e((string)$item['assignment_count'])?></strong></td>
        <td><span class="status <?=e($item['status'])?>"><?=e($statusLabel[$item['status']]??ucfirst($item['status']))?></span></td>
        <td><a class="btn btn-soft btn-sm" href="<?=e(url('admin/verificacao/profissional/'.$item['id']))?>">Abrir cadastro</a></td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <div class="empty-filter" data-filter-empty hidden>Nenhum profissional encontrado.</div>
</section>

<?php elseif($section==='companies'):?>
<div class="page-head"><div><h1>Empresas</h1><p>Empresas cadastradas, responsáveis e volume de vagas.</p></div><span class="admin-count-badge"><?=count($items)?> empresa(s)</span></div>
<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por empresa, CNPJ, responsável ou cidade"></div>
    <select data-filter-status><option value="">Todos os status</option><option value="pending">Pendente</option><option value="verified">Verificada</option><option value="rejected">Rejeitada</option></select>
  </div>
  <div class="table-wrap"><table class="tp-table admin-directory-table">
    <thead><tr><th>Empresa</th><th>Responsável</th><th>Local</th><th>Membros</th><th>Vagas</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($items as $item): $search=mb_strtolower(trim($item['trade_name'].' '.$item['legal_name'].' '.($item['cnpj']??'').' '.($item['owner_name']??'').' '.($item['city']??''))); ?>
      <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($item['status'])?>">
        <td><div class="role-cell"><div class="avatar-md <?=!empty($item['logo_url'])?'has-photo':''?>"><?php if(!empty($item['logo_url'])):?><img src="<?=e($item['logo_url'])?>" alt="<?=e($item['trade_name'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($item['trade_name']?:'E',0,1)))?><?php endif;?></div><div><strong><?=e($item['trade_name'])?></strong><small><?=e($item['legal_name'])?> · <?=e($item['cnpj']?:'CNPJ não informado')?></small></div></div></td>
        <td><strong><?=e($item['owner_name']?:'—')?></strong><small><?=e($item['owner_email']?:'')?></small></td>
        <td><?=e(trim(($item['city']??'').' - '.($item['state']??''),' -'))?></td>
        <td><?=e((string)$item['member_count'])?></td>
        <td><?=e((string)$item['shift_count'])?></td>
        <td><span class="status <?=e($item['status'])?>"><?=e($statusLabel[$item['status']]??ucfirst($item['status']))?></span></td>
        <td><a class="btn btn-soft btn-sm" href="<?=e(url('admin/verificacao/empresa/'.$item['id']))?>">Abrir cadastro</a></td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <div class="empty-filter" data-filter-empty hidden>Nenhuma empresa encontrada.</div>
</section>

<?php elseif($section==='shifts'):?>
<div class="page-head"><div><h1>Vagas</h1><p>Todas as vagas publicadas pelas empresas, independentemente do status.</p></div><span class="admin-count-badge"><?=count($items)?> vaga(s)</span></div>
<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por título, categoria, empresa ou cidade"></div>
    <select data-filter-status><option value="">Todos os status</option><option value="published">Publicada</option><option value="filling">Em preenchimento</option><option value="confirmed">Confirmada</option><option value="cancelled">Cancelada</option><option value="draft">Rascunho</option></select>
  </div>
  <div class="table-wrap"><table class="tp-table admin-directory-table">
    <thead><tr><th>Vaga</th><th>Empresa</th><th>Data</th><th>Valor</th><th>Interesses</th><th>Candidatos</th><th>Escala</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($items as $item): $search=mb_strtolower(trim($item['title'].' '.$item['category_name'].' '.$item['company_name'].' '.$item['city'].' '.$item['state'])); ?>
      <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($item['status'])?>">
        <td><div class="role-cell"><div class="role-thumb <?=!empty($item['image_url'])?'has-photo':''?>"><?php if(!empty($item['image_url'])):?><img src="<?=e($item['image_url'])?>" alt="<?=e($item['title'])?>"><?php else:?><?=e(mb_strtoupper(mb_substr($item['category_name'],0,1)))?><?php endif;?></div><div><strong><?=e($item['title'])?></strong><small><?=e($item['category_name'])?> · <?=e($item['city'].' - '.$item['state'])?></small></div></div></td>
        <td><?=e($item['company_name'])?></td>
        <td><strong><?=br_date($item['starts_at'])?></strong><small><?=date('H:i',strtotime($item['starts_at']))?> – <?=date('H:i',strtotime($item['ends_at']))?></small></td>
        <td><?=money($item['shift_value'])?></td>
        <td><?=e((string)$item['follower_count'])?></td>
        <td><?=e((string)$item['application_count'])?></td>
        <td><?=e((string)$item['assignment_count'])?> / <?=e((string)$item['required_workers'])?></td>
        <td><span class="status <?=e($item['status'])?>"><?=e($statusLabel[$item['status']]??ucfirst($item['status']))?></span></td>
        <td><a class="btn btn-soft btn-sm" target="_blank" rel="noopener" href="<?=e(url('vagas/'.$item['id']))?>">Ver anúncio</a></td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <div class="empty-filter" data-filter-empty hidden>Nenhuma vaga encontrada.</div>
</section>

<?php elseif($section==='locations'):?>
<div class="page-head"><div><h1>Locais</h1><p>Cidades onde existem empresas cadastradas e vagas publicadas.</p></div><span class="admin-count-badge"><?=count($items)?> local(is)</span></div>
<section class="panel" data-filter-table>
  <div class="filter-row"><div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por cidade ou UF"></div></div>
  <div class="table-wrap"><table class="tp-table admin-directory-table">
    <thead><tr><th>Cidade</th><th>UF</th><th>Empresas</th><th>Vagas cadastradas</th><th>Vagas abertas</th></tr></thead>
    <tbody>
    <?php foreach($items as $item): $search=mb_strtolower($item['city'].' '.$item['state']); ?>
      <tr data-filter-row data-search="<?=e($search)?>" data-status="">
        <td><strong><?=e($item['city'])?></strong></td><td><?=e($item['state'])?></td><td><?=e((string)$item['company_count'])?></td><td><?=e((string)$item['shift_count'])?></td><td><?=e((string)$item['open_shift_count'])?></td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
  <div class="empty-filter" data-filter-empty hidden>Nenhum local encontrado.</div>
</section>

<?php elseif($section==='reports'): $s=$report['stats']; ?>
<div class="page-head"><div><h1>Relatórios</h1><p>Resumo operacional da plataforma.</p></div></div>
<div class="kpi-grid five admin-report-kpis">
  <div class="kpi"><div><small>Usuários ativos</small><strong><?=$s['active_users']?></strong></div></div>
  <div class="kpi"><div><small>Empresas</small><strong><?=$s['companies']?></strong></div></div>
  <div class="kpi"><div><small>Profissionais</small><strong><?=$s['professionals']?></strong></div></div>
  <div class="kpi"><div><small>Vagas abertas</small><strong><?=$s['published_shifts']?></strong></div></div>
  <div class="kpi"><div><small>Turnos concluídos</small><strong><?=$s['completed_assignments']?></strong></div></div>
</div>
<div class="admin-report-grid">
  <section class="panel"><div class="panel-head"><div><h2>Usuários por tipo</h2><p>Distribuição atual das contas.</p></div></div>
    <div class="admin-metric-list"><?php foreach($report['roles'] as $row):?><div><span><?=e($roleLabel[$row['role']]??ucfirst($row['role']))?></span><strong><?=e((string)$row['total'])?></strong></div><?php endforeach;?></div>
  </section>
  <section class="panel"><div class="panel-head"><div><h2>Vagas por status</h2><p>Situação das vagas cadastradas.</p></div></div>
    <div class="admin-metric-list"><?php foreach($report['shift_status'] as $row):?><div><span><?=e($statusLabel[$row['status']]??ucfirst($row['status']))?></span><strong><?=e((string)$row['total'])?></strong></div><?php endforeach;?></div>
  </section>
  <section class="panel"><div class="panel-head"><div><h2>Pendências</h2><p>Cadastros que ainda precisam de revisão.</p></div></div>
    <div class="admin-metric-list"><div><span>Empresas pendentes</span><strong><?=$s['pending_companies']?></strong></div><div><span>Profissionais pendentes</span><strong><?=$s['pending_professionals']?></strong></div></div>
  </section>
  <section class="panel"><div class="panel-head"><div><h2>Novos usuários</h2><p>Cadastros nos últimos meses.</p></div></div>
    <div class="admin-metric-list"><?php foreach($report['user_months'] as $row):?><div><span><?=e($row['month'])?></span><strong><?=e((string)$row['total'])?></strong></div><?php endforeach;?></div>
  </section>
</div>
<?php endif;?>
