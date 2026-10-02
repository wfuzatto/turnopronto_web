# Diagnóstico do dashboard — 2026-10-01

## Causa comprovada

`View::render` e `RendererV3::render` recebiam um parâmetro `$data` e executavam
`extract($data, EXTR_SKIP)`. As rotas autenticadas enviam um envelope
`['title'=>..., 'data'=>Data::companyDashboard(...), 'user'=>...]`.
Como `$data` já existe no escopo do renderer, EXTR_SKIP ignora a chave `data`.
A view recebe o envelope inteiro, sem `kpis`, `company` ou `profile` no nível esperado.

Reprodução em subprocesso no PHP 8.2.12, antes da alteração:

```
TypeError: money(): Argument #1 ($value) must be of type string|int|float,
null given, called in app/views/company_dashboard.php on line 7
#1 app/RendererV3.php(15): require(...)
```

O erro acontece durante o buffer da view, antes de `ob_get_clean()` e do require
do layout. Ao terminar a execução, o PHP descarrega o buffer parcial: banner,
saudação e primeiros KPIs, sem documento HTML, sidebar ou topbar.
O dashboard profissional falha pelo mesmo motivo em `money($k['earnings'])`.
O smoke test visual não recebe esse payload e não exercitava o defeito.

## Correção

Renomeado o argumento para `$variables`, mantendo EXTR_SKIP para proteger controles
do renderer. Descartado o buffer parcial se a view lança uma exceção.
Todas as rotas usam View e layout.php; removidos renderer e layout duplicados,
headers temporários e route-check (que divulgava caminhos físicos).
Mantidos front controller, regras de rewrite com precedência das rotas e assets.
Corrigida também a normalização de barras Windows em base_path, após falha do
teste de regressão existente. O contrato visual foi preservado.

## Evidências e validação local

- MariaDB isolado em 127.0.0.1:3308, base tp_diagnostic; nenhum banco existente recriado.
- Contas demo criadas pelo instalador local, senha aleatória fora do repositório.
- GET /login, extração CSRF, POST via curl com cookie jar, GET autenticado dos dois dashboards.
- Empresa: HTTP 200, HTML completo, 14897 caracteres; profissional: 22310 caracteres.
- Ambos contêm doctype, app-shell, sidebar, topbar, main-content e fechamento html.
- CSS: HTTP 200, text/css, 43184 bytes; logo e componentes presentes.
- HTML autenticado capturado renderizado no Chrome: sidebar, topbar, logo, KPIs,
  cards, tabela e painéis presentes; app-shell com display:flex, sidebar 250px,
  logo carregado. Isso é inspeção do HTML capturado via curl, não login pelo navegador.
- Testes de render em subprocesso e de base_path local/produção passam.
- CI adiciona login real dos dois perfis com MariaDB e valida HTML e CSS.

Capturas e stack trace local: C:/xampp/tmp/tp-diagnostic/.
Não há mockups homologados em docs/visual-baseline no repositório.

## Servidor público

Sem sessão, /empresa/dashboard retorna 302 Location:/login.
Headers observados antes do deploy: nginx/1.24.0, PHP/8.5.7, X-Cache:MISS.
O route-check antigo confirmava os arquivos RendererV3 e layout_runtime_v3 publicados.
Não há prova de OPcache, LSCache ou arquivo físico sequestrando a rota.
O handler PHP e a configuração OPcache não podem ser deduzidos desses headers.
Sem credenciais FTP não foi possível inventariar arquivos físicos remotos.
Sem credenciais dos perfis em produção, a validação autenticada pública permanece pendente.
