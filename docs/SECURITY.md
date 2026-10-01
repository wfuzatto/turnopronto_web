# Segurança — baseline

O projeto atual é um MVP de desenvolvimento. Já usa prepared statements, hash de senha, CSRF web, cookies HttpOnly e tokens de API armazenados por hash.

Antes de produção:

1. forçar HTTPS;
2. `debug=false`;
3. remover usuários demo;
4. senha exclusiva do banco;
5. mover uploads para armazenamento privado;
6. limitar MIME/tamanho e fazer varredura de arquivo;
7. rate limit por IP/usuário/rota;
8. rotação/revogação de tokens;
9. MFA para admin;
10. CORS com allowlist;
11. assinatura/verificação de webhooks;
12. idempotency keys em operações financeiras;
13. auditoria append-only para decisões críticas;
14. backup testado;
15. revisão OWASP antes do lançamento.
