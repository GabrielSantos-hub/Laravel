# Matriz de tratamento de entradas

Inventário das entradas do usuário no GUEASS (branch `hardening/seguranca`).
«Preserva o digitado» = `old()` / valor devolvido no erro, salvo segredo
redigido. Mensagens HTML = redirect + `errors` + `old()`; JSON = `422`
`{message, errors}`.

Marcas: **SEM TESTE** (nenhum teste HTTP/unitário cobre o campo);
**FRACA** (existe teste mas não cobre tipo errado, limite ou encoding).

| Rota / método | Campo e origem | Validação | Normalização / sanitização | Guardrail / redator | Escape na saída | Mensagem de erro (HTML e JSON) | Preserva o digitado | Evento de log | Teste existente |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| POST `/login` | `email` body | `required`, `email` | `TrimStrings` | — | Blade `old('email')` | Padrão Laravel / credenciais genéricas | sim (só e-mail) | `login_success` / `login_failed` / `login_throttled` | `AuthSecurityTest`, `InputSurfaceEdgeCasesTest` |
| POST `/login` | `password` body | `required` | — (não logada) | `SecurityLogger::withoutSecrets` | nunca ecoada | credenciais genéricas | não | idem (sem senha) | `AuthSecurityTest` |
| POST `/register` | `name` body | `required`, `string`, `max:255` | `trim` após validate | — | `old('name')` | Laravel + política de senha | sim | `register` | `PasswordPolicyTest`, `InputSurfaceEdgeCasesTest` |
| POST `/register` | `email` body | `required`, `email`, `max:255`, `unique` | TrimStrings | e-mail mascarado no log | `old('email')` | unique / e-mail inválido | sim | `register` | `PasswordPolicyTest` |
| POST `/register` | `password` + `password_confirmation` body | `PasswordRules::required()` (8+, letras e números) | hashed no model | nunca logada | — | política de senha | não | `register` | `PasswordPolicyTest` |
| POST `/logout` | CSRF `_token` body / header | VerifyCsrfToken | session invalidate | — | — | 419 genérica | n/a | `logout` | `AuthSecurityTest` **FRACA** (não cobre token ausente) |
| POST `/prompts/generate` | `intencao` body | `required`, `string`, min 10, max 1000 (caracteres) | trim, strip NUL, CRLF→LF; UTF-8 inválido recusado | `InputSanityGuardrail` + `PromptInjectionDetector`; depois `SensitiveDataRedactor` | Blade `{{ }}` / JSON | formato próprio (vazio/curto/longo/tipo/UTF-8); guardrail/injection = mensagem genérica | sim, exceto segredo | `guardrail_rejected` / `prompt_injection_detected` / `sensitive_data_redacted` | `InputEdgeCasesTest`, `RealWorldEntryCasesTest`, `GeneratePromptRequest` |
| POST `/prompts/generate` | `user_input` / `input_text` body (aliases) | só se `intencao` vazia; `intencao` prevalece | mesmo pipeline | mesmo | mesmo | mesmo | sim | mesmo | `PromptControllerTest`, `InputEdgeCasesTest` (aliases conflitantes) |
| POST `/prompts/generate` | `architecture_id` body | `nullable`, `integer`, `exists:architectures,id` | `''` → null | — | `old()` nos selects | «não existe» / «inválida» | sim | — | `InputEdgeCasesTest`, `PromptControllerTest` |
| POST `/prompts/generate` | `language_id` body | `nullable`, `integer`, `exists:languages,id` | `''` → null | — | `old()` | «não existe» / «inválida» | sim | — | `InputEdgeCasesTest` |
| POST `/prompts/generate` | `framework_id` body | `nullable`, `integer`, `exists:frameworks,id` | `''` → null | — | `old()` | «não existe» / «inválido» | sim | — | `InputEdgeCasesTest` |
| POST `/prompts/generate` | `variables.*` body | array; chave `^[A-Za-z_][A-Za-z0-9_]*$`; valor `string` max 2000 | trim/NUL; chave inválida = 422 | injection/XSS por valor; `redactMap` | interpolado no envelope (escapado na view) | chave inválida / longo / genérica se injection | sim | `prompt_injection_detected` / `guardrail_rejected` | `Phase2PrivacyAndInjectionTest`, `InputEdgeCasesTest` |
| POST `/prompts/generate` | `nao_salvar_historico` body | `sometimes`, `boolean` | `boolean()` | — | checkbox `old()` | Laravel boolean | sim | — | `Phase2PrivacyAndInjectionTest` |
| POST `/prompts/generate` | `template_id` body (extra) | **ignorado** (não está nas rules) | descartado | — | — | — | n/a | — | `PromptControllerTest` (`template_id` ignorado) |
| POST `/prompts/generate` | campos extras (`role`, `user_id`…) | ignorados; create só com fillable do `Prompt` | — | — | — | — | n/a | — | `InputEdgeCasesTest` |
| GET `/prompts/{prompt}` | `prompt` route param | implicit binding; dono | — | `autorizarDono` | Blade escape | 403 / 404 | n/a | `authorization_denied` | `PromptControllerTest` |
| POST `/prompts/{prompt}/feedback` | `is_useful` body | `required`, `boolean` | — | dono | JSON | «Informe se o prompt foi útil.» | n/a | — | `PromptControllerTest`, `InputSurfaceEdgeCasesTest` |
| DELETE `/prompts/{prompt}` | `prompt` route param | binding + dono | delete | — | flash | 403 / 404 | n/a | `authorization_denied` | `PromptControllerTest`, `InputSurfaceEdgeCasesTest` |
| DELETE `/prompts/historico` | nenhum ID de prompt (IDs no corpo **ignorados**) | `auth` + `password.changed` + `throttle:5,1` + CSRF | apaga só `user_id` da sessão | — | flash «N prompts removidos» / JSON `{removed,message}` | 302 login / 419 CSRF | n/a | `history_cleared` (só `removed`) | `HistoryClearTest`, `SecurityBatteryTest` |
| POST `/admin/metricas/corte` | — (sem corpo de dados) | `can:admin` + CSRF + `throttle:10,1` | grava `app_settings.metrics_reset_at`; **não** apaga prompts | — | flash + rótulo «Métricas consideradas desde» | 403 comum | n/a | `admin_metrics_reset` (audit + security) | `MetricsResetTest` |
| DELETE `/admin/metricas/corte` | — | `can:admin` + CSRF + `throttle:10,1` | remove a chave de corte | — | flash; totais voltam ao histórico completo | 403 comum | n/a | `admin_metrics_reset_cleared` | `MetricsResetTest` |
| PUT `/perfil` | `name` body | `required`, `string`, `max:255` | `trim` | — | `old('name')` | «Informe o nome.» | sim | `password_changed` se senha | `ProfileAvatarTest`, `InputSurfaceEdgeCasesTest` |
| PUT `/perfil` | `password` + confirmação body | `PasswordRules::optional()` + `current_password` | hashed | nunca logada | — | política / senha atual | não | `password_changed` | `PasswordPolicyTest` |
| PUT `/perfil` | `avatar` arquivo | `image`, `mimes:jpg,jpeg,png,webp`, `max:2048` | `AvatarSanitizer` (GD, recusa SVG) | — | URL do PNG | MIME / tamanho | n/a | `avatar_rejected` | `ProfileAvatarTest` **FRACA** no update multipart vs crop |
| POST `/perfil/avatar` | `avatar` arquivo | `UpdateAvatarRequest` | GD reprocessa | SVG recusado | JSON `avatar_url` | 422 amigável | n/a | `avatar_rejected` | `ProfileAvatarTest`, `Phase3SecurityObservabilityTest` |
| DELETE `/perfil` | `current_password` body | `required`, `current_password`; último ADM bloqueado | — | — | — | senha / último admin | não | `account_deleted` | `Phase2PrivacyAndInjectionTest`, `LastAdminProtectionTest` |
| PUT `/senha-obrigatoria` | `password` body | `PasswordRules::required()` | hashed; `must_change_password=false` | — | — | política | não | `forced_password_change` | `ForcedPasswordChangeTest` |
| PUT `/admin/users/{user}/password` | `user` route param | admin gate; senha gerada no servidor | `Str::password` até passar política | temporária só no flash | flash uma vez | 403 | n/a | `admin_password_reset` (audit) | `AdminUserTest` **FRACA** (não cobre user inválido) |
| POST/PUT `/languages` | `nome` body | `required`, `string`, `max:100`, unique | — | — | `old()` | unique / required | sim | `admin_language_*` | `Phase3SecurityObservabilityTest`, `InputSurfaceEdgeCasesTest` |
| POST/PUT `/languages` | `slug` body | `required`, `string`, `max:100`, `alpha_dash`, unique | — | — | `old()` | `alpha_dash` | sim | idem | `InputSurfaceEdgeCasesTest` |
| POST/PUT `/frameworks` | `nome`, `slug`, `language_id` body | nome/slug como acima; `language_id` integer exists | — | — | `old()` | exists / alpha_dash | sim | `admin_framework_*` | `CatalogReferentialIntegrityTest` **FRACA** (CRUD feliz; slug inválido SEM TESTE dedicado) |
| POST/PUT `/architectures` | `nome` body | `required`, `string`, `max:100`, unique no create | — | — | `old()` | unique / required | sim | `admin_architecture_*` | `InputSurfaceEdgeCasesTest` (descrição) |
| POST/PUT `/architectures` | `descricao` body | `required`, `string`, `max:5000` | — | — | `old()` | longo / required | sim | idem | `InputSurfaceEdgeCasesTest` |
| POST/PUT `/templates` | `nome` body | store `max:255` unique; update `max:150` | — | — | `old()` | unique / required | sim | `admin_template_*` | `TemplateCatalogTest` **FRACA** (max store≠update) |
| POST/PUT `/templates` | `corpo_template` body | `required`, `string`, `max:500000` | — | **sem** detector de injection no corpo | `old()` textarea | required / max | sim | idem | `TemplateCatalogTest` **FRACA** (não cobre injection no corpo) |
| POST/PUT `/templates` | `versao`, `bloco`, `is_active` body | versao max 20; bloco `in:A,B,C`; boolean | `boolean()` | — | `old()` | in / boolean | sim | idem | `TemplateCatalogTest` **FRACA** |
| POST/PUT `/templates` | `slug` | **NÃO ENCONTRADO** (templates usam `bloco`, não slug) | — | — | — | — | — | — | — |
| GET `/admin/auditoria` | `action` query | `nullable`, `string`, `max:64`, `alpha_dash` | trim | — | selected | 422 se inválido | sim | — | `Phase3SecurityObservabilityTest`, `InputSurfaceEdgeCasesTest` |
| GET `/admin/auditoria` | `from`, `to` query | `nullable`, `date`; `to` ≥ `from` | Carbon | — | value | 422 (não 500) | sim | — | `InputSurfaceEdgeCasesTest` |
| GET `/admin/auditoria` | `page` query | `nullable`, `integer`, `min:1` | paginate 20 | — | links | 422 se não inteiro | sim | — | **FRACA** (page inválida SEM TESTE dedicado) |
| GET `/api/languages/{language}/frameworks` | `language` route param | implicit binding | JSON dos frameworks | auth | JSON | 404 | n/a | — | `InputSurfaceEdgeCasesTest` |
| GET `/architectures/{id}`, `/frameworks/{id}`, `/templates/{id}` | método GET/HEAD | resource sem `show` | `FriendlyHttpRenderer` trata MethodNotAllowed como **404** | — | página 404 | 404 amigável (não lista métodos) | n/a | — | `ErrorPagesTest` |
| POST (e outros) em rota só GET (ex. `/privacidade`) | método HTTP | MethodNotAllowed | 405 sem header `Allow` | — | página 405 | «Esta ação não está disponível por este endereço.» | n/a | — | `ErrorPagesTest` |
| GET `/privacidade` | — | pública | texto lido de `config('privacy.prompt_retention_days')` | — | Blade | — | n/a | — | `Phase2PrivacyAndInjectionTest` |
| header `X-Request-Id` | header (qualquer rota) | `AssignRequestId::isValid`: `[A-Za-z0-9._-]{8,128}` | inválido → UUID novo; **não** propaga o cru | — | páginas de erro usam só `request_id` sanitizado | — | n/a | contexto `request_id` | `InputEdgeCasesTest`, `ErrorPagesTest` |
| cookie sessão | cookie | EncryptCookies, HttpOnly | — | — | — | 419 se CSRF | n/a | — | `ErrorPagesTest` (419) **FRACA** |
| query strings genéricas | não listadas | ignoradas se não validadas | — | — | — | — | — | — | **SEM TESTE** para chaves arbitrárias |

## Política de mensagens em `/prompts/generate`

- Formato (vazio, curto, longo, tipo, UTF-8, ID, variável): mensagem **própria**.
- Guardrail de sanidade, XSS/SQLi e prompt injection: **mensagem pública genérica**
  (`InputUnprocessableException::MESSAGE`); o critério/categoria vai só ao
  canal `security`.
- HTML: volta ao formulário, texto preservado (exceto segredo), `autofocus`
  no campo `intencao`, classe `is-invalid`.
- JSON: sempre `422` com `{message, errors.campo[]}`. Nunca stack trace.

Erros HTTP (`FriendlyHttpRenderer`): JSON `{message}` estável, sem stack
e sem métodos suportados. GET/HEAD em URI só de escrita = 404.
