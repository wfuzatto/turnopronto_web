<?php
$pending=array_values(array_filter($reviews,fn($r)=>empty($r['review_id'])));
$done=array_values(array_filter($reviews,fn($r)=>!empty($r['review_id'])));
?>
<div class="page-head">
  <div><h1>Avaliações</h1><p>Avalie turnos concluídos sem misturar opinião subjetiva com presença e pontualidade verificadas.</p></div>
  <div class="date-chip"><?=icon('star',18)?> <?=count($pending)?> pendente(s)</div>
</div>

<?php if(!$reviews):?>
<section class="panel mini-empty">Quando houver turnos concluídos, as avaliações aparecerão aqui.</section>
<?php endif;?>

<div class="review-grid">
<?php foreach($pending as $r):?>
<section class="panel review-card">
  <div class="review-person">
    <div class="avatar-md"><?=e(mb_strtoupper(mb_substr($r['professional_name'],0,1)))?></div>
    <div><strong><?=e($r['professional_name'])?></strong><span><?=e($r['category_name'])?> · <?=br_date($r['starts_at'],'d/m/Y')?></span></div>
    <span class="score-badge compact"><?=round($r['reliability_score'])?>% confiabilidade</span>
  </div>
  <div class="objective-strip">
    <span><b><?=round($r['attendance_score'])?>%</b> presença</span>
    <span><b><?=round($r['punctuality_score'])?>%</b> pontualidade</span>
    <span><b>★ <?=number_format((float)$r['professional_rating'],1,',','.')?></b> avaliação</span>
  </div>
  <form method="post" action="<?=e(url('empresa/avaliacoes/'.$r['assignment_id']))?>" class="review-form">
    <?=csrf_field()?>
    <div class="rating-grid">
      <label>Qualidade geral<select name="rating" required><option value="">Selecione</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?> / 5</option><?php endfor;?></select></label>
      <label>Comparecimento<select name="attendance_rating" required><option value="">Selecione</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?> / 5</option><?php endfor;?></select></label>
      <label>Pontualidade<select name="punctuality_rating" required><option value="">Selecione</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?> / 5</option><?php endfor;?></select></label>
    </div>
    <label>Comentário opcional<textarea name="comment" rows="3" maxlength="1500" placeholder="Descreva a experiência de forma objetiva e respeitosa."></textarea></label>
    <button class="btn btn-primary" type="submit">Registrar avaliação</button>
  </form>
</section>
<?php endforeach;?>
</div>

<?php if($done):?>
<section class="panel">
  <div class="panel-head"><div><h2>Avaliações registradas</h2><p>Histórico recente da empresa.</p></div></div>
  <div class="table-wrap"><table class="tp-table"><thead><tr><th>Profissional</th><th>Função</th><th>Data</th><th>Nota</th><th>Comentário</th></tr></thead><tbody>
  <?php foreach($done as $r):?><tr>
    <td><strong><?=e($r['professional_name'])?></strong></td>
    <td><?=e($r['category_name'])?></td>
    <td><?=br_date($r['review_created_at'],'d/m/Y')?></td>
    <td><strong>★ <?=$r['review_rating']?> / 5</strong></td>
    <td><?=e($r['review_comment']?:'—')?></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
</section>
<?php endif;?>
