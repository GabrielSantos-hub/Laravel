# Linha de base de testes — Fase 0 (hardening)

Registro do estado da suíte **antes** das fases 1–4. Gerado em 2026-10-02
na branch `hardening/seguranca`.

## Ambiente

| Item | Valor |
| --- | --- |
| PHP | 8.2.12 (CLI, XAMPP) |
| Laravel | 12.69.2 |
| Banco nos testes | SQLite `:memory:` (`phpunit.xml`) |
| `AI_PROVIDER` nos testes | `null` |
| Driver de cobertura | **ausente** (sem PCOV e sem Xdebug) |

## Resultado da suíte

Comando: `php artisan test`

| Momento | Resultado | Asserções | Duração |
| --- | --- | --- | --- |
| Antes das alterações da Fase 0 | 381 passed, 0 failed | 1901 | 25,22 s |
| Depois do endurecimento Docker + updates de auditoria | 381 passed, 0 failed | 1901 | 7,94 s |

Nenhum teste existente foi removido ou enfraquecido. A Fase 0 não adicionou
casos Pest novos: as mudanças são de infraestrutura (Docker/Nginx) e de
dependências, sem alteração de comportamento da aplicação.

## Cobertura

**NÃO FEITO.** `php artisan test --coverage --min=0` falhou com:

```
ERROR  Code coverage driver not available. Did you install Xdebug or PCOV?
```

O PHP local (8.2.12) não carrega PCOV nem Xdebug. Não foi instalado driver
no sistema para não alterar o ambiente fora do repositório.

Para gerar cobertura no futuro (local ou CI):

1. Habilitar a extensão PCOV (preferível) ou Xdebug em `mode=coverage`.
2. Rodar `php artisan test --coverage --min=0`.
3. Atualizar esta página com as porcentagens por diretório (`app/`).

## Suites presentes

- `tests/Unit` — serviços do pipeline, guardrail, provedores (Null/Gemini)
- `tests/Feature` — auth, perfil/avatar, gerador, admin, catálogos, páginas de erro, auditoria XSS/SQLi, rate limit

Arquivo placeholder: `tests/Unit/ExampleTest.php` (permanece; limpeza é da Fase 4).

## Auditoria de dependências

### Composer (`composer audit`)

| Momento | Resultado |
| --- | --- |
| Antes | 2 avisos em `league/commonmark` 2.10.1 (GHSA-97jj-33gv-5xf9, GHSA-3q6v-r5mr-hxv8) |
| Depois | `league/commonmark` 2.10.3 — nenhum advisory |

Atualização patch, sem major. Laravel 12 exige `league/commonmark ^2.8.1`.

### npm (`npm audit`)

| Momento | Resultado |
| --- | --- |
| Antes | 7 vulnerabilidades (2 critical, 4 high, 1 low): axios, concurrently/shell-quote, esbuild, form-data, nanoid, postcss |
| Depois | 0 vulnerabilidades |

Atualizações compatíveis (sem major):

- `axios` 1.17.0 → 1.20.0
- `concurrently` 9.2.1 → 9.2.4 (`shell-quote` 1.8.3 → 1.9.0)
- `vite` 7.x → 7.3.6 (`esbuild` 0.27.7 → 0.28.2, `postcss` 8.5.15 → 8.5.28, `nanoid` 3.3.12 → 3.3.19)
- `form-data` → 4.0.6

`concurrently@10` existe, mas é major; não foi aplicado.
