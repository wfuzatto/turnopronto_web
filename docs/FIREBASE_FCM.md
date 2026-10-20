# Firebase Cloud Messaging — Backend TurnoPronto

A API mantém o MySQL como fonte de verdade: a tabela `tp_professional_categories` determina os destinatários; `tp_notifications` mantém o histórico; `tp_push_devices` relaciona tokens aos profissionais; `tp_push_deliveries` envia uma vez por vaga/usuário/dispositivo e faz retries.

## Configuração (sem versionar segredo)

1. No Firebase, ative Cloud Messaging HTTP v1 e gere a chave JSON em Configurações do projeto > Contas de serviço. Armazene-a **fora** do public_html (permissões 0600).
2. Em `config/config.local.php` adicione `'firebase'=>['service_account_path'=>'/caminho/privado/service-account.json']`; ou configure `TP_FIREBASE_SERVICE_ACCOUNT_PATH` no ambiente do PHP CLI.
3. Importe `database/migrations/20261019_01_firebase_push.sql` no banco da aplicação com phpMyAdmin (ou conceda permissão de CREATE TABLE para as tabelas serem criadas no primeiro registro).
4. No cron do provedor, configure **a cada minuto**: `/usr/bin/php /caminho/turnopronto/scripts/dispatch_push.php 100` (ajuste o caminho do PHP e a raiz).
5. Garanta PHP CLI com **openssl**, **curl** e **PDO MySQL** e HTTPS de saída em 443.

## Rotas (todas autenticadas com Bearer token do profissional)

- `POST /api/v1/push/devices` — JSON `{"token":"TOKEN_FCM","platform":"android","enabled":true}` (reatualiza em cada login/refresh e associa token à conta atual).
- `POST /api/v1/push/devices/unregister` — JSON `{"token":"TOKEN_FCM"}` no logout.

O app só registra tokens após a permissão do sistema operacional. Não envie a conta de serviço ao aplicativo. Firebase não substitui login/CPF/áreas no MySQL.

## Verificação

1. Entrar no aplicativo, autorizar notificações e conferir `tp_push_devices` (um dispositivo com `enabled=1`).
2. Publicar uma vaga futura numa categoria do profissional, sem duplicar o mesmo anúncio.
3. Conferir `tp_notifications` com `type='matching_shift'` e `tp_push_deliveries` com `status='pending'`.
4. Executar manualmente `php scripts/dispatch_push.php 100` e conferir `status='sent'`, ou investigar `last_error`. Repetições têm backoff e limite de cinco tentativas.
5. Testar app em segundo plano e totalmente fechado; tocar deve abrir a vaga. Logout apaga o token do dispositivo.

Sem projeto/credenciais Firebase, a fila pode receber eventos, mas o cron não fará envio. Não agende o cron antes de configurar a conta de serviço.

## Limitar notificações por distância

O profissional escolhe de **5 a 200 km** (passos de 5 km) em `/profissional/perfil`
ou no menu **Conta** do aplicativo. As preferências ficam no MySQL (`tp_push_preferences`).
O GPS é utilizado apenas quando o profissional define ou atualiza seu ponto de referência.
Não existe rastreamento contínuo do telefone.

Importe `database/migrations/20261019_02_push_distance.sql` caso o usuário MySQL
não possua permissão de CREATE TABLE. O filtro usa distância geodésica aproximada
em linha reta pela fórmula Haversine, tanto ao criar a notificação quanto antes de
despachá-la. Se um raio foi salvo mas a vaga não possui latitude/longitude, a
notificação é suprimida para não violar o limite. Profissionais que ainda não
configuraram o raio conservam o comportamento anterior (sem filtro).

API autenticada: GET/POST `/api/v1/push/preferences`, com POST
`{"radius_km":50,"latitude":-23.55,"longitude":-46.63}`.
O backend identifica o usuário pelo token e não aceita IDs de terceiros.
