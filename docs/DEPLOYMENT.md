# Deploy e operação

O deploy de produção roda no GitHub Actions quando `main` recebe um push ou por execução manual. O job só envia os arquivos depois do lint PHP e das validações de segurança.

## Produção atual

- Hospedagem: Locaweb
- Raiz FTP: `/home/turnopronto1/`
- Document root: `public_html/`
- Protocolo: FTP, porta 21
- URL oficial: `https://turnopronto.com.br/`
- API oficial: `https://turnopronto.com.br/api/v1`

O arquivo `config/config.local.php` existe somente no servidor e fica fora do deploy. Senhas do banco e tokens de provedores nunca são versionados. O mesmo vale para uploads e logs persistentes.

## Migrations

As migrations em `database/migrations/` devem ser aplicadas manualmente, uma por vez, pelo phpMyAdmin ou por acesso administrativo ao banco. Antes de aplicar uma migration:

1. faça um backup do banco;
2. verifique se o nome ainda não existe em `tp_migrations`;
3. aplique o SQL em transação quando suportado;
4. registre o nome e o horário em `tp_migrations`;
5. valide `/api/v1/health` e a tela de login.

## Rollback

O rollback é feito selecionando o commit anterior conhecido como bom no GitHub e executando novamente o workflow `Deploy WEB` por `workflow_dispatch`. O deploy é não destrutivo, portanto uploads, logs e `config.local.php` permanecem no servidor.
