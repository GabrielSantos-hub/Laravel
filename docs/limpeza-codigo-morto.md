# Limpeza de código morto

Varredura em `app/`, `bootstrap/`, `config/`, `database/`, `resources/`,
`routes/`, `tests/`, `docs/`, `public/`, `composer.json` e `package.json`.
Sem ferramenta permanente nova. Evidência por `rg` / referências dinâmicas
(`view()`, `@include`, `@extends`, `<x-...>`, bindings, seeders, testes).

## Removido

| Item | Motivo | Evidência |
| --- | --- | --- |
| `resources/views/components/auth-card.blade.php` | Componente Blade sem chamador | Nenhum `<x-auth-card>`, `view('components.auth-card')` ou `@include`. O login usa `auth.layout` + classe CSS `auth-card`. |
| `promptDasMetricas()` em `tests/Feature/MetricsResetTest.php` | Função de teste sem chamada | Só a definição; os testes criam `Prompt` inline. |

## Mantido de propósito

- `laravel/pint`, `laravel/tinker`, `pestphp/pest`, `phpunit/phpunit`: pedido explícito e skeleton.
- `laravel/sail`, `laravel/pail`, `fakerphp/faker`, `nunomaduro/collision`: skeleton do Laravel 12; o `composer.json` `dev` usa `pail`.
- `axios` em `resources/js/bootstrap.js` (importado por `app.js`): o módulo é carregado; não foi removido.
- Eventos novos do `SecurityLogger` (`history_cleared`, `admin_metrics_reset`, `admin_metrics_reset_cleared`): emitidos nesta rodada.
- Views `errors/{code}.blade.php` e fallbacks `4xx`/`5xx`: Laravel resolve por status.
- Seeders de catálogo: `DatabaseSeeder` e README.
- `AppSetting`: corte de métricas.

## Candidatos incertos (não removidos)

- `window.axios` não é referenciado no JS da aplicação (só `fetch`). Pode ser leftover do skeleton.
- `resources/views/auth/layout.blade.php` é usado só pelo login; parecer “extra” mas tem `@extends`.
- `docs/baseline-testes.md` ainda cita `ExampleTest.php`, que já foi apagado (documento velho, não arquivo morto).

## Banco (somente relato)

Não há migration destrutiva. Colunas/tabelas do skeleton sem fluxo da aplicação:

- `password_reset_tokens`
- `users.email_verified_at`
- `jobs`, `job_batches`, `failed_jobs`
- `cache`, `cache_locks`

`app_settings` é usada (`metrics_reset_at`).

## Dependências

Nenhum pacote removido. `composer.json` / `package.json` não têm dependência
óbvia sem import, exceto o caso incerto do `axios` (acima).
