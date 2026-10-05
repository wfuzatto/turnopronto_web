<?php
$items=$notifications??[];
$unread=count(array_filter($items,fn($n)=>empty($n['read_at'])));
$iconFor=function(string $type): string {
    return match($type){
        'matching_shift'=>'briefcase',
        'invitation','application'=>'users',
        'application_approved'=>'check',
        'application_rejected'=>'file',
        default=>'bell',
    };
};
?>
<div class="notification-center">
  <div class="page-head">
    <div><h1>Notificações</h1><p>Acompanhe vagas do seu interesse, convites e atualizações importantes da sua conta.</p></div>
    <?php if($unread>0):?>
      <form method="post" action="<?=e(url('notificacoes/ler-todas'))?>"><?=csrf_field()?><input type="hidden" name="back" value="notificacoes"><button class="btn btn-soft" type="submit">Marcar todas como lidas</button></form>
    <?php endif;?>
  </div>

  <section class="panel notification-center-panel">
    <?php if(!$items):?>
      <div class="notification-center-empty"><?=icon('bell',28)?><strong>Nenhuma notificação por enquanto</strong><p>Quando surgir uma vaga do seu interesse, um convite ou outra atualização importante, ela aparecerá aqui.</p></div>
    <?php endif;?>
    <?php foreach($items as $n):?>
      <a class="notification-center-row <?=empty($n['read_at'])?'unread':''?>" href="<?=e(url('notificacoes/'.$n['id'].'/abrir'))?>">
        <span class="notification-center-icon"><?=icon($iconFor((string)$n['type']),20)?></span>
        <span class="notification-center-copy">
          <strong><?=e($n['title'])?></strong>
          <span><?=e($n['body'])?></span>
          <small><?=date('d/m/Y H:i',strtotime($n['created_at']))?></small>
        </span>
        <?php if(empty($n['read_at'])):?><span class="notification-new">Nova</span><?php endif;?>
      </a>
    <?php endforeach;?>
  </section>
</div>
