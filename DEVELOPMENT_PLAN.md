# Plano de desenvolvimento — turnopronto_web

## Objetivo

Entregar o portal web da TurnoPronto para **empresa, profissional e administração**, além de ser o **backend oficial/API** consumido pelo app Android/iOS.

A referência visual é o conceito aprovado: interface clara, cards arredondados, azul/verde, navegação lateral e KPIs, buscando fidelidade de composição às imagens geradas.

## Arquitetura escolhida

Browser empresa/profissional/admin → Apache/XAMPP → PHP → Web + REST API v1 → PDO → MariaDB.

- Sem Docker.
- Sem Node em produção.
- Sem Composer obrigatório no núcleo.
- Mesmo banco e regras de negócio para web e app.

---

## Fase W0 — Fundação — CONCLUÍDA

- roteamento por .htaccess
- configuração local fora do Git
- PDO/MariaDB
- sessão e bearer token
- CSRF
- papéis empresa/profissional/admin
- layout responsivo
- identidade TurnoPronto
- instalador web
- dados de demonstração
- cadastro público de empresa/profissional
- aprovação administrativa antes da operação

## Fase W1 — Empresa MVP — CONCLUÍDA NO FLUXO PRINCIPAL

Implementado:

- dashboard visual
- criação de vaga
- edição de vaga
- cancelamento de vaga futura
- lista e filtros de vagas
- detalhe operacional da vaga
- profissionais confirmados
- candidaturas
- aprovação/rejeição manual
- aceite automático ou aprovação da empresa
- convite direto de profissional
- escalas reais
- financeiro/ledger real
- trilha de auditoria para ações críticas
- PIN de check-in por vaga
- edição de conta empresarial

Próximos incrementos de empresa:

- múltiplas unidades/filiais
- centros de custo
- templates de vaga
- exportação CSV/PDF
- usuários e permissões dentro da empresa

## Fase W2 — Profissional web — BASE IMPLEMENTADA

- home
- oportunidades
- detalhe da vaga
- aceite automático
- candidatura para vaga com aprovação manual
- próximos turnos
- turno atual
- check-in/check-out
- ganhos
- reputação
- documentos
- edição de perfil/categorias/PIX
- upload privado de documentos PDF/JPG/PNG

Próximos incrementos:

- filtros geográficos reais
- disponibilidade semanal
- favoritos
- mensagens
- anexos/documentos

## Fase W3 — Reputação, avaliações e cancelamentos — BASE OPERACIONAL IMPLEMENTADA

Implementado:
- avaliação bilateral após turno concluído;
- opinião subjetiva separada de presença/pontualidade objetivas;
- cancelamento pelo profissional com peso por antecedência;
- evento de reputação auditável;
- contestação de ocorrência negativa;
- revisão humana pelo administrador;
- reversão de pontos quando a contestação é aceita;
- auditoria das decisões.

Próximos incrementos:
- no-show confirmado pela empresa com evidências;
- peso por recência;
- TurnoScore estatístico completo;
- anexos em disputas;
- SLA e fila de revisão.

## Fase W4 — Pagamentos

Criar PaymentProvider desacoplado para split, conciliação, webhook idempotente, chargeback, estorno, comissão e repasse.

## Fase W5 — KYC / LGPD

Consentimentos versionados, documentos privados, CPF/CNPJ, selfie/liveness via fornecedor, validade de certificados, retenção e trilha de auditoria.

## Fase W6 — Administração — BASE OPERACIONAL IMPLEMENTADA

Implementado:
- fila de aprovação de empresas;
- fila de aprovação de profissionais;
- revisão humana de contestação de reputação;
- auditoria de ações críticas.

Base documental já implementada com upload privado e aprovação/rejeição administrativa. Próximos: validação automática de CPF/CNPJ, selfie/liveness, consentimentos versionados, validade de certificados, disputas com anexos, no-show, bloqueios, fraude, conciliação e gestão avançada de usuários.

## Fase W7 — Comunicação

E-mail, push, WhatsApp/SMS por adaptadores, preferências, templates e fallback.

## Fase W8 — Produção

HTTPS, staging, migrations versionadas, backup, rate limit, logs estruturados, observabilidade, testes de carga, política de secrets e CI/CD.

---

## Definition of Done do web MVP

O web MVP só é fechado quando:

- empresa cria/edita/cancela vaga;
- profissional visualiza e aceita ou se candidata;
- empresa aprova quando a vaga exigir aprovação;
- preenchimento da vaga é consistente;
- escala mostra profissionais confirmados;
- check-in/check-out funciona;
- conclusão gera ledger sem duplicar lançamento;
- reputação recebe evento de conclusão;
- ações críticas entram na auditoria;
- API usa as mesmas regras;
- nenhuma senha/chave real está versionada.
