# TurnoPronto Web

Portal web + backend REST da TurnoPronto.

> **Marca:** TurnoPronto  
> **Slogan:** “Nós cuidamos do extra que você precisa.”

## Stack

- PHP 8.1+ (sem framework obrigatório)
- MariaDB/MySQL via PDO
- Apache/XAMPP
- HTML5 + CSS responsivo + JavaScript puro
- API REST `/api/v1` para o app Android/iOS
- Sem Docker
- Sem Composer obrigatório

A implementação evita recursos exclusivos de PHP 8.4 para funcionar em instalações XAMPP com PHP 8.1+.

## O que já funciona

### Empresa
- login por perfil
- dashboard visual no padrão dos mockups
- KPIs de vagas, turnos, preenchimento e gasto
- publicação de vagas/turnos
- listagem de vagas
- visualização de profissionais sugeridos
- indicadores de reputação/presença
- estrutura para escalas, financeiro e avaliações

### Profissional
- dashboard visual no padrão dos mockups
- oportunidades próximas
- detalhe da vaga
- aceite de turno
- agenda e próximos turnos
- check-in por PIN
- check-out
- registro de ganhos
- reputação operacional
- documentos verificados

### Administração
- login administrativo
- KPIs básicos do marketplace
- base preparada para KYC, disputas, antifraude e auditoria

### API mobile
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`
- `GET /api/v1/opportunities`
- `GET /api/v1/shifts/{id}`
- `POST /api/v1/shifts/{id}/accept`
- `GET /api/v1/assignments`
- `GET /api/v1/assignments/{id}`
- `POST /api/v1/assignments/{id}/check-in`
- `POST /api/v1/assignments/{id}/check-out`
- `GET /api/v1/earnings`
- `GET /api/v1/reputation`
- `GET /api/v1/documents`
- `GET /api/v1/health`

## Instalação no XAMPP

1. Copie a pasta para:
   - Windows: `C:\xampp\htdocs\turnopronto_web`
2. Inicie **Apache** e **MySQL/MariaDB** no XAMPP.
3. Abra:
   - `http://localhost/turnopronto_web/install.php`
4. O instalador já sugere o padrão do XAMPP:
   - host `127.0.0.1`
   - porta `3306`
   - banco `turnopronto`
   - usuário `root`
   - senha vazia
5. Clique em **Instalar / recriar ambiente de demonstração**.
6. Abra:
   - `http://localhost/turnopronto_web/login`

Se `mod_rewrite` estiver desativado no Apache, habilite o módulo e confirme `AllowOverride All` para a pasta `htdocs`.

## Acessos de demonstração

Durante a instalação, defina uma senha de demonstração com pelo menos 8 caracteres. Ela será usada pelos três perfis abaixo.

- Empresa: `empresa@turnopronto.local`
- Profissional: `juliana@turnopronto.local`
- Admin: `admin@turnopronto.local`

Essas credenciais existem **somente para desenvolvimento** e devem ser removidas antes da produção.

## Conectar o app

Em outro dispositivo na mesma rede, use o IP do PC com XAMPP, por exemplo:

```text
http://192.168.1.50/turnopronto_web/api/v1
```

Teste:

```text
http://192.168.1.50/turnopronto_web/api/v1/health
```

No Android Emulator, o host da máquina normalmente é acessado via `10.0.2.2`.

## Segurança já aplicada

- PDO com prepared statements nativos
- `password_hash()` / `password_verify()`
- regeneração de sessão após login
- cookie HttpOnly + SameSite=Lax
- CSRF nos formulários web
- tokens da API armazenados apenas como SHA-256
- expiração de token
- separação de papéis empresa/profissional/admin
- prefixo `tp_` em todas as tabelas
- logs/auditoria previstos no schema

## Antes de produção

Ainda será necessário:

- HTTPS obrigatório
- rate limit persistente
- recuperação de senha
- MFA administrativo
- CORS restritivo
- armazenamento seguro de documentos
- KYC real
- LGPD/consentimentos finais
- gateway de pagamento + split
- push/WhatsApp/SMS
- antifraude
- geolocalização assinada no mobile
- QR Code real de check-in
- testes automatizados E2E

Veja [DEVELOPMENT_PLAN.md](DEVELOPMENT_PLAN.md).
