# Validação de WhatsApp no cadastro

O cadastro de profissional e empresa usa uma segunda etapa de validação do número informado.

## Fluxo

1. `POST /api/v1/auth/register/start`
2. O backend valida CPF/CNPJ, idade, dados Pix, senha e consentimentos.
3. É criado um pedido temporário de cadastro.
4. Um código de 6 dígitos é enviado ao WhatsApp.
5. `POST /api/v1/auth/register/verify`
6. Somente após o código correto a conta definitiva é criada.
7. O telefone recebe `phone_verified_at`.

O código expira em 10 minutos e possui limite de tentativas. Reenvios têm cooldown e limite.

## Configuração de produção

As credenciais ficam apenas em `config/config.local.php`, que não é versionado.

### Meta WhatsApp Cloud API

```php
'whatsapp' => [
    'driver' => 'meta_cloud',
    'meta_api_version' => 'v23.0',
    'meta_phone_number_id' => '...',
    'meta_access_token' => '...',
    'meta_template_name' => 'turnopronto_codigo_verificacao',
    'meta_template_language' => 'pt_BR',
],
```

O template deve estar previamente aprovado no WhatsApp Business Manager.

### Webhook

Para usar um gateway próprio/Twilio/automação intermediária:

```php
'whatsapp' => [
    'driver' => 'webhook',
    'webhook_url' => 'https://...',
    'webhook_token' => '...',
],
```

O webhook recebe JSON:

```json
{
  "to": "+55...",
  "code": "123456",
  "purpose": "turnopronto_phone_verification",
  "message": "Seu código TurnoPronto é 123456. Ele expira em 10 minutos."
}
```

## Desenvolvimento local

O driver `debug` grava o código somente no log do PHP e exige `debug=true`.

Nunca habilitar `debug` como solução de validação em produção.

## Dados coletados

Profissional:
- nome completo
- WhatsApp validado
- e-mail
- CPF
- data de nascimento (18+)
- endereço/CEP/cidade/UF
- atividade e categorias de interesse
- Pix: tipo, chave, titular e CPF/CNPJ do titular
- senha
- aceites de Termos, Privacidade e mensagens transacionais

Empresa:
- nome e CPF do responsável
- WhatsApp validado
- e-mail
- razão social
- nome fantasia
- CNPJ
- endereço/CEP/cidade/UF
- Pix para devoluções/reembolsos
- senha
- mesmos aceites legais
