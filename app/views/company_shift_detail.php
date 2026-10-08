<?php
$statusLabel=['published'=>'Publicada','filling'=>'Em preenchimento','confirmed'=>'Confirmada','cancelled'=>'Cancelada','draft'=>'Rascunho'][$shift['status']]??ucfirst($shift['status']);
$fill=min(100,round(((int)$shift['assigned']/max(1,(int)$shift['required_workers']))*100));
$activeCandidates=array_values(array_filter($candidates,fn($c)=>in_array($c['status'],['applied','invited'],true)));
$followers=$followers??[];
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><a href="<?=e(url('empresa/vagas'))?>">Minhas vagas</a> / #<?=$shift['id']?></div>
    <h1><?=e($shift['category_name'])?> <span class="status <?=e($shift['status'])?>"><?=e($statusLabel)?></span></h1>
    <p><?=e($shift['title'])?> · <?=e($shift['city'].' - '.$shift['state'])?></p>
  </div>
  <div class="head-actions">
    <?php if(!in_array($shift['status'],['cancelled','completed'],true) && strtotime($shift['starts_at'])>time()):?>
      <a class="btn btn-soft" href="<?=e(url('empresa/vagas/'.$shift['id'].'/editar'))?>"><?=icon('file',15)?> Editar vaga</a>
      <form method="post" action="<?=e(url('empresa/vagas/'.$shift['id'].'/cancelar'))?>" data-confirm="Cancelar esta vaga e liberar os profissionais confirmados?">
        <?=csrf_field()?><input type="hidden" name="reason" value="Cancelada pela empresa">
        <button class="btn btn-danger" type="submit">Cancelar vaga</button>
      </form>
    <?php endif;?>
  </div>
</div>

<div class="kpi-grid four shift-kpis">
  <div class="kpi"><div class="kpi-icon blue"><?=icon('calendar')?></div><div><small>Data</small><strong><?=br_date($shift['starts_at'],'d/m')?></strong><span><?=date('H:i',strtotime($shift['starts_at']))?> – <?=date('H:i',strtotime($shift['ends_at']))?></span></div></div>
  <div class="kpi"><div class="kpi-icon green"><?=icon('wallet')?></div><div><small>Valor por profissional</small><strong><?=money($shift['shift_value'])?></strong><span>Total previsto: <?=money($shift['shift_value']*$shift['required_workers'])?></span></div></div>
  <div class="kpi"><div class="kpi-icon blue"><?=icon('users')?></div><div><small>Preenchimento</small><strong><?=$shift['assigned']?> / <?=$shift['required_workers']?></strong><span><?=$fill?>% confirmado</span></div></div>
  <div class="kpi"><div class="kpi-icon green"><?=icon('shield')?></div><div><small>Interesse na vaga</small><strong><?=count($followers)?> acompanhando</strong><span><?=count($activeCandidates)?> candidatura(s) pendente(s)</span></div></div>
</div>

