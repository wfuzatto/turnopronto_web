<div class="page-head">
  <div><h1>Escalas</h1><p>Acompanhe todos os profissionais confirmados, check-ins e conclusão dos turnos.</p></div>
  <a class="btn btn-primary" href="<?=e(url('empresa/vagas/nova'))?>">+ Nova vaga</a>
</div>

<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar profissional, função ou local"></div>
    <select data-filter-status>
      <option value="">Todos os status</option>
      <option value="confirmed">Confirmado</option>
      <option value="checked_in">Em andamento</option>
      <option value="completed">Concluído</option>
    </select>
  </div>
  <div class="table-wrap">
    <table class="tp-table schedule-table">
      <thead><tr><th>Profissional</th><th>Função</th><th>Data</th><th>Horário</th><th>Local</th><th>Valor</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach($assignments as $a):
        $search=mb_strtolower($a['professional_name'].' '.$a['category_name'].' '.$a['title'].' '.$a['city']);
        $statusLabel=['confirmed'=>'Confirmado','checked_in'=>'Em andamento','completed'=>'Concluído'][$a['status']]??$a['status'];
      ?>
      <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($a['status'])?>">
        <td><div class="role-cell"><div class="avatar-sm"><?=e(mb_strtoupper(mb_substr($a['professional_name'],0,1)))?></div><div><strong><?=e($a['professional_name'])?></strong><small><?=round($a['reliability_score'])?>% confiabilidade</small></div></div></td>
        <td><strong><?=e($a['category_name'])?></strong><small><?=e($a['title'])?></small></td>
        <td><strong><?=br_date($a['starts_at'])?></strong></td>
        <td><?=date('H:i',strtotime($a['starts_at']))?> – <?=date('H:i',strtotime($a['ends_at']))?></td>
        <td><?=e($a['city'].' - '.$a['state'])?></td>
        <td><strong><?=money($a['agreed_value'])?></strong></td>
        <td><span class="status <?=$a['status']==='checked_in'?'filling':($a['status']==='completed'?'confirmed':'published')?>"><?=e($statusLabel)?></span></td>
        <td><a class="btn btn-soft btn-sm" href="<?=e(url('empresa/vagas/'.$a['shift_id']))?>">Ver vaga</a></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php if(!$assignments):?><div class="mini-empty">Nenhum profissional escalado ainda.</div><?php endif;?>
  <div class="empty-filter" data-filter-empty hidden>Nenhum item encontrado com esses filtros.</div>
</section>
