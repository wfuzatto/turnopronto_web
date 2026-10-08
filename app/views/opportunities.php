<div class="page-head">
  <div>
    <h1>Oportunidades</h1>
    <p>Turnos das áreas que você escolheu como interesse.</p>
  </div>
  <?php if(!$onboarding['can_apply']):?>
    <a class="btn btn-primary btn-sm" href="<?=e(url('profissional/completar?step='.$onboarding['next_step']))?>">Completar perfil para se candidatar</a>
  <?php endif;?>
</div>

<div class="opportunities-location-shell" data-opportunity-location>
  <div class="filter-row panel">
    <div class="top-search inline-search"><?=icon('search',17)?><input data-opportunity-search placeholder="Função, empresa ou cidade"></div>
    <select data-opportunity-radius aria-label="Distância máxima">
      <option value="10">Até 10 km</option>
      <option value="25">Até 25 km</option>
      <option value="50">Até 50 km</option>
      <option value="0">Qualquer distância</option>
    </select>
    <select data-opportunity-days aria-label="Período">
      <option value="7">Próximos 7 dias</option>
      <option value="today">Hoje</option>
      <option value="tomorrow">Amanhã</option>
      <option value="0">Todas as datas</option>
    </select>
  </div>

  <div class="location-permission panel">
    <div class="location-permission-icon"><?=icon('map',20)?></div>
    <div>
      <strong data-location-title>Usando sua localização para calcular as distâncias</strong>
      <span data-location-status>O Chrome deve solicitar autorização de localização. A distância será calculada pela rota viária, não em linha reta.</span>
    </div>
    <button class="btn btn-soft btn-sm" type="button" data-location-request>Atualizar localização</button>
    <details class="manual-location" data-manual-location>
      <summary>Localização imprecisa? Informar ponto de partida manualmente</summary>
      <div class="manual-location-row">
        <input type="text" data-manual-location-input autocomplete="street-address" placeholder="Ex.: Rua, número, bairro, cidade - UF">
        <button class="btn btn-soft btn-sm" type="button" data-manual-location-apply>Usar este endereço</button>
      </div>
      <small data-manual-location-status>Use esta opção quando o computador informar apenas uma localização aproximada.</small>
    </details>
  </div>

  <section class="panel">
    <div class="opportunity-list big" data-opportunity-list>
      <?php foreach($opportunities as $s):?>
      <div class="opportunity-row"
           data-opportunity-row
           data-search="<?=e(mb_strtolower(($s['category_name']??'').' '.($s['company_name']??'').' '.($s['city']??'').' '.($s['state']??''),'UTF-8'))?>"
           data-shift-lat="<?=e((string)($s['latitude']??''))?>"
           data-shift-lng="<?=e((string)($s['longitude']??''))?>"
           data-shift-address="<?=e((string)($s['address']??''))?>"
           data-shift-city="<?=e((string)($s['city']??''))?>"
           data-shift-state="<?=e((string)($s['state']??''))?>"
           data-shift-start="<?=e((string)($s['starts_at']??''))?>">
        <div class="venue-thumb"><?=e(mb_substr($s['category_name'],0,1))?></div>
        <div class="opp-role">
          <strong><?=e($s['category_name'])?></strong>
          <span><?=e($s['company_name'])?> · ★ <?=number_format((float)$s['company_rating'],1,',','.')?></span>
          <small><?=icon('map',13)?> <?=e($s['city'].' – '.$s['state'])?></small>
        </div>
        <div class="opp-meta"><?=icon('calendar',16)?><div><strong><?=br_date($s['starts_at'],'d/m')?></strong><small><?=date('D',strtotime($s['starts_at']))?></small></div></div>
        <div class="opp-meta"><?=icon('clock',16)?><div><strong><?=date('H:i',strtotime($s['starts_at']))?> – <?=date('H:i',strtotime($s['ends_at']))?></strong></div></div>
        <div class="opp-value"><strong><?=money($s['shift_value'])?></strong><small>por turno</small></div>
        <div class="opp-distance"><?=icon('map',17)?><div><strong data-opportunity-distance>Calculando…</strong><small><span data-opportunity-distance-kind>pela rota</span> · <?=e($s['city'].' - '.$s['state'])?></small></div></div>
        <div class="opp-actions">
          <a class="btn btn-soft" href="<?=e(url('profissional/vagas/'.$s['id']))?>">Ver detalhes</a>
          <form method="post" action="<?=e(url('profissional/vagas/'.$s['id'].'/aceitar'))?>"><?=csrf_field()?><button class="btn btn-primary"><?=$onboarding['can_apply']?($onboarding['profile_verified']&&($s['acceptance_mode']??'automatic')==='automatic'?'Aceitar':'Candidatar-se'):'Completar perfil'?></button></form>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <div class="mini-empty" data-opportunity-empty hidden>Nenhuma vaga encontrada com os filtros e a distância selecionados.</div>
  </section>
</div>
