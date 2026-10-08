<div class="page-head">
  <div><h1>Profissionais</h1><p>Encontre profissionais verificados e acompanhe indicadores reais de confiabilidade.</p></div>
</div>

<?php if(!$openShifts):?>
<div class="notice-card"><?=icon('briefcase',21)?><div><strong>Publique uma vaga antes de convidar profissionais</strong><p>O convite sempre precisa estar ligado a um turno específico.</p></div><a class="btn btn-primary btn-sm" href="<?=e(url('empresa/vagas/nova'))?>">Publicar vaga</a></div>
<?php endif;?>

<div class="professional-grid">
<?php foreach($professionals as $p):?>
<article class="panel pro-card" id="profissional-<?=$p['id']?>">
  <div class="avatar-xl"><?=e(mb_strtoupper(mb_substr($p['name'],0,1)))?></div>
  <div class="grow">
    <h3><?=e($p['name'])?></h3>
    <p><?=e($p['headline']?:'Profissional TurnoPronto')?></p>
    <div class="stars"><?=!empty($p['company_feedback_count'])?('★ '.number_format((float)$p['rating'],1,',','.')):'Sem avaliação ainda'?> <span>· <?=$p['completed_shifts']?> turnos</span></div>
  </div>
  <div class="score-ring"><b><?=!empty($p['company_feedback_count'])?round($p['reliability_score']).'%':'—'?></b><span>confiabilidade</span></div>
  <div class="pro-card-metrics"><span><b><?=!empty($p['company_feedback_count'])?round($p['attendance_score']).'%':'—'?></b> presença</span><span><b><?=!empty($p['company_feedback_count'])?round($p['punctuality_score']).'%':'—'?></b> pontualidade</span></div>

  <?php if($openShifts):?>
  <form class="invite-form" method="post" action="<?=e(url('empresa/profissionais/'.$p['id'].'/convidar'))?>">
    <?=csrf_field()?>
    <select name="shift_id" required>
      <option value="">Escolha a vaga para convidar</option>
      <?php foreach($openShifts as $s):?><option value="<?=$s['id']?>"><?=e($s['category_name'].' · '.br_date($s['starts_at'],'d/m H:i'))?></option><?php endforeach;?>
    </select>
    <button class="btn btn-primary btn-block">Convidar para a vaga</button>
  </form>
  <?php endif;?>
</article>
<?php endforeach;?>
</div>
