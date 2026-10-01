# Plano de desenvolvimento — `turnopronto_web`

## Objetivo

Entregar o portal web da TurnoPronto para **empresa, profissional e administração**, além de ser o **backend oficial/API** consumido pelo app Android/iOS.

A referência visual é o conceito aprovado: interface clara, cards arredondados, azul/verde, navegação lateral e KPIs, buscando fidelidade de composição às imagens geradas.

## Arquitetura escolhida

```text
Browser empresa/profissional/admin
            |
            v
Apache / XAMPP
            |
      PHP front controller
       /           \
  Web MVC-ish      REST API v1
       \           /
          PDO
           |
       MariaDB
           |
  pagamentos/KYC/push (adapters futuros)
```

### Por que assim

- roda diretamente em XAMPP;
- não exige Docker;
- não exige Node em produção;
- não exige Composer para o núcleo;
- uma única regra de negócio para web e app;
- fácil migração futura para VPS/cloud sem reescrever banco/API.

---

## Fase W0 — Fundação — CONCLUÍDA

- estrutura de pastas
- roteamento por `.htaccess`
- configuração local fora do Git
- PDO/MariaDB
- autenticação de sessão
- autenticação de API por bearer token
- CSRF
- papéis empresa/profissional/admin
- layout responsivo
- identidade TurnoPronto
- instalador web
- dados de demonstração

Critério de aceite: clonar, copiar para `htdocs`, executar `install.php` e entrar com os três perfis.

## Fase W1 — Empresa MVP — BASE IMPLEMENTADA

- dashboard
- KPIs
- criação de vaga
- lista de vagas
- candidatos
- profissionais sugeridos
- status da vaga
- estrutura de escalas
- estrutura financeira

Próximos incrementos:

- editar/cancelar vaga;
- candidatura manual vs aceite automático;
- convite direto;
- múltiplas unidades/filiais;
- centros de custo;
- templates de vaga;
- aprovação de candidato pela empresa;
- exportação CSV/PDF.

## Fase W2 — Profissional web — BASE IMPLEMENTADA

- home
- oportunidades
- detalhe da vaga
- aceitar vaga
- próximos turnos
- turno atual
- check-in/check-out
- ganhos
- reputação
- documentos

Próximos incrementos:

- filtros geográficos reais;
- disponibilidade semanal;
- cancelamento com regras;
- contestação de ocorrência;
- favoritos;
- mensagens com contratante;
- anexos/documentos.

## Fase W3 — Reputação e no-show

Implementar `TurnoScore` de maneira auditável.

Componentes:

- comparecimento;
- pontualidade;
- conclusão;
- cancelamentos;
- no-show;
- avaliações normalizadas;
- peso por recência;
- revisão humana;
- contestação;
- histórico explicável.

Regra crítica: nenhuma penalidade grave deve ser aplicada apenas por uma nota subjetiva.

## Fase W4 — Pagamentos

Criar interface `PaymentProvider` para permitir trocar fornecedor sem reescrever o produto.

Fluxo:

```text
financiamento da vaga
 -> reserva/autorização no PSP
 -> turno concluído
 -> janela de contestação
 -> split
 -> profissional
 -> comissão TurnoPronto
```

Módulos:

- ledger imutável;
- conciliação;
- webhook idempotente;
- chargeback;
- estorno;
- cancelamento;
- comissão;
- repasse;
- notas/documentos fiscais.

## Fase W5 — KYC / LGPD

- consentimentos versionados;
- upload privado;
- CPF/CNPJ;
- documento de identidade;
- selfie/liveness via fornecedor;
- validade de certificados;
- política de retenção;
- download/exclusão conforme regras legais;
- trilha de auditoria.

## Fase W6 — Administração

- fila de KYC;
- disputas;
- no-show;
- recursos de reputação;
- bloqueios;
- fraude;
- financeiro;
- conciliação;
- usuários;
- empresas;
- vagas;
- painel de saúde da operação;
- auditoria completa.

## Fase W7 — Comunicação

- e-mail transacional;
- push via app;
- WhatsApp/SMS por adaptador;
- preferências de comunicação;
- templates;
- tentativas e fallback.

## Fase W8 — Produção

- HTTPS;
- ambiente `staging`;
- migrations versionadas;
- backup automático;
- rate limiting;
- WAF/CDN opcional;
- logs estruturados;
- Sentry/observabilidade ou equivalente;
- métricas de API;
- testes de carga;
- política de secrets;
- CI/CD.

---

## Modelo de dados principal

- `tp_users`
- `tp_companies`
- `tp_company_members`
- `tp_professionals`
- `tp_job_categories`
- `tp_professional_categories`
- `tp_shifts`
- `tp_shift_applications`
- `tp_assignments`
- `tp_reviews`
- `tp_reputation_events`
- `tp_documents`
- `tp_ledger`
- `tp_notifications`
- `tp_api_tokens`
- `tp_audit_logs`

---

## Definition of Done do web MVP

O web MVP só é considerado fechado quando:

- empresa cria uma vaga do início ao fim;
- profissional recebe/visualiza a oportunidade;
- profissional aceita;
- vaga reflete preenchimento;
- profissional faz check-in e check-out;
- conclusão gera lançamento financeiro;
- reputação é atualizada por evento;
- empresa e profissional conseguem contestar ocorrências;
- admin consegue auditar a trilha;
- app consome os mesmos endpoints;
- nenhuma senha/chave real está versionada.
