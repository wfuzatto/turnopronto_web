<?php
$editing=!empty($shift);
$source=array_merge($shift??[],$_POST);
$date=$editing && !empty($source['starts_at']) ? date('Y-m-d',strtotime($source['starts_at'])) : ($source['date']??date('Y-m-d',strtotime('+1 day')));
$start=$editing && !empty($source['starts_at']) ? date('H:i',strtotime($source['starts_at'])) : ($source['start_time']??'18:00');
$end=$editing && !empty($source['ends_at']) ? date('H:i',strtotime($source['ends_at'])) : ($source['end_time']??'02:00');
$value=$source['value']??$source['shift_value']??160;
$mode=$source['acceptance_mode']??'automatic';
$rangeMode=!$editing && ($source['date_mode']??'single')==='range';
$rangeFrom=(string)($source['date_from']??$date);
$rangeTo=(string)($source['date_to']??date('Y-m-d',strtotime($date.' +6 days')));
?>
<div class="shift-form-page">
<div class="page-head">
  <div>
    <h1><?=$editing?'Editar vaga':'Publicar nova vaga'?></h1>
    <p><?=$editing?'Atualize as informações do turno sem perder os profissionais já vinculados.':'Informe tudo que o profissional precisa saber antes de aceitar.'?></p>
  </div>
  <?php if($editing):?><a class="btn btn-soft" href="<?=e(url('empresa/vagas/'.$shift['id']))?>">← Voltar à vaga</a><?php endif;?>
</div>

<form method="post" enctype="multipart/form-data" class="form-layout" data-shift-form><?=csrf_field()?>
<?php if(!$editing):?><input type="hidden" name="batch_token" value="<?=e($batch_token??'')?>"><?php endif;?>
<input type="hidden" name="latitude" value="<?=e((string)($source['latitude']??''))?>" data-shift-latitude>
<input type="hidden" name="longitude" value="<?=e((string)($source['longitude']??''))?>" data-shift-longitude>
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
    <div class="full shift-photo-field">
      <label for="shift-image">Foto da vaga</label>
      <div class="shift-photo-upload">
        <div class="shift-photo-preview <?=!empty($source['image_url'])?'has-image':''?>" data-shift-image-preview>
          <?php if(!empty($source['image_url'])):?><img src="<?=e($source['image_url'])?>" alt="Foto atual da vaga"><?php else:?><span><?=icon('file',22)?><b>Prévia da foto</b><small>A imagem aparecerá nos anúncios desta vaga.</small></span><?php endif;?>
        </div>
        <div class="shift-photo-controls">
          <input id="shift-image" type="file" name="shift_image" accept="image/jpeg,image/png,image/webp" data-shift-image-input>
          <small>JPG, PNG ou WEBP. Máximo de 8 MB. Na edição, envie outra foto para substituir a atual.</small>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="panel form-panel">
  <h2>2. Data, horário e confirmação</h2>
  <?php if(!$editing):?>
  <div class="shift-date-mode" aria-label="Forma de publicação">
    <label class="<?=!$rangeMode?'selected':''?>"><input type="radio" name="date_mode" value="single" <?=!$rangeMode?'checked':''?> data-date-mode> Um único dia</label>
    <label class="<?=$rangeMode?'selected':''?>"><input type="radio" name="date_mode" value="range" <?=$rangeMode?'checked':''?> data-date-mode> Vários dias (em lote)</label>
  </div>
  <?php endif;?>
  <div class="form-grid three shift-date-fields <?=$rangeMode?'is-range':''?>" data-shift-date-grid>
    <div data-single-date <?=$rangeMode?'hidden':''?>>
      <label for="shift-date">Data *</label>
      <input id="shift-date" type="date" name="date" value="<?=e($date)?>" <?=$rangeMode?'disabled':''?> required>
    </div>
    <?php if(!$editing):?>
    <div data-shift-range <?=$rangeMode?'':'hidden'?>>
      <label for="shift-range-from">Data inicial *</label>
      <input id="shift-range-from" type="date" name="date_from" value="<?=e($rangeFrom)?>" data-range-from <?=$rangeMode?'required':'disabled'?>>
    </div>
    <div data-shift-range <?=$rangeMode?'':'hidden'?>>
      <label for="shift-range-to">Data final *</label>
      <input id="shift-range-to" type="date" name="date_to" value="<?=e($rangeTo)?>" data-range-to <?=$rangeMode?'required':'disabled'?>>
    </div>
    <?php endif;?>
    <div>
      <label for="shift-start-time">Entrada *</label>
      <input id="shift-start-time" type="time" name="start_time" value="<?=e($start)?>" required>
    </div>
    <div>
      <label for="shift-end-time">Saída *</label>
      <input id="shift-end-time" type="time" name="end_time" value="<?=e($end)?>" required>
    </div>
  </div>
  <?php if(!$editing):?>
  <p class="shift-range-inline-note" data-range-preview aria-live="polite" <?=$rangeMode?'':'hidden'?>></p>
  <?php endif;?>
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
    <div class="full shift-geocode-note" data-shift-geocode-status>As coordenadas serão calculadas automaticamente pelo endereço para permitir o cálculo de distância até os profissionais.</div>
    <div class="full"><label>Uniforme / Dress code</label><textarea name="dress_code" rows="2"><?=e($source['dress_code']??'Calça preta, camisa branca e sapato social preto.')?></textarea></div>
    <div class="full"><label>Observações</label><textarea name="notes" rows="3" placeholder="Instruções de acesso, refeição, responsável no local..."><?=e($source['notes']??'')?></textarea></div>
  </div>
</section>

<?php if($editing):?>
<div class="notice-card shift-edit-notice">
  <?=icon('bell',20)?>
  <div>
    <strong>Alterações serão comunicadas automaticamente</strong>
    <p>Quem acompanha esta vaga, quem já se candidatou e profissionais confirmados receberão uma notificação com os campos alterados.</p>
  </div>
</div>
<?php endif;?>

<div class="form-actions">
  <a class="btn btn-soft" href="<?=e(url($editing?'empresa/vagas/'.$shift['id']:'empresa/vagas'))?>">Cancelar</a>
  <button class="btn btn-primary" type="submit" data-shift-submit><?=$editing?'Salvar alterações':'Publicar vaga'?></button>
</div>
</form>

</div>

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
