<div class="page-head">
  <div><h1>Financeiro</h1><p>Visão operacional dos custos dos turnos e lançamentos registrados.</p></div>
  <div class="date-chip"><?=icon('calendar',18)?> Mês atual</div>
</div>

<div class="kpi-grid three">
  <div class="kpi"><div class="kpi-icon blue"><?=icon('wallet')?></div><div><small>Gasto realizado no mês</small><strong><?=money($data['spent_month'])?></strong><span>turnos já concluídos/lançados</span></div></div>
  <div class="kpi"><div class="kpi-icon green"><?=icon('calendar')?></div><div><small>Comprometido em turnos</small><strong><?=money($data['committed'])?></strong><span>confirmados ou em andamento</span></div></div>
  <div class="kpi"><div class="kpi-icon blue"><?=icon('check')?></div><div><small>Turnos concluídos no mês</small><strong><?=$data['completed']?></strong><span>com check-out registrado</span></div></div>
</div>

<section class="panel">
  <div class="panel-head"><div><h2>Lançamentos</h2><p>Ledger operacional. O gateway real será integrado na fase de pagamentos.</p></div></div>
  <div class="table-wrap">
    <table class="tp-table">
      <thead><tr><th>Data</th><th>Profissional</th><th>Função</th><th>Tipo</th><th>Status</th><th>Valor</th></tr></thead>
      <tbody>
      <?php foreach($data['transactions'] as $t):?>
        <tr>
          <td><?=br_date($t['created_at'],'d/m/Y H:i')?></td>
          <td><strong><?=e($t['professional_name']?:'—')?></strong></td>
          <td><?=e($t['category_name']?:'—')?></td>
          <td><?=e(['shift_cost'=>'Custo do turno','shift_payment'=>'Repasse ao profissional'][$t['kind']]??$t['kind'])?></td>
          <td><span class="status <?=$t['status']==='settled'||$t['status']==='paid'?'confirmed':'filling'?>"><?=e(ucfirst($t['status']))?></span></td>
          <td><strong class="<?=$t['direction']==='debit'?'money-debit':'money-credit'?>"><?=$t['direction']==='debit'?'− ':'+ '?><?=money($t['amount'])?></strong></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php if(!$data['transactions']):?><div class="mini-empty">Ainda não existem lançamentos financeiros.</div><?php endif;?>
</section>

<div class="notice-card"><?=icon('shield',21)?><div><strong>Integração de pagamento ainda em modo de ledger interno</strong><p>Esta tela já usa os lançamentos reais do banco. Split, conciliação, estorno e webhooks entram na fase W4 sem alterar o layout.</p></div></div>
