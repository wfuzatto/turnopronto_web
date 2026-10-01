# API TurnoPronto v1

Base local típica:

`http://IP_DO_XAMPP/turnopronto_web/api/v1`

## Login

`POST /auth/login`

```json
{
  "email": "juliana@turnopronto.local",
  "password": "<senha-definida-no-instalador>"
}
```

Resposta:

```json
{
  "ok": true,
  "token": "...",
  "user": {
    "id": 2,
    "role": "professional"
  }
}
```

Enviar nas demais chamadas:

`Authorization: Bearer TOKEN`

## Saúde

`GET /health`

## Oportunidades

`GET /opportunities`

## Detalhe

`GET /shifts/{id}`

## Aceitar

`POST /shifts/{id}/accept`

## Turnos do profissional

`GET /assignments`

`GET /assignments/{id}`

## Check-in

`POST /assignments/{id}/check-in`

```json
{"pin":"<pin-informado-pela-empresa>"}
```

## Check-out

`POST /assignments/{id}/check-out`

## Ganhos

`GET /earnings`

## Reputação

`GET /reputation`

## Documentos

`GET /documents`
