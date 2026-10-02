<?php $error=flash('error'); $success=flash('success'); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar • TurnoPronto</title><link rel="stylesheet" href="<?=e(asset('css/app.css'))?>"></head><body class="login-page">
<div class="login-shell"><section class="login-brand"><img src="<?=e(asset('img/logo.svg'))?>" alt="TurnoPronto"><h1>O extra que sua equipe precisa.<br><span>No momento certo.</span></h1><p>Empresas encontram profissionais para turnos pontuais. Profissionais transformam tempo livre em renda extra.</p><div class="login-feature"><b>✓</b> Reputação baseada em presença e pontualidade</div><div class="login-feature"><b>✓</b> Turnos, check-in e ganhos em um só lugar</div></section>
<section class="login-card"><h2>Bem-vindo</h2><p>Entre para acessar sua conta.</p><?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post" action="<?=e(url('login'))?>"><?=csrf_field()?><label>E-mail</label><input name="email" type="email" value="juliana@turnopronto.local" required><label>Senha</label><input name="password" type="password" value="" required><button class="btn btn-primary btn-block" type="submit">Entrar</button></form><div class="demo-login"><strong>Acessos de demonstração</strong><button data-fill-login="empresa@turnopronto.local">Empresa</button><button data-fill-login="juliana@turnopronto.local">Profissional</button><button data-fill-login="admin@turnopronto.local">Admin</button><small>Use a senha definida no instalador.</small></div><div class="signup-link">Ainda não tem conta? <a href="<?=e(url('cadastro'))?>">Criar conta</a></div>
<div class="app-downloads">
  <div class="app-downloads-title">Leve o TurnoPronto com você</div>
  <div class="store-badges">
    <a class="store-badge android" href="https://github.com/wfuzatto/turnopronto_app/releases/download/test-latest/TurnoPronto.apk" aria-label="Baixar aplicativo TurnoPronto para Android">
      <span class="store-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="24" height="24" role="img"><path fill="currentColor" d="M17.523 15.341a1.18 1.18 0 1 0 0 2.36 1.18 1.18 0 0 0 0-2.36Zm-11.046 0a1.18 1.18 0 1 0 0 2.36 1.18 1.18 0 0 0 0-2.36ZM17.945 8.21l1.79-3.1a.37.37 0 0 0-.64-.37L17.28 7.88A11.05 11.05 0 0 0 12 6.55c-1.9 0-3.69.48-5.28 1.33L4.905 4.74a.37.37 0 0 0-.64.37l1.79 3.1C3.205 9.77 1.27 12.59 1 15.9h22c-.27-3.31-2.205-6.13-5.055-7.69Z"/></svg>
      </span>
      <span><small>BAIXAR PARA</small><strong>Android</strong></span>
    </a>
    <span class="store-badge ios disabled" aria-label="Aplicativo TurnoPronto para iOS em breve">
      <span class="store-icon apple" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="24" height="24" role="img"><path fill="currentColor" d="M16.365 1.43c0 1.14-.42 2.19-1.26 3.14-.99 1.12-2.18 1.77-3.47 1.67-.02-.14-.03-.3-.03-.46 0-1.1.48-2.28 1.33-3.19.42-.46.95-.85 1.59-1.16.64-.3 1.24-.47 1.82-.5.02.17.02.34.02.5Zm4.2 14.58c-.35.79-.77 1.52-1.28 2.19-.69.91-1.25 1.54-1.68 1.89-.67.61-1.39.92-2.17.94-.56 0-1.23-.16-2.01-.48-.78-.32-1.5-.48-2.16-.48-.69 0-1.43.16-2.22.48-.79.32-1.43.49-1.91.51-.74.03-1.48-.29-2.22-.97-.47-.41-1.06-1.06-1.76-1.96-.75-.96-1.37-2.08-1.85-3.35-.52-1.37-.78-2.7-.78-3.98 0-1.47.32-2.74.95-3.81a5.6 5.6 0 0 1 2.01-2.03 5.4 5.4 0 0 1 2.72-.76c.59 0 1.36.18 2.31.54.95.36 1.56.54 1.83.54.2 0 .88-.21 2.03-.64 1.09-.39 2.01-.55 2.76-.49 2.04.17 3.57.97 4.59 2.41-1.82 1.1-2.72 2.65-2.7 4.64.02 1.55.58 2.84 1.68 3.87.5.48 1.06.85 1.68 1.11-.14.41-.28.8-.45 1.18Z"/></svg>
      </span>
      <span><small>EM BREVE PARA</small><strong>iOS</strong></span>
    </span>
  </div>
  <div class="app-download-note">Android disponível em versão de teste.</div>
</div>
<a class="installer-link" href="<?=e(url('install.php'))?>">Instalar / recriar banco de demonstração</a></section></div><script src="<?=e(asset('js/app.js'))?>"></script></body></html>
