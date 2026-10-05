<?php
$editing=!empty($shift);
$source=array_merge($shift??[],$_POST);
$date=$editing && !empty($source['starts_at']) ? date('Y-m-d',strtotime($source['starts_at'])) : ($source['date']??date('Y-m-d',strtotime('+1 day')));
$start=$editing && !empty($source['starts_at']) ? date('H:i',strtotime($source['starts_at'])) : ($source['start_time']??'18:00');
$end=$editing && !empty($source['ends_at']) ? date('H:i',strtotime($source['ends_at'])) : ($source['end_time']??'02:00');
$value=$source['value']??$source['shift_value']??160;
$mode=$source['acceptance_mode']??'automatic';
?>
<div class="page-head">
  <div>
    <h1><?=$editing?'Editar vaga':'Publicar nova vaga'?></h1>
    <p><?=$editing?'Atualize as informações do turno sem perder os profissionais já vinculados.':'Informe tudo que o profissional precisa saber antes de aceitar.'?></p>
  </div>
  <?php if($editing):?><a class="btn btn-soft" href="<?=e(url('empresa/vagas/'.$shift['id']))?>">← Voltar à vaga</a><?php endif;?>
</div>

<form method="post" class="form-layout"><?=csrf_field()?>
<section class="panel form-panel">
  <h2>1. Função e quantidade</h2>
  <div class="form-grid">
    <div>
      <label for="shift-category">Categoria *</label>
      <div class="category-select-row">
        <select id="shift-category" name="category_id" data-category-select required>
          <option value="">Selecione</option>
          <?php foreach($categories as $c):?>
            <option value="<?=$c['id']?>" <?=((int)($source['category_id']??0)===(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option>
          <?php endforeach;?>
        </select>
        <button class="category-add-btn" type="button" data-category-modal-open title="Cadastrar nova categoria" aria-label="Cadastrar nova categoria">+</button>
      </div>
      <small class="category-created-notice" data-category-created-notice hidden></small>
    </div>
    <div>
      <label>Título da vaga *</label>
      <input name="title" value="<?=e($source['title']??'')?>" placeholder="Ex.: Garçom - Evento corporativo" required>
    </div>
    <div>
      <label>Quantidade de profissionais *</label>
      <input type="number" min="1" max="100" name="required_workers" value="<?=e($source['required_workers']??1)?>" required>
    </div>
    <div>
      <label>Valor por profissional *</label>
      <div class="money-input"><span>R$</span><input type="number" step="0.01" min="0.01" name="value" value="<?=e($value)?>" required></div>
    </div>
    <div class="full">
      <label>Descrição</label>
      <textarea name="description" rows="3" placeholder="Descreva a atividade e o contexto do turno"><?=e($source['description']??'')?></textarea>
    </div>
  </div>
</section>

<section class="panel form-panel">
  <h2>2. Data, horário e confirmação</h2>
  <div class="form-grid three">
    <div><label>Data *</label><input type="date" name="date" value="<?=e($date)?>" required></div>
    <div><label>Entrada *</label><input type="time" name="start_time" value="<?=e($start)?>" required></div>
    <div><label>Saída *</label><input type="time" name="end_time" value="<?=e($end)?>" required></div>
  </div>
  <div class="acceptance-choice">
    <label class="choice-card <?=$mode==='automatic'?'selected':''?>">
      <input type="radio" name="acceptance_mode" value="automatic" <?=$mode==='automatic'?'checked':''?>>
      <span class="choice-icon"><?=icon('check',20)?></span>
      <span><strong>Aceite automático</strong><small>O primeiro profissional elegível que aceitar já fica confirmado, até preencher a quantidade.</small></span>
    </label>
    <label class="choice-card <?=$mode==='manual'?'selected':''?>">
      <input type="radio" name="acceptance_mode" value="manual" <?=$mode==='manual'?'checked':''?>>
      <span class="choice-icon"><?=icon('users',20)?></span>
      <span><strong>Aprovação da empresa</strong><small>O profissional se candidata e a empresa escolhe quem será confirmado.</small></span>
    </label>
  </div>
</section>

<section class="panel form-panel">
  <h2>3. Local e orientações</h2>
  <div class="form-grid">
    <div class="full"><label>Endereço *</label><input name="address" value="<?=e($source['address']??'Av. das Nações Unidas, 12551')?>" required></div>
    <div><label>Cidade *</label><input name="city" value="<?=e($source['city']??'São Paulo')?>" required></div>
    <div><label>UF *</label><input name="state" value="<?=e($source['state']??'SP')?>" maxlength="2" required></div>
    <div class="full"><label>Uniforme / Dress code</label><textarea name="dress_code" rows="2"><?=e($source['dress_code']??'Calça preta, camisa branca e sapato social preto.')?></textarea></div>
    <div class="full"><label>Observações</label><textarea name="notes" rows="3" placeholder="Instruções de acesso, refeição, responsável no local..."><?=e($source['notes']??'')?></textarea></div>
  </div>
</section>

<div class="form-actions">
  <a class="btn btn-soft" href="<?=e(url($editing?'empresa/vagas/'.$shift['id']:'empresa/vagas'))?>">Cancelar</a>
  <button class="btn btn-primary" type="submit"><?=$editing?'Salvar alterações':'Publicar vaga'?></button>
</div>
</form>

<div class="category-modal" data-category-modal hidden>
  <section class="category-modal-card" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
    <div class="category-modal-head">
      <div>
        <h2 id="category-modal-title">Cadastrar categoria</h2>
        <p>Essa categoria ficará disponível para todas as empresas e profissionais nos próximos cadastros e vagas.</p>
      </div>
      <button type="button" class="category-modal-close" data-category-modal-close aria-label="Fechar">×</button>
    </div>
    <form method="post" action="<?=e(url('empresa/categorias'))?>" data-category-form>
      <?=csrf_field()?>
      <label for="new-category-name">Nome da categoria *</label>
      <input id="new-category-name" name="name" maxlength="120" autocomplete="off" placeholder="Ex.: Cozinheiro, Segurança, Manobrista" required>
      <div class="category-modal-feedback" data-category-feedback role="alert" hidden></div>
      <div class="category-modal-actions">
        <button type="button" class="btn btn-soft" data-category-modal-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" data-category-submit>Cadastrar categoria</button>
      </div>
    </form>
  </section>
</div>
