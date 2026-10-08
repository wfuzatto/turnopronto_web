<div class="page-head">
  <div><h1>Minhas vagas</h1><p>Acompanhe publicação, candidatos e preenchimento dos turnos.</p></div>
  <a class="btn btn-primary" href="<?=e(url('empresa/vagas/nova'))?>">+ Publicar nova vaga</a>
</div>

<section class="panel" data-filter-table>
  <div class="filter-row">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-filter-search placeholder="Buscar por função, título ou local"></div>
    <select data-filter-status>
      <option value="">Todos os status</option>
      <option value="published">Publicada</option>
      <option value="filling">Em preenchimento</option>
      <option value="confirmed">Confirmada</option>
      <option value="cancelled">Cancelada</option>
    </select>
  </div>
  <div class="table-wrap">
    <table class="tp-table">
      <thead><tr><th>Função</th><th>Data</th><th>Horário</th><th>Valor</th><th>Preenchimento</th><th>Interessados</th><th>Candidatos</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach($shifts as $s):
        $statusLabel=['published'=>'Publicada','filling'=>'Em preenchimento','confirmed'=>'Confirmada','cancelled'=>'Cancelada','draft'=>'Rascunho'][$s['status']]??ucfirst($s['status']);
        $search=mb_strtolower($s['category_name'].' '.$s['title'].' '.$s['city'].' '.$s['state']);
      ?>
        <tr data-filter-row data-search="<?=e($search)?>" data-status="<?=e($s['status'])?>">
          <td><div class="role-cell"><div class="role-thumb"><?=e(mb_strtoupper(mb_substr($s['category_name'],0,1)))?></div><div><strong><?=e($s['category_name'])?></strong><small><?=e($s['title'])?> · <?=e($s['city'].' - '.$s['state'])?></small></div></div></td>
          <td><strong><?=br_date($s['starts_at'])?></strong><small><?=date('D',strtotime($s['starts_at']))?></small></td>
          <td><strong><?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></strong></td>
          <td><strong><?=money($s['shift_value'])?></strong><small>por profissional</small></td>
          <td><div class="fill-cell"><strong><?=$s['assigned']?> / <?=$s['required_workers']?></strong><div class="mini-progress"><i style="width:<?=min(100,round(($s['assigned']/max(1,$s['required_workers']))*100))?>%"></i></div></div></td>
          <td><span class="candidate-count follower-count"><?=e((string)($s['followers']??0))?></span></td>
          <td><span class="candidate-count"><?=$s['candidates']?></span></td>
          <td><span class="status <?=e($s['status'])?>"><?=e($statusLabel)?></span></td>
          <td><a class="btn btn-soft btn-sm" href="<?=e(url('empresa/vagas/'.$s['id']))?>">Gerenciar</a></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <div class="empty-filter" data-filter-empty hidden>Nenhuma vaga encontrada com esses filtros.</div>
</section>
