<?php
$appearance=$appearance??Data::platformAppearance();
$webSkin=(string)($appearance['web_skin']??'modern');
$appSkin=(string)($appearance['app_skin']??'modern');
?>
<div class="admin-settings-page">
  <div class="admin-settings-head">
    <h1>Configurações da plataforma</h1>
    <p>Personalize a aparência e o comportamento da sua plataforma TurnoPronto.</p>
  </div>

  <div class="admin-settings-tabs">
    <span>Geral</span>
    <span class="active">Aparência</span>
    <span>Conteúdo</span>
    <span>Notificações</span>
    <span>Integrações</span>
    <span>Usuários</span>
    <span>Avançado</span>
  </div>

  <form method="post" class="admin-skins-panel">
    <?=csrf_field()?>
    <div class="admin-skins-title-row">
      <div class="admin-skins-title-icon">◉</div>
      <div>
        <h2>Skins da primeira página</h2>
        <p>Escolha e personalize o visual da página inicial pública, onde os candidatos visualizam as vagas.</p>
      </div>
      <button type="button" class="btn btn-soft admin-help-btn">ⓘ Saiba mais sobre skins</button>
    </div>

    <div class="admin-skins-info">
      <span>ⓘ</span>
      <p>Você pode manter diferentes skins para Web e App, testar variações (A/B) e alternar facilmente entre os modelos.</p>
    </div>

    <div class="admin-skin-grid">
      <article class="admin-skin-card <?=$webSkin==='classic'?'selected':''?>">
        <div class="admin-skin-badges"><span class="skin-badge current">ATUAL</span></div>
        <h3>Clássica (atual)</h3>
        <p>Layout atual da TurnoPronto. Simples, direto e focado nas vagas.</p>
        <div class="admin-skin-preview classic-preview">
          <div class="preview-top"><span class="preview-logo">Turno<span>Pronto</span></span><div><b>Entrar</b><b>Sou empresa</b></div></div>
          <div class="preview-classic-body">
            <div><strong>Veja as vagas primeiro.<br>Cadastre-se só quando quiser se candidatar.</strong><small>Explore horários, valores, empresas e locais.</small></div>
            <div class="preview-steps"><i>1 Informações básicas</i><i>2 Contato</i><i>3 Pagamento</i></div>
          </div>
          <div class="preview-classic-job"><span>A</span><div><b>Aux. Cozinha</b><strong>Confeitaria</strong><small>Vale da Mantiqueira</small></div><em>R$ 160,00</em><button>Tenho interesse</button></div>
        </div>
        <div class="admin-skin-state <?=$webSkin==='classic'?'on':''?>"><?=icon('check',16)?> <?=$webSkin==='classic'?'Em uso':'Disponível para ativação'?></div>
        <div class="admin-skin-platforms">
          <label><span>▣ Web</span><input type="radio" name="web_skin" value="classic" <?=$webSkin==='classic'?'checked':''?>><i></i><b><?=$webSkin==='classic'?'Ativada':'Desativada'?></b></label>
          <label><span>▯ App</span><input type="radio" name="app_skin" value="classic" <?=$appSkin==='classic'?'checked':''?>><i></i><b><?=$appSkin==='classic'?'Ativada':'Desativada'?></b></label>
        </div>
        <div class="admin-skin-actions"><a class="btn btn-soft" href="<?=e(url('vagas?preview_skin=classic'))?>" target="_blank">◉ Visualizar</a><button class="btn <?=$webSkin==='classic'?'btn-disabled':'btn-primary'?>" type="submit" name="activate" value="classic" <?=$webSkin==='classic'?'disabled':''?>><?=$webSkin==='classic'?'✓ Ativa no momento':'⚡ Ativar esta skin'?></button></div>
      </article>

      <article class="admin-skin-card <?=$webSkin==='modern'?'selected':''?>">
        <div class="admin-skin-badges"><span class="skin-badge new">NOVA</span></div>
        <h3>Moderna Extra Jobs</h3>
        <p>Visual moderno e dinâmico, com destaque para vagas e navegação otimizada.</p>
        <div class="admin-skin-preview modern-preview">
          <div class="modern-preview-desktop">
            <div class="mp-head"><b>TurnoPronto</b><span>Entrar</span><span>Sou empresa</span></div>
            <div class="mp-hero"><div><strong>Vagas perto de você</strong><small>Encontre oportunidades de trabalho extra de forma rápida e prática.</small><i>Qual vaga você procura? 🔍</i><em>Cozinha &nbsp; Eventos &nbsp; Limpeza &nbsp; Recepção</em></div><div class="mp-photo">👩🏽‍🍳</div></div>
          </div>
          <div class="modern-preview-phone"><b>TurnoPronto</b><small>Vagas perto de você</small><i>Confeitaria&nbsp;&nbsp; R$ 160,00</i><i>Garçom&nbsp;&nbsp; R$ 200,00</i><i>Recepção&nbsp;&nbsp; R$ 180,00</i></div>
        </div>
        <div class="admin-skin-state <?=$webSkin==='modern'?'on':''?>"><?=icon('check',16)?> <?=$webSkin==='modern'?'Em uso':'Disponível para ativação'?></div>
        <div class="admin-skin-platforms">
          <label><span>▣ Web</span><input type="radio" name="web_skin" value="modern" <?=$webSkin==='modern'?'checked':''?>><i></i><b><?=$webSkin==='modern'?'Ativada':'Desativada'?></b></label>
          <label><span>▯ App</span><input type="radio" name="app_skin" value="modern" <?=$appSkin==='modern'?'checked':''?>><i></i><b><?=$appSkin==='modern'?'Ativada':'Desativada'?></b></label>
        </div>
        <div class="admin-skin-actions"><a class="btn btn-soft" href="<?=e(url('vagas?preview_skin=modern'))?>" target="_blank">◉ Visualizar</a><button class="btn <?=$webSkin==='modern'?'btn-disabled':'btn-primary'?>" type="submit" name="activate" value="modern" <?=$webSkin==='modern'?'disabled':''?>><?=$webSkin==='modern'?'✓ Ativa no momento':'⚡ Ativar esta skin'?></button></div>
      </article>

      <article class="admin-skin-card <?=$webSkin==='minimal'?'selected':''?>">
        <div class="admin-skin-badges"></div>
        <h3>Minimalista</h3>
        <p>Layout limpo e elegante, com foco total nas vagas.</p>
        <div class="admin-skin-preview minimal-preview">
          <div class="minimal-preview-copy"><b>TurnoPronto</b><strong>Oportunidades<br>que cabem na sua rotina</strong><small>Trabalhos temporários e extras perto de você.</small><i>Qual vaga você procura? 🔍</i><em>⌘ Cozinha &nbsp; Eventos &nbsp; Limpeza &nbsp; Atendimento</em></div><div class="minimal-preview-photo">👩🏻‍🍳</div>
        </div>
        <div class="admin-skin-state <?=$webSkin==='minimal'?'on':''?>"><?=icon('check',16)?> <?=$webSkin==='minimal'?'Em uso':'Disponível para ativação'?></div>
        <div class="admin-skin-platforms">
          <label><span>▣ Web</span><input type="radio" name="web_skin" value="minimal" <?=$webSkin==='minimal'?'checked':''?>><i></i><b><?=$webSkin==='minimal'?'Ativada':'Desativada'?></b></label>
          <label><span>▯ App</span><input type="radio" name="app_skin" value="minimal" <?=$appSkin==='minimal'?'checked':''?>><i></i><b><?=$appSkin==='minimal'?'Ativada':'Desativada'?></b></label>
        </div>
        <div class="admin-skin-actions"><a class="btn btn-soft" href="<?=e(url('vagas?preview_skin=minimal'))?>" target="_blank">◉ Visualizar</a><button class="btn <?=$webSkin==='minimal'?'btn-disabled':'btn-primary'?>" type="submit" name="activate" value="minimal" <?=$webSkin==='minimal'?'disabled':''?>><?=$webSkin==='minimal'?'✓ Ativa no momento':'⚡ Ativar esta skin'?></button></div>
      </article>
    </div>

    <div class="admin-skin-advanced">
      <h3>⚙ Opções avançadas</h3>
      <div class="admin-advanced-grid">
        <label><input type="checkbox" name="ab_test" value="1" <?=!empty($appearance['ab_test'])?'checked':''?>><i></i><span><strong>Teste A/B de skins</strong><small>Permite testar duas skins com públicos diferentes para comparar desempenho.</small></span></label>
        <label><input type="checkbox" name="seasonal_campaign" value="1" <?=!empty($appearance['seasonal_campaign'])?'checked':''?>><i></i><span><strong>Campanha sazonal</strong><small>Defina um período para ativar automaticamente uma skin específica.</small></span></label>
        <label><input type="checkbox" name="different_per_platform" value="1" <?=!empty($appearance['different_per_platform'])?'checked':''?>><i></i><span><strong>Skin diferente por plataforma</strong><small>Permite usar skins distintas no site (Web) e no aplicativo (App).</small></span></label>
      </div>
    </div>

    <div class="admin-settings-save"><button class="btn btn-primary" type="submit">Salvar configurações</button></div>
  </form>
</div>