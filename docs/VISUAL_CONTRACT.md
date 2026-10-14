# Contrato visual — TurnoPronto

## Regra obrigatória

Os mockups aprovados durante o desenvolvimento são a referência visual do produto.

Alterações de backend, regras de negócio, pagamentos, reputação, KYC, cadastro ou integrações **não podem descaracterizar** o frontend aprovado.

O WEB e o APP devem preservar, salvo mudança solicitada explicitamente:

- identidade TurnoPronto;
- azul, verde, branco/off-white e tons de apoio definidos;
- navegação lateral no desktop WEB;
- topbar e busca;
- cards arredondados;
- KPIs;
- hierarquia tipográfica;
- espaçamento;
- tabelas e cards de vagas;
- perfil/reputação;
- comportamento responsivo;
- estados de sucesso, alerta e pendência;
- padrão visual do profissional e da empresa.

## Falha visual

Uma página com CSS ausente, layout cru, navegação desaparecida, tipografia padrão do navegador ou componentes sem estilo é considerada **falha de deploy**, mesmo que a API esteja respondendo HTTP 200.

## QA obrigatório

Antes de considerar um deploy WEB concluído:

1. validar PHP;
2. executar testes de regressão de rotas/assets;
3. gerar um único stylesheet de produção;
4. confirmar que o CSS publicado contém os seletores principais;
5. confirmar login e health check;
6. preservar a composição dos mockups aprovados.

## Regra para novas telas

Novas telas devem parecer parte do mesmo produto. Não criar páginas genéricas ou com framework visual diferente sem aprovação explícita.

## Baselines

Quando screenshots finais homologados forem adicionados ao repositório, eles devem ficar em:

docs/visual-baseline/

Esses arquivos passam a ser a referência de comparação visual para futuras alterações.


## Baseline aprovada — página pública /vagas (UX V3, 14/10/2026)

Referência: mockup aprovado de vagas, com hero em azul/verde, busca central, chips de categoria, cards de oportunidades com **remuneração evidente** e painel "Como funciona?".

Implementação:
- HTML/PHP: \`app/views/public_opportunities_modern.php\`
- CSS isolado: \`public/assets/css/landing-ux-v3.css\`
- JS isolado: \`public/assets/js/landing-ux-v3.js\`

**Regras de preservação**
1. Não trocar valores reais, empresas, fotos ou datas por demonstrações fictícias.
2. Destacar remuneração, data, hora, local e forma de candidatura sem impor cadastro apenas para navegar.
3. Preservar os formulários POST, CSRF e diálogos de detalhes, acompanhamento e interesse.
4. Busca por profissão, cidade, data e categorias deve continuar funcional. Lista e cards devem ser responsivos.
5. Não interferir no CSS de telas autenticadas, skins antigas de pré-visualização ou rotas do backoffice.
6. Manter o verificador de produção sensível à presença dos assets UX V3 e à versão declarada no HTML.
