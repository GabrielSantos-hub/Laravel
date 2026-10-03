# Bateria de testes de segurança — rodada única

Executada em 2026-10-03 contra o ambiente local (`127.0.0.1:8000` com
`APP_DEBUG=false` só no processo, e Apache Laragon em `Laravel.test`).
Usuários temporários. Não é uma afirmação de “100% seguro”.

| Categoria | Técnica | Alvo | Resultado | Severidade | Correção |
| --- | --- | --- | --- | --- | --- |
| 1 Autenticação | Força bruta | `POST /login` | RESISTIU | — | Limiters IP e e-mail+IP já existentes |
| 1 Autenticação | Enumeração | login inválido existente vs ausente | RESISTIU | — | Mesma mensagem |
| 1 Autenticação | Sessão | regeneração no login, logout, cookies HttpOnly/SameSite | RESISTIU | — | Já existente |
| 1 Autenticação | `must_change_password` JSON | `POST /prompts/generate` | FALHOU→CORRIGIDO | Alto | JSON passa a 403 em vez de redirect |
| 2 Autorização | IDOR | show/feedback/destroy/limpar histórico | RESISTIU | — | Só o dono |
| 2 Autorização | Escalada vertical | rotas admin GET/POST | RESISTIU | — | `can:admin` |
| 2 Autorização | Mass assignment | `role` no cadastro | RESISTIU | — | `role` fora de `$fillable` |
| 3 CSRF | Rotas de escrita | grupo `web` + token nos formulários | RESISTIU | — | — |
| 3 CSRF | GET em URI só de escrita | `/prompts/historico` autenticado | RESISTIU | — | 404 sem `Allow` |
| 4 Injeção | SQLi | filtro `action` da auditoria | RESISTIU | — | Validação `alpha_dash` |
| 4 Injeção | XSS armazenado | nome do usuário | RESISTIU | — | Escape Blade |
| 4 Injeção | CRLF | `X-Request-Id` | RESISTIU | — | Regex fechada |
| 4 Injeção | Command injection | `app/` | RESISTIU | — | Nenhum `exec`/`shell_exec`/`system` |
| 4 Injeção | SSRF Gemini | URL do provedor | RESISTIU | — | Constante em config, não vem do pedido |
| 4 Injeção | PCRE falha aberta | guardrail/detector/redator | FALHOU→CORRIGIDO | Alto | Falha de `preg_*` rejeita ou redige o texto |
| 5 Uploads | SVG / MIME | avatar | RESISTIU | — | GD + recusa SVG |
| 5 Uploads | Disco `local` servido | `storage/{path}` | FALHOU→CORRIGIDO | Alto | `serve => false` |
| 6 DoS / ReDoS | Regex até 1000 chars | injection, redator, normalizer | RESISTIU | — | Pior caso medido **0,108 ms** |
| 6 DoS | Tamanho | intenção > 1000, >20 variables | RESISTIU / reforçado | Médio | `variables` agora `max:20` |
| 7 Corrida | Clique duplo / último admin | suíte existente | RESISTIU | — | Idempotência 5 s; último admin |
| 8 Vazamento | Erros 404/405 | páginas amigáveis | RESISTIU | — | Sem stack |
| 8 Vazamento | Cache de páginas autenticadas | `Cache-Control` | FALHOU→CORRIGIDO | Médio | `no-store, private` |
| 9 Cabeçalhos | CSP / XFO / nosniff | HTML | RESISTIU | — | `style-src 'unsafe-inline'` permanece (baixo) |
| 10 Arquivos | GET `/.env` no Apache `Laravel.test` | DocumentRoot = raiz do repo | FALHOU→CORRIGIDO | Crítico | `.htaccess` na raiz (403) + rewrite para `public/` |
| 10 Arquivos | `composer.json`, `.git/config`, `artisan`, `storage/logs/`, `vendor/`, `database/`, `tests/`, `docs/` | Apache | FALHOU→CORRIGIDO | Crítico | 403 após `.htaccess` |
| 11 LLM | Injection / redação / rate limit | gerador | RESISTIU | — | Já existente |
| 12 Dependências | `composer audit` / `npm audit` | — | RESISTIU na linha de base | — | Conferir de novo na Tarefa F |

## Segunda passada (só o que falhou)

Após as correções, as URLs no Apache `Laravel.test` passaram de 200 para **403**
(`.env`, `.git/config`, `composer.json`, `artisan`, diretórios internos).
`/login` e `/` continuam servidos via `public/`. Testes da bateria: 16 feature
+ 2 ReDoS, verdes.

## ReDoS

Entrada de até 1000 caracteres (`aaaa…`, `ignore ` repetido, `1=1 OR `,
`<script>`, PEM parcial). Nenhuma regex acima de 200 ms. Pior caso nesta
máquina: **0,108 ms** (`instruction_override` #0).

## Apache

- Vhost `Laravel.test` → DocumentRoot `C:/laragon/www/Laravel` (raiz do repo).
- Correção no repositório: `.htaccess` na raiz (`AllowOverride All` já estava
  no vhost). O DocumentRoot **correto** continua sendo `public/`.
- Corpo das respostas não foi copiado neste relatório.

## Limitações conhecidas (baixo / informativo / não verificado)

- CSP `style-src` ainda inclui `'unsafe-inline'`.
- Detector de injection é heurístico (paráfrase longa, multi-turno).
- `GET /up` sem middleware de aplicação (health do framework).
- Corrida real de dois cadastros / dois deletes de admin: coberta por testes
  de unidade/feature, não por dois processos HTTP simultâneos nesta rodada
  (NÃO VERIFICADO em paralelo real).
- Source maps do Vite em produção: `public/build` não é versionado; NÃO
  VERIFICADO um deploy de produção.
- `TRACE` no Apache: NÃO VERIFICADO.
- Pacotes abandonados do Composer/npm: NÃO VERIFICADO além do `audit`.