<div class="manage-grid">
  <div class="manage-main">
    <section class="panel">
      <div class="panel-head"><div><h2>Profissionais confirmados</h2><p>Quem já faz parte da escala deste turno.</p></div><div class="fill-summary"><b><?=$shift['assigned']?>/<?=$shift['required_workers']?></b><div class="mini-progress wide"><i style="width:<?=$fill?>%"></i></div></div></div>
      <?php if(!$assigned):?>
        <div class="mini-empty">Nenhum profissional confirmado ainda.</div>
      <?php else:?>
        <div class="candidate-list">
          <?php foreach($assigned as $p):?>
          <article class="candidate-card assigned-card">
            <div class="avatar-md"><?=e(mb_strtoupper(mb_substr($p['name'],0,1)))?></div>
            <div class="candidate-main"><strong><?=e($p['name'])?></strong><span><?=e($p['headline']?:'Profissional TurnoPronto')?></span><small><?=round($p['reliability_score'])?>% confiabilidade · <?=round($p['punctuality_score'])?>% pontualidade · <?=$p['completed_shifts']?> turnos</small></div>
            <div class="candidate-side"><span class="status <?=$p['status']==='completed'?'confirmed':'published'?>"><?=e(ucfirst(str_replace('_',' ',$p['status'])))?></span><strong><?=money($p['agreed_value'])?></strong></div>
          </article>
          <?php endforeach;?>
        </div>
      <?php endif;?>
    </section>

    <section class="panel">
      <div class="panel-head"><div><h2>Candidaturas e convites</h2><p>Use indicadores objetivos de presença e pontualidade para decidir.</p></div><span class="candidate-total"><?=count($activeCandidates)?> pendente(s)</span></div>
      <?php if(!$candidates):?>
        <div class="mini-empty">Ainda não há candidaturas para esta vaga.</div>
      <?php else:?>
        <div class="candidate-list">
        <?php foreach($candidates as $p):?>
          <article class="candidate-card">
            <div class="avatar-md"><?=e(mb_strtoupper(mb_substr($p['name'],0,1)))?></div>
            <div class="candidate-main">
              <strong><?=e($p['name'])?></strong>
              <span><?=e($p['headline']?:'Profissional TurnoPronto')?></span>
              <div class="candidate-metrics"><b><?=$p['reliability_score']?>% confiabilidade</b><span><?=$p['attendance_score']?>% presença</span><span><?=$p['punctuality_score']?>% pontualidade</span><span>★ <?=number_format((float)$p['rating'],1,',','.')?></span></div>
            </div>
            <div class="candidate-side">
              <span class="status <?=e($p['status']==='applied'?'filling':($p['status']==='accepted'?'confirmed':'draft'))?>"><?=e(['applied'=>'Candidatou-se','invited'=>'Convidado','accepted'=>'Aprovado','rejected'=>'Rejeitado'][$p['status']]??$p['status'])?></span>
              <?php if(in_array($p['status'],['applied','invited'],true) && empty($p['assignment_id']) && !in_array($shift['status'],['confirmed','cancelled'],true)):?>
                <div class="candidate-actions">
                  <?php if(($p['professional_status']??'pending')==='verified'):?>
                    <form method="post" action="<?=e(url('empresa/vagas/'.$shift['id'].'/candidaturas/'.$p['id'].'/aprovar'))?>"><?=csrf_field()?><button class="btn btn-primary btn-sm">Aprovar</button></form>
                  <?php else:?>
                    <button class="btn btn-primary btn-sm" type="button" disabled title="O TurnoPronto ainda está verificando a identidade deste profissional">Aguardando verificação</button>
                  <?php endif;?>
                  <form method="post" action="<?=e(url('empresa/vagas/'.$shift['id'].'/candidaturas/'.$p['id'].'/rejeitar'))?>" data-confirm="Rejeitar esta candidatura?"><?=csrf_field()?><button class="btn btn-ghost btn-sm">Rejeitar</button></form>
                </div>
                <?php if(($p['professional_status']??'pending')!=='verified'):?><small class="candidate-verification-note">Identidade em análise pelo TurnoPronto. A empresa pode avaliar o interesse, mas ainda não confirmar o turno.</small><?php endif;?>
              <?php endif;?>
            </div>
          </article>
        <?php endforeach;?>
        </div>
      <?php endif;?>
    </section>

    <section class="panel shift-followers-panel">
      <div class="panel-head">
        <div>
          <h2>Profissionais acompanhando</h2>
          <p>Demonstraram interesse sem assumir o compromisso do turno. Ao acompanhar, autorizaram a empresa a visualizar seus contatos para conversar sobre esta vaga.</p>
        </div>
        <span class="candidate-total"><?=count($followers)?> interessado(s)</span>
      </div>
      <?php if(!$followers):?>
        <div class="mini-empty">Ninguém está acompanhando esta vaga no momento.</div>
      <?php else:?>
        <div class="candidate-list">
          <?php foreach($followers as $p):
            $phoneDigits=preg_replace('/\D+/','',(string)($p['phone']??''));
            if($phoneDigits!=='' && !str_starts_with($phoneDigits,'55')) $phoneDigits='55'.$phoneDigits;
          ?>
          <article class="candidate-card follower-card">
            <div class="avatar-md"><?=e(mb_strtoupper(mb_substr($p['name'],0,1)))?></div>
            <div class="candidate-main">
              <strong><?=e($p['name'])?></strong>
              <span><?=e($p['headline']?:'Profissional TurnoPronto')?></span>
              <div class="candidate-metrics">
                <b><?=$p['reliability_score']?>% confiabilidade</b>
                <span><?=$p['attendance_score']?>% presença</span>
                <span><?=$p['punctuality_score']?>% pontualidade</span>
                <span>★ <?=number_format((float)$p['rating'],1,',','.')?></span>
              </div>
              <div class="follower-contact-data">
                <?php if(!empty($p['phone'])):?><span><strong>Telefone:</strong> <?=e($p['phone'])?></span><?php endif;?>
                <?php if(!empty($p['email'])):?><span><strong>E-mail:</strong> <?=e($p['email'])?></span><?php endif;?>
              </div>
            </div>
            <div class="candidate-side follower-actions">
              <span class="status filling">Acompanhando</span>
              <small>desde <?=br_date($p['followed_at'],'d/m/Y H:i')?></small>
              <?php if($phoneDigits!==''):?><a class="btn btn-primary btn-sm" href="<?=e('https://wa.me/'.$phoneDigits)?>" target="_blank" rel="noopener">WhatsApp</a><?php endif;?>
              <?php if(!empty($p['email'])):?><a class="btn btn-soft btn-sm" href="<?=e('mailto:'.$p['email'])?>">E-mail</a><?php endif;?>
            </div>
          </article>
          <?php endforeach;?>
        </div>
      <?php endif;?>
    </section>
  </div>

  <aside class="manage-side">
    <section class="panel compact">
      <div class="panel-head"><h3>Detalhes do turno</h3></div>
      <dl class="detail-list">
        <div><dt>Local</dt><dd><?=e($shift['address'])?><br><?=e($shift['city'].' - '.$shift['state'])?></dd></div>
        <div><dt>Data</dt><dd><?=br_date($shift['starts_at'])?></dd></div>
        <div><dt>Horário</dt><dd><?=date('H:i',strtotime($shift['starts_at']))?> – <?=date('H:i',strtotime($shift['ends_at']))?></dd></div>
        <div><dt>PIN check-in</dt><dd><span class="pin-badge"><?=e($shift['checkin_pin']?:'—')?></span></dd></div>
      </dl>
    </section>
    <section class="panel compact">
      <div class="panel-head"><h3>Uniforme / dress code</h3></div>
      <p class="detail-copy"><?=nl2br(e($shift['dress_code']?:'Não informado.'))?></p>
    </section>
    <section class="panel compact">
      <div class="panel-head"><h3>Orientações</h3></div>
      <p class="detail-copy"><?=nl2br(e($shift['notes']?:'Nenhuma orientação adicional.'))?></p>
    </section>
  </aside>
</div>
