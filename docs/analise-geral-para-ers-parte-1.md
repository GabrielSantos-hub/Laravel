# Análise geral do GUEASS para reescrita da ERS

| Campo | Valor |
| --- | --- |
| Data da análise | 2026-10-03 |
| Commit analisado | `fe59959` (`fe599596277805d3f7cb841a368f52effad26d3e`) |
| Branch | `hardening/seguranca` |
| Testes | 774 passed, 4007 assertions, 17,97 s (`php artisan test --compact` com o PHP do Laragon) |
| PHP da suíte | 8.3.30 (cli) ZTS, `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| PHP em `PATH` | 8.2.12, `C:\xampp\php\php.exe` — não foi o interpretador da suíte |

Este texto descreve somente o que o código, o esquema MySQL em uso, os locks e os comandos executados mostram. Onde o código não define o fato, a célula ou a frase diz **NÃO ENCONTRADO** ou **INCERTO**, com o motivo. Documentos em `docs/` foram tratados como hipótese e só entram quando o código confirma ou quando a divergência é o próprio achado.

Sigla usada na ERS antiga extraída de `storage/app/_ers_unzip/doc/word/document.xml`: GUEASS = Gerador de Prompts Especializado. O código não redefine a sigla; `config/app.php` usa `env('APP_NAME', 'Laravel')` e `.env.example` define `APP_NAME=Gueass`. O valor efetivo de `APP_NAME` no processo em execução **não foi lido** (arquivo `.env` não consultado).

---

> Parte 1 de 2. Continuação em `docs/analise-geral-para-ers-parte-2.md` (seções 8 a 17 e a verificação por amostragem).

## 0. RESUMO EXECUTIVO

O GUEASS é uma aplicação web em Laravel que recebe, de um usuário autenticado, um texto de intenção de software (10 a 1.000 caracteres), recusa entrada sem escopo de software, ruído de teclado, XSS/SQLi ou prompt injection, mascara segredos reconhecíveis e devolve um prompt estruturado. O prompt sai em um de dois formatos definidos em `app/Services/PromptBuilderService.php`: envelope com as seções PAPEL E CONTEXTO, TAREFA, RESTRIÇÕES NEGATIVAS, ESQUEMA DE SAÍDA e AUTO-VALIDAÇÃO, ou um envelope curto (Modo Lean) quando o guardrail classifica o pedido como curto. A escolha do template é local e automática (`app/Services/AI/TemplateSelector.php`). O histórico fica na tabela `prompts`, associado ao usuário.

**Para quem.** Visitante (catálogos e política de privacidade). Usuário com `users.role = USU` (geração, histórico, perfil). Administrador com `users.role = ADM` (`app/Models/User.php::isAdmin`). Não há outros papéis no código.

**Problema que o código trata.** Transformar um texto livre de intenção de software em um prompt com seções fixas, escolhendo um template ativo do catálogo e, se o pedido pedir só prosa, acrescentando a restrição `NO_CODE_CONSTRAINT` (`PromptBuilderService::NO_CODE_CONSTRAINT`). O código não executa o código descrito no prompt e não chama um LLM para “eliminar alucinações” como propriedade medida. Com `AI_PROVIDER=null` (padrão de `config/ai.php` e de `.env.example`), a geração é local (`app/Services/AI/NullAIProvider.php`). Com `gemini`, há uma chamada HTTP ao Google e a política é fail-closed (`app/Services/PromptGeneratorService.php::generate`).

**Atores.** Visitante; usuário USU; administrador ADM; MySQL; agendador do sistema operacional que deve chamar `schedule:run` (o repositório só declara `Schedule::command('gueass:prune-prompts')->daily()` em `routes/console.php`); provedor Gemini, somente se `AI_PROVIDER=gemini` e houver chave.

**Fluxo principal (8 passos), fiel a `PromptController::generate` e `PromptGeneratorService::generate`.**

1. O usuário autenticado, com `must_change_password = false`, envia `POST /prompts/generate`.
2. `GeneratePromptRequest` aceita aliases `user_input` e `input_text`, remove NUL, normaliza CRLF para LF, faz trim e exige UTF-8.
3. A validação exige `intencao` entre 10 e 1.000 caracteres e IDs de catálogo existentes ou vazios.
4. `InputSanityGuardrail::assertSane` recusa XSS/SQLi, prompt injection, gibberish, word salad ou texto sem marcador de software. A mensagem pública é única: `InputUnprocessableException::MESSAGE`.
5. `SensitiveDataRedactor::inspect` troca segredos por `[REDACTED:tipo]` antes do pipeline e de novo na saída.
6. O pipeline reavalia o guardrail, analisa a intenção com `NullAIProvider` (sempre, mesmo se o driver configurado for Gemini), escolhe template ativo, chama `AIProviderInterface::generateStructuredPrompt` e monta Lean ou envelope.
7. Se `nao_salvar_historico` não foi marcado, grava `prompts` (falha de insert não apaga o texto já gerado). Há cache de idempotência de 5 s por usuário e hash da intenção já redigida mais o flag de opt-out.
8. HTML volta para `GET /` com flash e `last_output` na sessão; JSON (`Accept` que `expectsJson`) responde 201 com o payload.

**Stack (versões de lock ou binário, não intervalos do composer.json).** PHP da suíte 8.3.30; Laravel framework `v12.69.2`; Vite `7.3.6`; Tailwind CSS `4.3.0`; `@tailwindcss/vite` `4.3.0`; Bootstrap `5.3.8`; Font Awesome Free `7.3.1`; Cropper.js `1.6.2`; Chart.js `4.5.1`; Pest `v3.8.7`; PHPUnit `11.5.56`; Collision `v8.9.5`; MySQL reportado por `php artisan db:show`: **8.4.3**, host `127.0.0.1`, porta **3308**, database `gueass_db`. Node `v24.14.0`, npm `11.9.0`.

**Estado atual.** `php artisan migrate:status`: 23 migrations, todas Ran (batches 1 a 5). Suíte: 774 testes, 4007 asserções, 17,97 s, exit code 0. `composer audit` (via `composer.phar` e o PHP 8.3.30; o comando `composer` não está no PATH): “No security vulnerability advisories found.” `npm audit`: 0 vulnerabilidades (164 dependências no metadata). Cobertura de código: **NÃO FEITO** — `php -m` do PHP 8.3.30 não lista `xdebug` nem `pcov`, e `phpunit.xml` não define driver de cobertura.

**Limitações que o código confirma.** Não há download Markdown/Texto. Não há relatório de histórico por período nem por “nível de complexidade”. Não há tela de papéis além de USU (cadastro) e ADM (comando ou seeder fora de production). Não há reset de senha por e-mail (a tabela `password_reset_tokens` existe e não é usada pela aplicação). A senha mínima no código é 8, não 6. A integração Gemini já existe e não está adiada no código. O Modo Lean é decidido por tamanho/tokens, e `LEAN_MAX_LINES = 25` é teto do envelope montado, não o critério de entrada. Não há meta de resolução 1024×768 no CSS. Não há hospedagem Railway no repositório.

---

## 1. STACK E AMBIENTE

### 1.1 Versões exatas

| Componente | Versão observada | Onde |
| --- | --- | --- |
| PHP da suíte e do `db:show` | 8.3.30 (cli) (built: Jan 13 2026) ZTS VC++ 2019 x64 | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -v` |
| PHP no PATH desta sessão | 8.2.12 (cli) (built: Oct 24 2023) | `C:\xampp\php\php.exe` |
| Restrição do projeto | `php: ^8.2` | `composer.json` `require.php` |
| Laravel framework | v12.69.2 | `composer.lock` pacote `laravel/framework` |
| Pest | v3.8.7 | `composer.lock` |
| PHPUnit | 11.5.56 | `composer.lock` |
| Collision | v8.9.5 | `composer.lock` |
| Vite | 7.3.6 | `package-lock.json` `node_modules/vite` |
| laravel-vite-plugin | 2.1.0 | `package-lock.json` |
| Tailwind CSS | 4.3.0 | `package-lock.json` |
| @tailwindcss/vite | 4.3.0 | `package-lock.json` |
| Bootstrap | 5.3.8 | `package-lock.json` |
| Font Awesome Free | 7.3.1 | `package-lock.json` |
| Cropper.js | 1.6.2 | `package-lock.json` |
| Chart.js | 4.5.1 | `package-lock.json` |
| @fontsource/orbitron | ^5.3.0 no `package.json`; versão exata do lock | NÃO EXTRAÍDA neste passo (o `package.json` fixa o intervalo `^5.3.0`) |
| Axios | ^1.20.0 no `package.json` | versão exata do lock: NÃO EXTRAÍDA |
| Popper | ^2.11.8 no `package.json` | versão exata do lock: NÃO EXTRAÍDA |
| MySQL | 8.4.3 | saída de `php artisan db:show` |
| Node / npm | v24.14.0 / 11.9.0 | `node -v`, `npm -v` |
| Serviço Windows | `MySQL_Laragon`, status Running, DisplayName “MySQL Laragon 8.4 (GUEASS)” | `Get-Service MySQL_Laragon` |

Módulos presentes no PHP 8.3.30 usado na suíte (saída de `php -m`): bcmath, calendar, Core, ctype, curl, date, dom, exif, fileinfo, filter, **gd**, hash, iconv, **intl**, json, libxml, mbstring, mysqli, mysqlnd, openssl, pcre, PDO, pdo_mysql, pdo_sqlite, Phar, random, readline, Reflection, session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, xsl, zlib. Não aparecem xdebug nem pcov.

O PHP 8.2.12 do PATH, ao rodar `php artisan db:show`, conectou no mesmo MySQL 8.4.3 porta 3308 e em seguida lançou `RuntimeException`: a extensão `intl` não está carregada nesse binário (`Illuminate\Support\Number`). A suíte foi executada de propósito com o PHP 8.3.30 do Laragon, que tem `gd` e `intl`.

### 1.2 Assets self-hosted

`resources/css/app.css` importa, via Vite, arquivos de `node_modules` (não CDN no CSS da aplicação):

- `bootstrap/dist/css/bootstrap.min.css`
- `@fortawesome/fontawesome-free/css/all.min.css`
- `@fontsource/orbitron/400.css`, `600.css`, `700.css`, `900.css`
- `tailwindcss/theme.css` e `tailwindcss/utilities.css`

`resources/js/app.js` importa `./bootstrap` (axios), `bootstrap` (JS) e `./ui`. `vite.config.js` publica `resources/css/app.css`, `resources/js/app.js`, `resources/js/admin-dashboard.js`, `resources/js/profile-crop.js`. `profile-crop.js` importa `cropperjs`. `admin-dashboard.js` é a entrada do gráfico (Chart.js; o import exato do Chart não foi relido linha a linha neste passo — o arquivo existe e o dashboard usa `<canvas id="grafico-templates">` e `grafico-stacks` em `resources/views/admin/dashboard.blade.php`).

Páginas de erro (`resources/views/errors/layout.blade.php`) **não** usam Vite: o CSS está no próprio Blade. Ícone: `asset('favicon.png')`. Logo da home: `asset('logo.png')` em `resources/views/prompts/index.blade.php`.

Não há tag `<script src="https://` nem `<link href="https://` nas views pesquisadas para Font Awesome, Bootstrap ou Chart. **INCERTO** se algum Blade residual aponta CDN: a busca cobriu `mailto`, Vite e imports do CSS principal, não uma varredura exaustiva de todo `https://` em Blade.

### 1.3 Banco em uso

`php artisan db:show` (sem leitura do arquivo `.env`): Connection mysql, Database `gueass_db`, Host `127.0.0.1`, Port `3308`, Username `gueass`, MySQL 8.4.3, 19 tabelas. A senha não foi impressa nem copiada. `.env.example` ainda declara `DB_PORT=3306` e `DB_USERNAME=root`. A porta efetiva desta instalação é 3308. Os serviços MYSQL95 (3306) e MySQL80 (3307) não foram consultados.

Todas as 19 tabelas são InnoDB, collation `utf8mb4_unicode_ci` (`information_schema.TABLES`).

Contagens (somente `COUNT(*)`, sem linhas de dados): languages 15, frameworks 32, architectures 16, templates 26 (bloco A ativo 7, B ativo 10, C ativo 9), users 4, prompts 3, audit_logs 7, app_settings 0. Essas contagens são o estado do banco do autor nesta data, não um requisito.

### 1.4 Variáveis de ambiente

Somente nomes, padrão não secreto e função. Valores secretos de `.env` não foram lidos. Fonte: `.env.example`, `config/*.php`.

| Nome | Padrão não secreto | Função |
| --- | --- | --- |
| `APP_NAME` | exemplo `Gueass`; default de `config/app.php` é `Laravel` | Nome exibido onde o framework usa `config('app.name')` |
| `APP_ENV` | exemplo `local`; default de config `production` | Ambiente; `production` liga HTTPS forçado, HSTS condicional, `Password::uncompromised()` e impede usuários demo no seeder |
| `APP_KEY` | vazio no exemplo | Chave de criptografia do framework. Valor não lido |
| `APP_DEBUG` | exemplo `true` | Stack trace do Laravel quando true. Páginas `errors/*` existem para HttpException mesmo assim, porque `bootstrap/app.php` renderiza `FriendlyHttpRenderer` para `HttpExceptionInterface` |
| `APP_URL` | `http://localhost` | URL base |
| `APP_LOCALE` | exemplo `pt_BR`; default de `config/app.php` é `en` | Locale. Não há pasta `lang/` no repositório |
| `APP_FALLBACK_LOCALE` | `en` | Fallback |
| `APP_FAKER_LOCALE` | `pt_BR` | Faker |
| `APP_MAINTENANCE_DRIVER` | `file` | Manutenção |
| `PHP_CLI_SERVER_WORKERS` | `4` | Workers do servidor embutido do PHP. Não é lido pelo código da aplicação |
| `BCRYPT_ROUNDS` | `12` no exemplo; testes forçam `4` em `phpunit.xml` | Custo do hash (`casts` password `hashed` em `User`) |
| `LOG_CHANNEL` | `stack` | Canal padrão |
| `LOG_STACK` | `single` | Canais do stack |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Depreciações |
| `LOG_LEVEL` | `debug` | Nível do log de aplicação |
| `DB_CONNECTION` | `mysql` | Driver. Testes forçam `sqlite` `:memory:` |
| `DB_HOST` | `127.0.0.1` | Host |
| `DB_PORT` | `3306` no exemplo; conexão observada `3308` | Porta |
| `DB_DATABASE` | `gueass_db` | Nome do schema |
| `DB_USERNAME` | `root` no exemplo; conexão observada `gueass` | Usuário do banco |
| `DB_PASSWORD` | vazio no exemplo | Senha. Não lida |
| `SESSION_DRIVER` | `database` | Sessão na tabela `sessions` |
| `SESSION_LIFETIME` | `120` (minutos) | `config/session.php` |
| `SESSION_ENCRYPT` | `true` no exemplo; default do config é `true` | Criptografa o payload da sessão |
| `SESSION_HTTP_ONLY` | default `true` | Cookie não lido por JS |
| `SESSION_SAME_SITE` | default `lax` | Atributo SameSite |
| `SESSION_SECURE_COOKIE` | exemplo `false` | Sem default booleano no config (`env` sem segundo argumento). Em HTTP local o exemplo manda `false` |
| `SESSION_PATH` | `/` | Path do cookie |
| `SESSION_DOMAIN` | `null` | Domínio |
| `TRUSTED_PROXIES` | comentado; vazio não configura | `bootstrap/app.php` só chama `trustProxies` se a string não for vazia nem `*` |
| `BROADCAST_CONNECTION` | `log` | Broadcasting. Sem uso de domínio encontrado |
| `FILESYSTEM_DISK` | `local` | Disco padrão. Avatar usa disco `public` explícito |
| `QUEUE_CONNECTION` | `database` | Fila. Nenhum job da aplicação (`ShouldQueue` / `dispatch` em `app/`: NÃO ENCONTRADO) |
| `CACHE_STORE` | `database` | Cache, inclusive idempotência e throttle |
| `REDIS_*` | host `127.0.0.1`, porta `6379` | Config padrão do skeleton. Sem uso de domínio encontrado |
| `MAIL_*` | mailer `log`, from `noreply@gueass.test` | Mail do skeleton. A aplicação não envia e-mail de reset |
| `VITE_APP_NAME` | `${APP_NAME}` | Nome no front, se o bundle ler. Uso no JS da aplicação: NÃO ENCONTRADO na leitura de `ui.js` |
| `AI_PROVIDER` | `null` (`config/ai.php` default `null`) | `null` ou `gemini` |
| `GEMINI_API_KEY` | vazio | Chave. Não lida. Vai no header `x-goog-api-key` |
| `GEMINI_MODEL` | `gemini-2.0-flash` | Modelo |
| `GEMINI_BASE_URL` | `https://generativelanguage.googleapis.com/v1beta` | Base. Não está no `.env.example`; está em `config/services.php` |
| `GEMINI_TIMEOUT` | `15` (segundos) | Timeout HTTP e teto do connect timeout (`min(5, timeout)`) |
| `GEMINI_TRIES` | `2` | Tentativas totais do `Http::retry` |
| `GEMINI_MAX_PAYLOAD_BYTES` | `65536` | Teto do JSON enviado |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | comentados | `gueass:create-admin` |
| `DEMO_ADMIN_EMAIL` | default no seeder `admin@email.com` | Só fora de production |
| `DEMO_ADMIN_PASSWORD` | vazio → senha aleatória de 16 caracteres | Não commitada |
| `DEMO_USER_EMAIL` | default `usuario@email.com` | Idem |
| `DEMO_USER_PASSWORD` | vazio → aleatória de 16 | Idem. Testes definem `AdminTest1` e `UserTest1` em `phpunit.xml` |
| `PROMPT_RETENTION_DAYS` | `90` | `config/privacy.php` |
| `SECURITY_LOG_DAYS` | `30` | Rotação de `storage/logs/security.log` |
| `SECURITY_LOG_STDERR` | `false` | Se true, o canal `security` também escreve em stderr |
| `GENERATE_IDEMPOTENCY_SECONDS` | `5` | Janela do cache de geração |

### 1.5 Comandos

Instalação declarada em `composer.json` script `setup`: `composer install`; copia `.env.example` para `.env` se não existir; `php artisan key:generate`; `php artisan migrate --force`; `npm install`; `npm run build`.

Desenvolvimento declarado no script `dev`: `php artisan serve`, `php artisan queue:listen --tries=1 --timeout=0`, `php artisan pail`, `npm run dev` (concurrently). O autor deste ambiente usa Laragon/Apache; se o vhost aponta para este diretório **não foi verificado** nesta sessão (NÃO VERIFICADO).

Testes: `php artisan test` (o script composer `test` antes executa `config:clear`). A suíte desta análise: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --compact`.

Agendamento: `routes/console.php` registra `Schedule::command('gueass:prune-prompts')->daily()`. Quem dispara é o agendador do sistema operacional com `php artisan schedule:run` a cada minuto. **NÃO ENCONTRADO** no repositório um arquivo de tarefa do Windows (`.xml` do Agendador, `.bat`). A existência da tarefa no Windows desta máquina: **NÃO VERIFICADO**.

Admin inicial: `php artisan gueass:create-admin` (`app/Console/Commands/CreateAdminCommand.php`). Cria `name = Administrador`, `role = ADM` via `forceFill` (role não está em `$fillable`).

Catálogo: `php artisan db:seed` chama `ArchitectureSeeder`, `LanguageSeeder`, `TemplateSeeder` e, fora de `production`, dois usuários demo (`database/seeders/DatabaseSeeder.php`).

Saúde: rota `GET|HEAD /up` registrada por `Application::configure()->withRouting(health: '/up')` em `bootstrap/app.php`. Corpo HTTP da rota nesta sessão: **NÃO VERIFICADO** (não houve requisição ao servidor web).

---

## 2. ATORES E PERFIS

Middleware comum das rotas web, citado em `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php::getMiddlewareGroups` (grupo `web`): `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `ValidateCsrfToken`, `SubstituteBindings`. `bootstrap/app.php` acrescenta `SecurityHeaders` ao grupo `web` e faz `prepend` global de `AssignRequestId`. `auth.session` não é ligado (`authenticateSessions()` não é chamado). Globais do framework no mesmo arquivo (`getGlobalMiddleware`): `ValidatePathEncoding`, `InvokeDeferredCallbacks`, `TrustProxies`, `HandleCors`, `PreventRequestsDuringMaintenance`, `ValidatePostSize`, `TrimStrings`, `ConvertEmptyStringsToNull`.

`Gate::define('admin', fn (User $user) => $user->isAdmin())` em `app/Providers/AppServiceProvider.php`. `isAdmin()` é `role === 'ADM'`.

### 2.1 Visitante (não autenticado)

Pode:

- `GET /login` (`guest`) — formulário de entrar e de cadastrar (`resources/views/auth/login.blade.php`).
- `POST /login` (`guest`, `throttle:5,1`, `throttle:login-email-ip`).
- `POST /register` (`guest`, `throttle:10,1`). Não existe `GET /register`.
- `GET /languages`, `/frameworks`, `/architectures`, `/templates` — sem `auth`.
- `GET /privacidade` — sem `auth`.

Não pode: gerar prompt, ver histórico, perfil, rotas `auth` ou `can:admin`. Usuário já autenticado que abre `/login` é enviado para `/` (`bootstrap/app.php` `redirectUsersTo('/')`).

### 2.2 Usuário USU

Papel gravado no cadastro: a coluna `role` tem default `USU` (`users.role`, schema). `User::$fillable` não inclui `role` nem `must_change_password`, então o `create` do registro não promove ADM.

Pode, com `auth` e, nas rotas de negócio, `password.changed`:

- `POST /logout` (só `auth`; permitido mesmo com senha temporária).
- `GET/PUT /senha-obrigatoria` se `must_change_password` (a tela em si não usa o middleware `password.changed`).
- `GET /` gerar e ver o último output na sessão; `POST /prompts/generate` (`throttle:10,1`).
- `GET /prompts/{prompt}` só se `user_id` do prompt é o dele.
- `POST /prompts/{prompt}/feedback` (`throttle:20,1`), mesmo critério de dono.
- `DELETE /prompts/{prompt}` (`throttle:20,1`), mesmo critério.
- `DELETE /prompts/historico` (`throttle:5,1`) apaga só `where user_id = Auth::id()`.
- `GET/PUT /perfil` (`PUT` throttle `20,1`), `POST /perfil/avatar` (`10,1`), `DELETE /perfil` (`5,1`).
- `GET /api/languages/{language}/frameworks` (JSON dos frameworks da linguagem).

Não pode: `/admin/*`, CRUD de catálogo além do `index` público, redefinir senha de outro usuário. Prompt de outro usuário: `PromptController::autorizarDono` chama `abort(403, "Você só pode {$acao} prompts do seu próprio histórico.")`. O renderer de `bootstrap/app.php` substitui **qualquer** `HttpException` pela página `errors/403`, cuja mensagem fixa é “Você não tem permissão para acessar esta área. Se acredita que isso é um engano, entre em contato com um administrador.” A frase do `abort` não é a mensagem da view. JSON da mesma exceção usa `FriendlyHttpRenderer::message(403)` = “Você não tem permissão para acessar esta área.”

Com `must_change_password` verdadeiro, `EnsurePasswordIsChanged` redireciona HTML para `password.forced.edit` e, em pedido que `expectsJson`, `abort(403, 'É necessário alterar a senha temporária.')`. Esse `abort` também cai no renderer genérico de 403 (a frase da exceção não é a da view). Teste: `tests/Feature/ForcedPasswordChangeTest.php::test_usuario_com_senha_temporaria_e_redirecionado_ate_trocar` espera redirect no HTML e `assertForbidden()` no JSON de geração.

### 2.3 Administrador ADM

Tudo do USU, mais o grupo `auth` + `password.changed` + `can:admin`:

- `GET /admin` redireciona para `admin.dashboard` (closure em `routes/web.php`).
- `GET /admin/dashboard`, `POST /admin/metricas/corte` (`throttle:10,1`), `DELETE /admin/metricas/corte` (`throttle:10,1`).
- `GET /admin/auditoria`, `GET /admin/users`, `PUT /admin/users/{user}/password` (`throttle:10,1`).
- `languages` resource exceto `index`, `frameworks` exceto `index` e `show`, `architectures` exceto `index` e `show`, `templates` exceto `index` e `show`, todos com `throttle:20,1`.

Não há rota para alterar `role` pela UI. Promover ADM: `gueass:create-admin` ou seeder demo. Rebaixar o último ADM dispara `CannotRemoveLastAdminException` no `updating` do model.

`Gate::after` em `AppServiceProvider` registra `authorization_denied` quando o gate retorna false. Isso ocorre além do `authorization_denied` de `autorizarDono`.

### 2.4 Sistemas externos

| Ator | O que faz | O que não faz |
| --- | --- | --- |
| MySQL 8.4.3 em 3308 | Persiste usuários, catálogo, prompts, sessão, cache, auditoria | Não é chamado pelos testes (sqlite memória, `phpunit.xml`) |
| Provedor `null` | `NullAIProvider::generateStructuredPrompt` devolve JSON `valido` sem rede | Não é “degradação” se o Gemini falhar |
| Provedor `gemini` | `POST /models/{model}:generateContent` com header `x-goog-api-key` | Não escolhe o template: `AIServiceProvider` instancia `TemplateSelector` só com logger, sem o provedor (`app/Providers/AIServiceProvider.php`) |
| Agendador | Deve executar `schedule:run` para o prune diário | O repositório não contém a tarefa do Windows |

---

## 3. CASOS DE USO

Mensagens HTTP genéricas de `app/Exceptions/FriendlyHttpRenderer.php::message` (JSON) e, quando a view existe, o texto da view (HTML). Validação de formulário em HTML é redirect back do Laravel (não passa por `FriendlyHttpRenderer`, porque `ValidationException` não é `HttpExceptionInterface`). CSRF inválido é `TokenMismatchException` (419) e cai no renderer.

| Status | Mensagem JSON (`FriendlyHttpRenderer::message`) | Título / mensagem HTML da view |
| --- | --- | --- |
| 400 | O pedido enviado não pôde ser entendido. | Pedido inválido / a mesma frase (`errors/400.blade.php`) |
| 401 | Você precisa entrar para continuar. | Entrada necessária / a mesma frase |
| 403 | Você não tem permissão para acessar esta área. | Acesso negado / essa frase mais “Se acredita que isso é um engano, entre em contato com um administrador.” |
| 404 | O endereço que você tentou abrir não existe ou foi movido. | Página não encontrada / a frase JSON mais “Confira o link ou volte para o GUEASS.” |
| 405 | Esta ação não está disponível por este endereço. | Ação indisponível / a mesma frase |
| 419 | A página expirou. Recarregue e tente de novo. | Sessão expirada / a mesma frase |
| 422 | Os dados enviados não puderam ser processados. | Dados não processados / a mesma frase. Validação de campo usa as mensagens do FormRequest, não esta |
| 429 | Aguarde um momento e tente novamente. | Muitas tentativas / a mesma frase |
| 500 | Não foi possível concluir esta ação agora. Tente novamente em instantes. | Algo deu errado / a frase mais “Nenhum detalhe interno do servidor é exibido aqui.” |
| 503 | O serviço está temporariamente indisponível. Tente de novo em instantes. | Serviço indisponível / a mesma frase |

`GET` ou `HEAD` em rota que só aceita outro método vira **404** (`FriendlyHttpRenderer::statusFor`). Outros métodos incompatíveis viram **405**. O header `Allow` é removido (`bootstrap/app.php` e `FriendlyHttpRenderer::withoutAllow`).

### UC01 Cadastrar-se

| Campo | Conteúdo |
| --- | --- |
| ID | UC01 |
| Nome | Cadastrar-se |
| Ator | Visitante |
| Objetivo | Criar conta USU e entrar na sessão |
| Pré-condições | Não autenticado (`guest`) |
| Pós-condições | Linha em `users` com `role` default `USU`, senha hasheada, sessão autenticada, redirect `GET /` |
| Fluxo principal | 1. Abre `GET /login`. 2. Preenche nome, e-mail e senha com confirmação na aba de cadastro. 3. `POST /register`. 4. `AuthController::register` valida e cria o usuário. 5. `Auth::login`. 6. Log `register`. 7. Redirect `/`. |
| Fluxos alternativos | Nome sofre `trim` antes do create |
| Exceções | Nome ausente, e-mail inválido, e-mail duplicado (`unique:users`), senha fora da política ou sem `confirmed`: 422 de validação (redirect back no HTML). Mensagens de senha: **não há arquivos em `lang/`**; o texto exato é o default do Laravel para `Password::min(8)->letters()->numbers()` e para `confirmed`. NÃO CITADO literal no código da aplicação, exceto a mensagem do seeder: “A senha do {label} não atende à política (mínimo 8 caracteres, letras e números).” Throttle `10,1`: HTTP 429, mensagem da tabela acima, e o log `login_throttled` **não** dispara no cadastro (o `reportable` só olha a rota `login`) |
| Regras | RN senha; RN papel não preenchível; throttle de cadastro |
| Telas e rotas | `resources/views/auth/login.blade.php`; `POST /register` `register` |
| Log | `register` com e-mail mascarado e `user_id` |
| Testes | `tests/Feature/PasswordPolicyTest.php`, `tests/Feature/AuthSecurityTest.php` |

### UC02 Autenticar

| Campo | Conteúdo |
| --- | --- |
| ID | UC02 |
| Nome | Autenticar (login e bloqueio por tentativas) |
| Ator | Visitante |
| Objetivo | Abrir sessão |
| Pré-condições | Conta existente |
| Pós-condições | Sessão regenerada. ADM vai para `/admin` (redirect `intended`). Demais vão para `/` |
| Fluxo principal | 1. `GET /login`. 2. `POST /login` com e-mail e senha. 3. `Auth::attempt`. 4. `session()->regenerate()`. 5. Log `login_success` com e-mail (mascarado no logger). 6. Redirect conforme `isAdmin()` |
| Alternativos | `redirect()->intended` respeita URL pretendida |
| Exceções | Credencial inválida: redirect back, erro no campo `email`: “As credenciais fornecidas não coincidem com os nossos registros.” (`AuthController::login`). Log `login_failed`. E-mail ausente ou não-e-mail: validação 422. Dois limiters no POST: `throttle:5,1` (chave padrão do framework por IP, 5 por minuto) e `login-email-ip` (`Limit::perMinute(5)->by(email\|ip)` em `AppServiceProvider`). Estouro: HTTP 429 e, se a rota for login, log `login_throttled` com `status` 429. Não há bloqueio permanente de conta nem coluna de tentativas |
| Regras | RN throttle de login |
| Telas e rotas | `auth/login.blade.php`; `GET /login`, `POST /login` |
| Log | `login_success`, `login_failed`, `login_throttled` |
| Testes | `tests/Feature/AuthSecurityTest.php`, `tests/Feature/RouteThrottleTest.php` |

### UC03 Sair

| Campo | Conteúdo |
| --- | --- |
| ID | UC03 |
| Nome | Sair |
| Ator | USU ou ADM autenticado |
| Objetivo | Encerrar a sessão |
| Pré-condições | `auth`. Funciona com senha temporária pendente |
| Pós-condições | `Auth::logout`, sessão invalidada, token CSRF regenerado, redirect `/login` |
| Fluxo | 1. `POST /logout`. 2. Log `logout` antes do logout. 3. Invalida sessão. 4. Redirect `/login` |
| Alternativos | NÃO ENCONTRADO |
| Exceções | Sem sessão: middleware `auth` redireciona ao login (HTML) ou 401 JSON do framework. Pedido sem CSRF: 419 |
| Regras | Nenhuma de domínio além da sessão |
| Telas e rotas | Botão no layout; `POST /logout` |
| Log | `logout` |
| Testes | `ForcedPasswordChangeTest` cobre `POST /logout` com senha temporária (`assertRedirect('/login')`) |

### UC04 Trocar senha obrigatória

| Campo | Conteúdo |
| --- | --- |
| ID | UC04 |
| Nome | Trocar senha obrigatória |
| Ator | Usuário com `must_change_password = true` |
| Objetivo | Substituir a senha temporária e liberar o restante do sistema |
| Pré-condições | Autenticado; flag verdadeira (gravada por `AdminUserController::resetPassword`) |
| Pós-condições | Nova senha hasheada, flag `false`, redirect `home` com flash “Senha atualizada. Você já pode usar o sistema.” |
| Fluxo | 1. Qualquer rota com `password.changed` redireciona para `GET /senha-obrigatoria`. 2. Informa senha e confirmação. 3. `PUT /senha-obrigatoria` (`throttle:10,1`). 4. `ProfileController::updateForcedPassword` grava via `forceFill`. 5. Log `forced_password_change` |
| Alternativos | `POST /logout` continua disponível |
| Exceções | Senha fraca: redirect de volta com erro no campo `password` (teste `test_troca_obrigatoria_recusa_senha_fraca`). JSON para rotas bloqueadas: 403 genérico (ver ator USU) |
| Regras | RN senha; RN senha temporária |
| Telas e rotas | `resources/views/profile/forced-password.blade.php`; `password.forced.edit`, `password.forced.update` |
| Log | `forced_password_change` (sem a senha) |
| Testes | `tests/Feature/ForcedPasswordChangeTest.php` |

### UC05 Gerar e sanitizar prompt

| Campo | Conteúdo |
| --- | --- |
| ID | UC05 |
| Nome | Gerar e sanitizar prompt |
| Ator | USU ou ADM com senha já trocada |
| Objetivo | Obter o prompt estruturado e, por padrão, gravá-lo |
| Pré-condições | `auth` + `password.changed`. Catálogo com ao menos um template utilizável para não cair em `NoCompatibleTemplateException` |
| Pós-condições | Texto na sessão (`last_output`, `selected_template_id`, opcional `last_prompt_id`) ou JSON 201. Se não houve opt-out e o insert funcionou: linha em `prompts` |
| Fluxo | Ver seção 4. Resumo: validar → guardrail → redigir → idempotência → `PromptPipelineService::generate` → redigir saída → resolver IDs → gravar ou não → responder |
| Alternativos | Opt-out `nao_salvar_historico`: não insere; flash “Prompt gerado. Ele não foi salvo no histórico.” Formulário pode deixar arquitetura, linguagem e framework vazios (“Deixar a IA deduzir do texto…”). A dedução persistida é `CatalogHintResolver`, não o provedor Gemini. Campos `variables` existem na request e não existem no formulário HTML (NÃO ENCONTRADO `name="variables"` nas views) |
| Exceções | Guardrail: 422 no campo `intencao` com “A instrução fornecida parece inválida ou desconexa. Por favor, descreva uma necessidade clara.” UTF-8 inválido: “O texto contém caracteres inválidos.” Abaixo de 10: “Descreva sua intenção com mais detalhes: são necessários ao menos :min caracteres.” Acima de 1000: “Sua descrição é longa demais: o limite é de :max caracteres.” Vazio: “Descreva o que você precisa gerar.” ID inexistente: “A arquitetura/linguagem/framework selecionada/o não existe.” (mensagens em `GeneratePromptRequest::messages`). Provedor falhou: “O provedor de IA demorou demais para responder. Tente novamente.” se a causa parece timeout; senão `InvalidIntentException::unclear` com `PromptGeneratorService::UNCLEAR_MESSAGE` se o motivo vier vazio. JSON inválido do provedor também vira essa mensagem unclear. Sem template: “Nenhum template compatível foi encontrado para essa descrição. Detalhe melhor a linguagem, o framework ou a arquitetura desejada.” Exceção genérica: “Não foi possível processar a solicitação.” HTTP 500 se `expectsJson`, senão back com esse erro. Throttle geração `10,1`: 429. Falha ao gravar: HTTP de sucesso com flash “Prompt gerado, mas não foi possível salvar no histórico.” e log `prompt_persist_failed` |
| Regras | RN limites, guardrail, lean, NO_CODE, template, redator, idempotência, opt-out |
| Telas e rotas | `resources/views/prompts/index.blade.php`; `POST /prompts/generate`; `GET /` |
| Log | `guardrail_rejected` ou `prompt_injection_detected` na request; `sensitive_data_redacted` no controller; `provider_timeout` ou `provider_error` no gerador; `prompt_persist_failed` se o insert falha. Não há evento “prompt_generated” |
| Testes | `tests/Feature/PromptControllerTest.php` (44), `tests/Feature/PromptPipelineTest.php`, `tests/Unit/Services/PromptGeneratorServiceTest.php`, `tests/Feature/InputSanityGuardrailTest.php`, `tests/Feature/IntentValidationTest.php` (70), `tests/Feature/Phase2PrivacyAndInjectionTest.php` |

### UC06 Consultar histórico e ver detalhe

| Campo | Conteúdo |
| --- | --- |
| ID | UC06 |
| Nome | Consultar histórico e ver detalhe |
| Ator | Dono do prompt |
| Objetivo | Reabrir um prompt salvo |
| Pré-condições | Linha em `prompts` com `user_id` do autenticado |
| Pós-condições | Tela de detalhe com template, arquitetura, linguagem e framework carregados |
| Fluxo | 1. O layout lista até 30 prompts do usuário (`AppServiceProvider` view composer de `layout`, `latest()->limit(30)`). 2. `GET /prompts/{prompt}`. 3. `autorizarDono(..., 'visualizar')`. 4. View `prompts/show` |
| Alternativos | Lista vazia no sidebar: o JS de limpeza cria o texto “Nenhum prompt salvo ainda.” (`resources/js/ui.js`). O estado vazio inicial do Blade não foi relido linha a linha; o texto acima é o do JS após limpar |
| Exceções | ID de outro usuário ou `user_id` nulo: 403 genérico (view) / JSON genérico. ID inexistente: 404 (`findOrFail` no destroy; no `show` o route-model binding gera `ModelNotFoundException`, que para HTML não é capturado pelo renderer customizado antes do default do Laravel — **INCERTO** se a página final é `errors/404` ou a 404 padrão do Laravel, porque `ModelNotFoundException` não é `HttpExceptionInterface` no `render` de `bootstrap/app.php`; o framework converte para 404 antes ou depois conforme a ordem interna. Os testes de páginas de erro existem em `tests/Feature/ErrorPagesTest.php` (26 testes) e são a evidência de que 404 de rota é a view própria. O caso model binding: NÃO VERIFICADO linha do teste neste texto) |
| Regras | Dono; lista limitada a 30 no menu, não na existência dos registros |
| Telas e rotas | `layout.blade.php`, `prompts/show.blade.php`; `prompts.show` |
| Log | `authorization_denied` com `action=visualizar` se o dono não bate |
| Testes | `tests/Feature/PromptControllerTest.php` |

### UC07 Avaliar prompt

| Campo | Conteúdo |
| --- | --- |
| ID | UC07 |
| Nome | Avaliar prompt (útil / não útil) |
| Ator | Dono |
| Objetivo | Gravar `prompts.is_useful` |
| Pré-condições | Prompt do usuário. Avaliação anterior pode ser sobrescrita (`update`) |
| Pós-condições | `is_useful` true ou false. Três estados existem: null (não avaliado), true, false (`Prompt` casts e comentário do model) |
| Fluxo | 1. Botões em `partials/prompt-feedback.blade.php`. 2. `fetch` `POST /prompts/{prompt}/feedback` com JSON `is_useful`. 3. `PromptController::feedback` valida boolean obrigatório. 4. Update. 5. JSON `{prompt_id, is_useful}` |
| Alternativos | Pode votar de novo |
| Exceções | Sem o campo: “Informe se o prompt foi útil.” Não-dono: 403. Throttle `20,1`: 429. Falha de rede no JS: texto “Não foi possível registrar sua avaliação.” Sucesso no JS: “Obrigado pelo retorno!” |
| Regras | Só o dono; métricas usam true/false e ignoram null no índice |
| Telas e rotas | Feedback na home (se `last_prompt_id`) e no detalhe; `prompts.feedback` |
| Log | Só `authorization_denied` no caso negado. Voto aceito não tem evento de segurança |
| Testes | `PromptControllerTest`, `AdminDashboardTest` (o índice de satisfação) |

### UC08 Copiar prompt

| Campo | Conteúdo |
| --- | --- |
| ID | UC08 |
| Nome | Copiar prompt |
| Ator | USU/ADM na própria tela |
| Objetivo | Colocar o texto no clipboard do navegador |
| Pré-condições | Há texto no textarea |
| Pós-condições | Nenhuma no servidor |
| Fluxo home | Botão `#btn-copy-output` (`aria-label` “Copiar prompt gerado”) faz `select()` e `document.execCommand('copy')` (`prompts/index.blade.php`) |
| Fluxo detalhe | `#copy-all` usa `navigator.clipboard.writeText` (`prompts/show.blade.php`) |
| Alternativos | NÃO ENCONTRADO download `.md` ou `.txt` |
| Exceções | Sem handler de erro se o clipboard falhar |
| Regras | Nenhuma de servidor |
| Telas e rotas | Home e detalhe. Sem rota |
| Log | Nenhum |
| Testes | NÃO ENCONTRADO teste de clipboard (é JS do Blade) |

### UC09 Limpar meu histórico

| Campo | Conteúdo |
| --- | --- |
| ID | UC09 |
| Nome | Limpar meu histórico |
| Ator | USU/ADM |
| Objetivo | Apagar todos os prompts daquele `user_id` |
| Pré-condições | Autenticado, senha ok |
| Pós-condições | `DELETE` em `prompts` onde `user_id` é o autenticado. IDs no corpo são ignorados (`PromptController::clearHistory`) |
| Fluxo | 1. Botão “Limpar meu histórico” abre modal. 2. `DELETE /prompts/historico` (o JS envia POST com method spoofing via `FormData`, header `Accept: application/json`). 3. Conta, apaga, log `history_cleared` com `removed`. 4. JSON `{removed, message}` ou redirect com flash |
| Alternativos | Se o `fetch` falha, o JS faz `form.submit()` (navegação normal) |
| Exceções | Throttle `5,1`: 429. Mensagem: “1 prompt removido” ou “{n} prompts removidos” |
| Regras | Escopo é o usuário, não um ID enviado |
| Telas e rotas | `layout.blade.php` modal; `prompts.history.clear` |
| Log | `history_cleared` |
| Testes | `tests/Feature/HistoryClearTest.php` (6) |

### UC10 Editar perfil e foto

| Campo | Conteúdo |
| --- | --- |
| ID | UC10 |
| Nome | Editar perfil e foto |
| Ator | USU/ADM |
| Objetivo | Alterar nome, senha opcional e avatar |
| Pré-condições | Autenticado |
| Pós-condições | `users.name` atualizado; senha só se o campo veio preenchido; avatar em `storage` disco `public`, caminho `avatars/{id}-{uuid}.png` |
| Fluxo perfil | 1. `GET /perfil`. 2. `PUT /perfil` com nome obrigatório (max 255, trim). 3. Senha opcional exige `current_password` (`required_with:password`) e a política. 4. Flash “Perfil atualizado.” |
| Fluxo foto | 1. Cropper.js com `aspectRatio: 1` (`resources/js/profile-crop.js`). 2. `POST /perfil/avatar`. 3. `UpdateAvatarRequest`: image, mimes jpg/jpeg/png/webp, max 2048 (kilobytes; mensagem “A foto pode ter no máximo 2 MB.”). 4. `AvatarSanitizer::sanitize` recusa SVG, confere MIME real jpeg/png/webp, reprocessa com GD, lado máximo 512 px, grava PNG. 5. JSON `{avatar_url, message: "Foto de perfil atualizada."}` |
| Alternativos | Avatar também pode ir no `PUT /perfil` (`UpdateProfileRequest` tem regra de avatar). O crop usa a rota dedicada |
| Exceções | Senha atual errada: regra `current_password` (mensagem default do Laravel, sem `lang/`). Arquivo inválido na validação: mensagens de `UpdateAvatarRequest`. Conteúdo rejeitado pelo sanitizer: “A imagem enviada não pôde ser aceita.” HTTP 422 no JSON do avatar; no PUT, back com erro. Log `avatar_rejected` com `reason` `content` ou, se a extensão/MIME contém “svg”, `reason` `svg` (o log de svg ocorre no validator mesmo que a regra `mimes` já falhe). Throttle perfil 20/min, avatar 10/min |
| Regras | RN senha, RN avatar |
| Telas e rotas | `profile/edit.blade.php`; `profile.update`, `profile.avatar` |
| Log | `password_changed` se trocou senha; `avatar_rejected` se o sanitizer ou a marca svg disparar |
| Testes | `tests/Feature/ProfileAvatarTest.php` (8), `tests/Unit/Services/AvatarSanitizerTest.php`, `tests/Feature/PasswordPolicyTest.php` |

### UC11 Excluir minha conta

| Campo | Conteúdo |
| --- | --- |
| ID | UC11 |
| Nome | Excluir minha conta |
| Ator | USU ou ADM que não seja o último administrador |
| Objetivo | Apagar cadastro, avatar e prompts |
| Pré-condições | Senha atual correta |
| Pós-condições | Arquivo de avatar removido do disco `public`; `prompts()` deleted; logout; sessão invalidada; usuário deleted. Redirect login com “Sua conta e todos os seus dados foram excluídos.” FK `prompts.user_id` também é `ON DELETE CASCADE` |
| Fluxo | 1. `DELETE /perfil` com `current_password`. 2. `DeleteAccountRequest` recusa se `isLastAdmin()`. 3. `ProfileController::destroy` |
| Alternativos | NÃO ENCONTRADO exclusão administrativa de usuário (não há `AdminUserController::destroy`) |
| Exceções | Senha ausente: “Confirme a senha para excluir a conta.” Senha errada: “A senha informada não confere.” Último ADM: “Não é possível excluir a conta: você é o único administrador.” O model ainda lança `CannotRemoveLastAdminException::MESSAGE` = “Não é possível excluir ou rebaixar o último administrador.” se a exclusão passar da request. Throttle `5,1` |
| Regras | RN último administrador |
| Telas e rotas | Perfil; `profile.destroy` |
| Log | `account_deleted` com `user_id` e e-mail mascarado, depois do delete |
| Testes | `tests/Feature/LastAdminProtectionTest.php` (5) |

### UC12 Consultar catálogos (público)

| Campo | Conteúdo |
| --- | --- |
| ID | UC12 |
| Nome | Consultar catálogos |
| Ator | Qualquer visitante ou autenticado |
| Objetivo | Ver linguagens, frameworks, arquiteturas e templates |
| Pré-condições | Nenhuma de autenticação |
| Pós-condições | Somente leitura |
| Fluxo | `GET` dos quatro `index`. Templates agrupados por bloco A/B/C (`TemplateController::index`) |
| Alternativos | `GET /languages/{language}` é `show` e está **fora** do grupo admin: a rota resource exceto index fica no grupo admin, mas `show` de language está no resource admin. Conferido em `routes/web.php`: `Route::resource('languages', ...)->except(['index'])` está dentro de `can:admin`. Portanto `GET /languages/{id}` exige ADM. `frameworks` e `architectures` não têm `show` (`except index, show`). `templates` não têm `show` |
| Exceções | Visitante em rota de escrita do catálogo: redirect login |
| Regras | Index público; mutação só ADM |
| Telas e rotas | `languages/index`, `frameworks/index`, `architectures/index`, `templates/index` |
| Log | Nenhum na leitura |
| Testes | `tests/Feature/HomeRouteTest.php`, `tests/Feature/TemplateCatalogTest.php`, `tests/Feature/CatalogPopulationTest.php` |

### UC13 Gerenciar linguagens, frameworks, arquiteturas e templates

| Campo | Conteúdo |
| --- | --- |
| ID | UC13 |
| Nome | Gerenciar catálogos (admin) |
| Ator | ADM |
| Objetivo | CRUD do catálogo |
| Pré-condições | `can:admin` e senha trocada |
| Pós-condições | Linha criada/alterada/excluída; pivôs de template acompanham o delete em cascata no banco |
| Fluxo linguagem | Validação `nome` e `slug` max 100, slug `alpha_dash`, únicos. Store/update/destroy em `LanguageController`. Destroy recusa se existem frameworks (“Não é possível excluir uma linguagem vinculada a um framework.”) ou templates (“Não é possível excluir uma linguagem vinculada a um template.”). Sucesso: “Linguagem salva com sucesso!”, “Linguagem atualizada!”, “Linguagem removida!” |
| Fluxo framework | `nome`, `slug`, `language_id` exists. Mensagens de sucesso “Framework cadastrado com sucesso!”, “Framework atualizado com sucesso!”, “Framework excluído com sucesso!”. Destroy não consulta pivô no PHP; a FK `framework_template` é ON DELETE CASCADE e `prompts.framework_id` é ON DELETE SET NULL |
| Fluxo arquitetura | `nome` max 100 unique na validação (não há UNIQUE no banco para `nome`), `descricao` max 5000. Destroy recusa se há templates vinculados. `prompts.architecture_id` ON DELETE SET NULL |
| Fluxo template | `StoreTemplateRequest`: `nome` max **255** unique, `corpo_template` max 500000, `versao` max 20, `bloco` in A,B,C, `is_active` boolean. A coluna `templates.nome` é `varchar(150)`. `UpdateTemplateRequest` limita `nome` a 150. Criar nome entre 151 e 255 caracteres passa na FormRequest e pode falhar no MySQL. `is_active` ausente vira false (`$request->boolean`). `versao` vazia vira `'1'`. Não há campos de slug, intent_type, is_generic nem sync de pivôs nesses FormRequests. **INCERTO** se a tela de create envia language/framework/architecture: as rules não os aceitam, então não são persistidos por este controller |
| Alternativos | Template inativo (`is_active` false) sai da seleção (`TemplateSelector` filtra `where is_active true`) mas continua no index |
| Exceções | Erro de banco na linguagem: “Erro interno ao salvar a linguagem.” / “Erro interno ao atualizar.” / “Não foi possível excluir a linguagem.” Framework: “Erro interno ao salvar o framework no banco de dados.” etc. Arquitetura: “Erro ao salvar arquitetura.” Throttle `20,1` nas rotas de escrita |
| Regras | RN template inativo; RN nullOnDelete; RN bloqueio de exclusão de linguagem/arquitetura com vínculo |
| Telas e rotas | create/edit de cada recurso; ver mapa de rotas |
| Log | `admin_language_*`, `admin_framework_*`, `admin_architecture_*`, `admin_template_*` via `AdminAuditor::record` (audit_logs e canal security) |
| Testes | `CatalogReferentialIntegrityTest`, `TemplateCatalogTest`, `CatalogPopulationTest` |

### UC14 Gerenciar usuários e redefinir senha

| Campo | Conteúdo |
| --- | --- |
| ID | UC14 |
| Nome | Gerenciar usuários e redefinir senha |
| Ator | ADM |
| Objetivo | Listar usuários e impor senha temporária |
| Pré-condições | Admin |
| Pós-condições | `password` nova, `must_change_password` true. A senha em claro vai só para a sessão flash `temporary_password` |
| Fluxo | 1. `GET /admin/users` lista `User::orderBy('name')`. 2. `PUT /admin/users/{user}/password`. 3. `Str::password(16)` em loop até passar `PasswordRules::required()`. 4. `forceFill`. 5. `AdminAuditor::record('admin_password_reset', $user)` — o metadata enviado é só `target_user_id`; a senha não entra no record. 6. Flash “Senha temporária gerada para {nome}. Ela será exibida uma única vez.” |
| Alternativos | Não há criar/editar/excluir usuário nesta tela. Não há troca de `role` |
| Exceções | Throttle `10,1`. Não há confirmação de senha digitada pelo admin: a senha é gerada no servidor |
| Regras | RN senha temporária; RN política |
| Telas e rotas | `admin/users/index.blade.php`; `admin.users.password` |
| Log | `admin_password_reset` |
| Testes | `tests/Feature/AdminUserTest.php`, `tests/Feature/PasswordResetTest.php` |

### UC15 Visualizar métricas

| Campo | Conteúdo |
| --- | --- |
| ID | UC15 |
| Nome | Visualizar métricas |
| Ator | ADM |
| Objetivo | Ver agregados de `prompts` |
| Pré-condições | Admin |
| Pós-condições | Nenhuma escrita |
| Fluxo | `GET /admin/dashboard` chama `PromptMetricsService::summary`. Cartões: total de prompts, índice de satisfação (1 casa, porcentagem de úteis sobre avaliados, ou “—” se zero avaliações), stack mais selecionada, e o quarto cartão (template mais usado) está no restante da view. Gráficos: úteis vs não úteis, top templates (limite 5), top stacks (limite 8, mistura linguagem, framework e arquitetura). Se existe `app_settings.metrics_reset_at`, a leitura filtra `created_at >=` esse instante. O rótulo usa timezone de `config/app.php`, que é `UTC` |
| Alternativos | Sem corte: considera todas as linhas |
| Exceções | Não-admin: 403 |
| Regras | RN data de corte; a satisfação ignora `is_useful` null |
| Telas e rotas | `admin/dashboard.blade.php` |
| Log | Nenhum na leitura |
| Testes | `tests/Feature/AdminDashboardTest.php` (10), `tests/Feature/MetricsResetTest.php` |

### UC16 Zerar métricas e restaurar todo o histórico

| Campo | Conteúdo |
| --- | --- |
| ID | UC16 |
| Nome | Zerar métricas e restaurar a leitura |
| Ator | ADM |
| Objetivo | Mudar a data de corte sem apagar prompts |
| Pré-condições | Admin |
| Pós-condições | `POST` grava `app_settings.key = metrics_reset_at`. `DELETE` remove a chave (`AppSetting::putValue` com null dá `delete`) |
| Fluxo zerar | Modal, `POST /admin/metricas/corte`, flash “Métricas consideradas desde {d/m/Y H:i}.” |
| Fluxo restaurar | Botão “Considerar todo o histórico” só aparece se há corte. `DELETE` mesma URI. Flash “Métricas voltam a considerar todo o histórico.” |
| Alternativos | NÃO ENCONTRADO purge de linhas de prompt nesta ação |
| Exceções | Throttle `10,1` em ambos |
| Regras | Corte é filtro de leitura |
| Telas e rotas | Dashboard; `admin.metrics.reset`, `admin.metrics.reset.clear` |
| Log | `admin_metrics_reset` (metadata `metrics_reset_at`) e `admin_metrics_reset_cleared` |
| Testes | `tests/Feature/MetricsResetTest.php` (5) |

### UC17 Consultar auditoria

| Campo | Conteúdo |
| --- | --- |
| ID | UC17 |
| Nome | Consultar auditoria |
| Ator | ADM |
| Objetivo | Listar `audit_logs` |
| Pré-condições | Admin |
| Pós-condições | Leitura. O model impede `updating` e `deleting` com `RuntimeException` “Registros de auditoria não podem ser alterados/excluídos.” |
| Fluxo | `GET /admin/auditoria` com filtros opcionais `action` (max 64, `alpha_dash`), `from`, `to` (`after_or_equal:from`). Paginado 20, `orderByDesc('id')` |
| Alternativos | Sem filtro: todos |
| Exceções | Data inválida: validação 422 |
| Regras | Imutabilidade no model, não por trigger de banco (NÃO ENCONTRADO trigger) |
| Telas e rotas | `admin/audit/index.blade.php` |
| Log | A consulta não gera novo audit |
| Testes | `tests/Feature/Phase3SecurityObservabilityTest.php` (parte), `SecurityAuditTest` |

Ações que `AdminAuditor::record` grava (e que portanto podem aparecer em `audit_logs.action`): `admin_password_reset`, `admin_metrics_reset`, `admin_metrics_reset_cleared`, `admin_language_created/updated/deleted`, `admin_framework_created/updated/deleted`, `admin_architecture_created/updated/deleted`, `admin_template_created/updated/deleted`. Eventos só do `SecurityLogger` (login, guardrail, etc.) **não** criam linha em `audit_logs`, salvo se alguém chamar `record` — não chamam.

### UC18 Reportar bug

| Campo | Conteúdo |
| --- | --- |
| ID | UC18 |
| Nome | Reportar bug |
| Ator | Quem vê o layout autenticado |
| Objetivo | Abrir o cliente de e-mail |
| Pré-condições | Nenhuma de servidor |
| Pós-condições | Nenhuma no GUEASS. Não há tabela de bugs |
| Fluxo | Modal em `layout.blade.php` com `mailto:suportegueass@gmail.com?subject=Report%20de%20Bug%20-%20GUEASS` |
| Alternativos | O mesmo endereço aparece no modal “Esqueceu a senha” (`auth/login.blade.php`, sem subject) e na política de privacidade |
| Exceções | NÃO ENCONTRADO |
| Regras | Não há envio SMTP |
| Telas e rotas | Layout; sem rota |
| Log | Nenhum |
| Testes | `tests/Feature/LayoutAccessibilityTest.php` (presença de mailto, a confirmar pelo nome do arquivo; 7 testes no arquivo) |

### UC19 Alternar tema e acessibilidade

| Campo | Conteúdo |
| --- | --- |
| ID | UC19 |
| Nome | Alternar tema e acessibilidade |
| Ator | Qualquer um que carregue `ui.js` (layout autenticado e guest) |
| Objetivo | Persistir preferência visual no `localStorage` do navegador |
| Pré-condições | JavaScript |
| Pós-condições | Nenhuma no servidor |
| Fluxo | Ver seção 10. Chaves: `gueass-theme` (`dark`/`light`), `gueass-font-scale`, `gueass-high-contrast` (`1`/`0`), `sidebar-collapsed` |
| Alternativos | Sem valor salvo, o tema segue `prefers-color-scheme` |
| Exceções | `localStorage` indisponível: o JS ignora a exceção e aplica o padrão da sessão |
| Regras | Nenhuma de negócio |
| Telas e rotas | `partials/user-menu.blade.php`; sem rota |
| Log | Nenhum |
| Testes | `tests/Feature/LayoutAccessibilityTest.php` |

### UC20 Consultar política de privacidade

| Campo | Conteúdo |
| --- | --- |
| ID | UC20 |
| Nome | Consultar política de privacidade |
| Ator | Visitante ou autenticado |
| Objetivo | Ler o texto estático |
| Pré-condições | Nenhuma |
| Pós-condições | Nenhuma |
| Fluxo | `GET /privacidade` renderiza `resources/views/privacy.blade.php`. O texto integral está na seção 11 |
| Alternativos | Link “Voltar ao Início” vai para `home` se autenticado, senão `login` |
| Exceções | NÃO ENCONTRADO |
| Regras | O número de dias é `config('privacy.prompt_retention_days')`, não um literal na view |
| Telas e rotas | `privacy.blade.php`; `privacidade` |
| Log | Nenhum |
| Testes | NÃO VERIFICADO o nome do teste; a rota existe |

### UC21 Tarefa automática de prune

| Campo | Conteúdo |
| --- | --- |
| ID | UC21 |
| Nome | Remover prompts antigos |
| Ator | Agendador / operador de CLI |
| Objetivo | Apagar prompts com `created_at` anterior a N dias |
| Pré-condições | `php artisan schedule:run` ou chamada direta `gueass:prune-prompts` |
| Pós-condições | Linhas removidas. Log de aplicação (canal default, não o security) `gueass:prune-prompts` com `deleted` e `days`. Não grava o texto |
| Fluxo | `PrunePromptsCommand::handle` lê `--days` ou `config('privacy.prompt_retention_days', 90)`. Se `< 1`, exit failure e “O período de retenção precisa ser de pelo menos 1 dia.” Senão `Prompt::where('created_at', '<', now()->subDays($days))->delete()` e “Removidos {n} prompt(s) com mais de {days} dia(s).” |
| Alternativos | `--days=` na CLI |
| Exceções | Código de saída `FAILURE` se days < 1 |
| Regras | RN retenção |
| Telas e rotas | Nenhuma. Comando `gueass:prune-prompts`. Agenda `daily()` |
| Log | `Log::info`, não `SecurityLogger` |
| Testes | NÃO LOCALIZADO arquivo com o nome do comando na lista de 774 (não há `PrunePrompts*Test` na contagem por arquivo). Cobertura deste comando: NÃO ENCONTRADO na lista |

---
## 4. FLUXO DE GERAÇÃO DE PROMPT

Ordem real de `PromptController::generate` (`app/Http/Controllers/PromptController.php`) depois de `GeneratePromptRequest` ter passado.

### 4.1 Da requisição à resposta

1. Middleware: `AssignRequestId` (global), grupo `web` (CSRF inclusive), `auth`, `password.changed`, `throttle:10,1`.
2. `GeneratePromptRequest::prepareForValidation`: se `intencao` não é string preenchida, usa `user_input` ou `input_text`. Se for string, `mb_check_encoding(..., 'UTF-8')`; se ok, `sanitizeUserText`; se não, grava string vazia e `intencao_encoding=invalid`. IDs `''` ou `false` viram null. Cada valor string de `variables` passa por `sanitizeUserText`.
3. `sanitizeUserText`: remove `\0`, troca `\r\n` e `\r` por `\n`, `trim` se ainda for UTF-8 válido.
4. `rules`: `intencao` required, string, min `IntentAnalyzer::MIN_INPUT_LENGTH` (10), max `IntentAnalyzer::MAX_INPUT_LENGTH` (1000). IDs nullable integer exists na tabela. `variables` nullable array max 20. `variables.*` nullable string max 2000. `nao_salvar_historico` sometimes boolean.
5. `withValidator`: se encoding inválido, erro `INVALID_UTF8_MESSAGE` e retorna. Se não há erro de tamanho, `InputSanityGuardrail::assertSane`. Falha de injection loga `prompt_injection_detected` com `category` e `length` e adiciona a mensagem da exceção. Outra recusa loga `guardrail_rejected` com `rejectionCategory` e `length`. `Throwable` genérico vira “Não foi possível processar a solicitação.” Para cada variável: chave deve casar `^[A-Za-z_][A-Za-z0-9_]*$` senão `VARIABLE_KEY_MESSAGE`. UTF-8 inválido no valor: mensagem de UTF-8. Injection no valor: log e `InputUnprocessableException::MESSAGE`. `isMalicious` no valor (XSS/SQLi ou injection): log `guardrail_rejected` category `xss_sqli` e a mesma mensagem. O guardrail de sanidade (gibberish, escopo) **não** é aplicado às variáveis, só `isPromptInjection` e `isMalicious`.
6. No controller, `SensitiveDataRedactor::inspect` na intenção já validada. O texto usado dali em diante é o redigido. `redactMap` nas variáveis que passam `variaveisDinamicas` (de novo exige a regex da chave, `trim(strip_tags)`, descarta vazio).
7. Se `types` não é vazio, log `sensitive_data_redacted` com types e counts (não com o segredo).
8. Chave de cache `generate-idempotency:{userId}:{sha256(intencaoRedigida|0 ou 1)}`. Janela `max(1, config('security.generate_idempotency_seconds', 5))`. Se o cache tem array, devolve esse payload sem regerar. A chave **não** inclui IDs de catálogo nem variables.
9. `PromptPipelineService::generate` delega a `PromptGeneratorService::generate`.
10. Lá dentro, `guardrail->assess` de novo. Se `accepted` é false, `InputUnprocessableException::disconnected()`. Essa exceção estende `InvalidIntentException` e cai no catch do controller, que mostra a mensagem pública.
11. Redação de novo (`redact` e `redactMap`) — segunda passagem.
12. `new IntentAnalyzer(new NullAIProvider)` — a análise estruturada é **sempre** o provedor null, não o driver de `AI_PROVIDER`. `analyze` sanitiza de novo (strip_tags, remove controles, colapsa espaços, corta em 1000) e exige mínimo 10. `NullAIProvider::analyzeIntent` preenche objective, technologies, architecture, constraints, type por heurística local.
13. `enrichIntent` acrescenta o `nome` da linguagem e do framework se os IDs do formulário existem, e preenche `architecture` com o nome do catálogo se a heurística deixou null.
14. `TemplateSelector::select`. Se o seletor devolve null, o corpo usado na chamada ao provedor é a string `Tarefa: {user_input}`; o template null só estoura depois, se `valido` for true (`NoCompatibleTemplateException::forIntent`).
15. `provider->generateStructuredPrompt`. Driver vem de `config('ai.provider')`.
16. `decodeStructuredResponse`: só aprova se o JSON for objeto e `valido` for o booleano `true` (string `"true"` não aprova, `toBool`). Senão `valido` false e motivo `UNCLEAR_MESSAGE` se não houver motivo string.
17. Se não aprovado, `InvalidIntentException::unclear(motivo)`.
18. Se lean, o `prompt_gerado` é descartado (`$prompt = ''`). Se não lean e `prompt_gerado` veio vazio, `PromptComposer::compose` interpola o template.
19. `PromptBuilderService::assemble` monta lean ou envelope. `PromptAssemblyException` é engolida e o corpo original volta (log warning se houver logger).
20. De volta ao controller: `redactor->redact` na saída. `CatalogHintResolver::resolve` calcula os IDs gravados.
21. Opt-out: não insere; flash específico; cacheia o payload; responde.
22. Senão `Prompt::create` com user_id, template_id, três IDs, `input_text` (já redigido), `output_text` (já redigido). `is_useful` fica null. Falha: log `prompt_persist_failed` com a classe da exceção, `saved=false`, flash de falha ao salvar, e ainda assim responde sucesso.
23. Cache `put` da janela. `respostaGeracao`: JSON 201 com o array, ou redirect `home` com `sucesso`, `last_output`, `selected_template_id` e, se houve id, `last_prompt_id`.

`PromptPipelineResult.degraded` é sempre `false` no `return` de `generate`. O comentário do result fala em fallback offline; esse ramo **não existe** no método. Fail-closed substitui fallback.

### 4.2 Guardrail — ordem e categorias

`InputSanityGuardrail::assess` (`app/Services/Guardrails/InputSanityGuardrail.php`):

1. `isMalicious`: `hasXssOrSqli` no texto cru **ou** `PromptInjectionDetector::isInjection`. Se sim, `reject`.
2. `normalize` próprio (não é `TextNormalizer`): minúsculas, sem acento, pontuação vira espaço. Se vazio, `reject`.
3. `hasGibberish` em algum token: `reject`.
4. `isWordSalad`: `reject`.
5. `hasSoftwareScope`: se nenhum marcador, `reject`.
6. `isLean` → `lean()` senão `full()`.

Mensagem pública única para qualquer reject via `assertSane` / `disconnected()`: “A instrução fornecida parece inválida ou desconexa. Por favor, descreva uma necessidade clara.” (`InputUnprocessableException::MESSAGE`).

`rejectionCategory`: `xss_sqli` se o detector de XSS/SQLi casa; senão a categoria do detector de injection; senão `sanity`. Injection tem precedência de log na request: se `isPromptInjection`, o evento é `prompt_injection_detected` mesmo que XSS também case (`isPromptInjection` é testado primeiro no `catch`).

**XSS/SQLi (`hasXssOrSqli`).** Texto em minúsculas. Needles literais: `<script`, `</script`, `javascript:`, `vbscript:`, `data:text/html`, `onerror=`, `onload=`, `onclick=`, `onmouseover=`, `<iframe`, `<svg`, `expression(`. Padrões: `' OR`, `or 1=1`, `;` seguido de drop/delete/insert/update/truncate, `union select` / `union all select`, `xp_cmdshell`, `information_schema`, `sleep(dígitos)`, `benchmark(`, `load_file(`, `into outfile` / `into dumpfile`. Se `preg_match` retorna `false` (erro PCRE), também recusa (fail-closed). Pedido que só fala sobre ataque, sem essa sintaxe, não casa — o comentário do método diz isso.

**Gibberish / teclado.** Token numérico ou com menos de 5 caracteres não é lixo. Token na `TECH_WHITELIST` (lista fixa no arquivo, inclui laravel, javascript, postgresql, etc.) não é lixo. Sequência contida numa fileira de teclado (`qwertyuiop`, `asdfghjkl`, `zxcvbnm` e os reversos) é lixo. Trecho de 6 teclas consecutivas da fileira dentro do token é lixo. Letras sem vogal e tamanho ≥ 6: lixo. Tamanho ≥ 8 e razão de vogais < 0,22: lixo. Tamanho ≥ 10, razão < 0,28 e maior sequência de consoantes ≥ 5: lixo.

**Word salad.** Conta substantivos de `EVERYDAY_NOUNS` (papo, rato, padeiro, bola, sapato, manteiga, girassol, gato, cachorro, fogao, cadeira, pato, banana, churrasco, sabonete, abacaxi, preto). Dois ou mais sem `STRONG_SOFTWARE`: salada. Um ou mais com até 3 tokens e sem strong: salada.

**Escopo de software.** `hasSoftwareScope` exige um radical de `SOFTWARE_MARKERS` (cria, implement, api, login, laravel, erro, 500, bug, …). A lista completa está nas linhas 33–47 do arquivo.

**Categorias de prompt injection** (`PromptInjectionPatterns` e `PromptInjectionDetector::detect`), nesta ordem de decisão:

1. Se `TextNormalizer::hasAbnormalInvisibles`: categoria `evasive_encoding` imediata.
2. Para cada categoria em `categorized()`, testa os regex no haystack da categoria. Erro de PCRE devolve `evasive_encoding`.
3. `structuralInstructionOverride`: verbo de descarte perto de “o que te disseram” / contexto anterior, ou reset temporal. Categoria `instruction_override`.
4. `compoundSignals`: esquecer o passado **e** “siga somente”, ou “não obedeça” perto de sistema/regras, ou abandonar briefing **e** obedecer exclusivamente. Categoria `instruction_override`.
5. `compactedCategory`: texto sem espaços contra agulhas fixas (ignoreprevious, retornevalidotrue, youarenowdan, etc.).

Nomes das constantes: `instruction_override`, `system_prompt_reveal`, `role_switch`, `jailbreak`, `verdict_manipulation`, `delimiter_forging`, `exfiltration`, `evasive_encoding`. Os regex em si ficam em `PromptInjectionPatterns::categorized`; não são reproduzidos aqui.

### 4.3 Normalização para injection

`app/Support/TextNormalizer.php`. O guardrail de sanidade **não** usa esta classe (comentário do arquivo: não pode transformar “erro 500” em “erro soo”).

| Passo | Critério |
| --- | --- |
| NFKC | `Normalizer::FORM_KC` se a classe `Normalizer` existe (extensão intl). Senão o texto original |
| Invisíveis | Remove U+00AD, U+180E, U+200B–U+200F, U+202A–U+202E, U+2060–U+206F, U+FEFF, U+E0000–U+E007F |
| Anormal | `invisibleCount >= 6` (`INVISIBLE_COUNT_LIMIT`) ou (count > 0 e count/length > 0,15) (`INVISIBLE_RATIO_LIMIT`) |
| fold | NFKC, sem invisíveis, minúsculas, homóglifos cirílicos/gregos da tabela `HOMOGLYPHS`, sem acento, espaços colapsados |
| forInjection | fold + leetspeak `@→a`, `4→a`, `3→e`, `1→i`, `!→i`, `0→o`, `5→s`, `7→t`, `$→s`; pontuação vira espaço; `compactSpacedLetters` junta sequências de letras isoladas (“I g n o r e” → “ignore”) sem colar o resto da frase |

### 4.4 SensitiveDataRedactor

Marcador `[REDACTED:{tipo}]`. Ordem de `PATTERNS` e depois validadores. Se um `preg_replace_callback` dos padrões não devolve string, o texto inteiro vira `[REDACTED:pcre_failure]`.

| Tipo | Formato (resumo) | Validação extra |
| --- | --- | --- |
| private_key | bloco `-----BEGIN … PRIVATE KEY-----` até `END` | Nenhuma além do regex (modo `s`) |
| aws_access_key | `AKIA` + 16 de `[0-9A-Z]` | Nenhuma |
| github_token | `ghp_` + 36+ alfanuméricos, ou `github_pat_` + 22+ | Nenhuma |
| google_api_key | `AIza` + 35 de `[0-9A-Za-z_-]` | Nenhuma |
| slack_token | `xox` + b/a/p/r/s + `-` + 10+ | Nenhuma |
| openai_key | `sk-` + 20+ alfanuméricos | Nenhuma |
| jwt | três segmentos começando `eyJ` | Nenhuma |
| bearer | `Authorization: Bearer` + token | Nenhuma |
| connection_string | `mysql\|postgres\|postgresql\|mongodb\|redis\|amqp://user:pass@host` | Nenhuma |
| password_pair | palavra password/passwd/secret/token/api_key `=` valor | Nenhuma |
| cpf | `\d{3}.?\d{3}.?\d{3}-?\d{2}` | 11 dígitos, não todos iguais, dois dígitos verificadores |
| cnpj | máscara de 14 dígitos | não todos iguais, pesos do método `isValidCnpj` |
| card | começa em 3–6, 13 a 19 dígitos com separadores espaço ou hífen | Luhn (`isValidCard`, comprimento 13–19) |
| email | regex de e-mail simples | Não redige se o match já contém `[REDACTED:` |

### 4.5 Delimitação da intenção

`UserIntentFrame`: início `<<<GUEASS_USER_INTENT>>>`, fim `<<<END_GUEASS_USER_INTENT>>>`. `neutralize` troca esses marcadores (case insensitive) por `«GUEASS_USER_INTENT»` e `«END_GUEASS_USER_INTENT»`, e também `<<<INTENCAO` / `<<<TEMPLATE`. Linhas que são só `INTENCAO` ou `TEMPLATE` viram `«INTENCAO»` / `«TEMPLATE»`. `wrap` coloca o texto neutralizado entre os marcadores. `PromptGeneratorService::SYSTEM_INSTRUCTION` inclui `UserIntentFrame::RULE` no texto enviado ao modelo. `GeminiAIProvider::structuredMessage` chama `wrap` na intenção e `neutralize` no corpo do template.

### 4.6 Lean versus envelope

`isLean` (`InputSanityGuardrail`), sobre o texto **antes** da normalização de acentos para o tamanho, e tokens da versão normalizada:

| Condição | Resultado |
| --- | --- |
| `mb_strlen(trim(raw)) > 60` | não é lean (segue para envelope, se aceito) |
| mais de 10 tokens | não é lean |
| contém marcador de `RICH_DETAIL_MARKERS` (s3, sqs, sns, queue(s), fila(s), clean architecture, hexagonal, microserv, endpoint, rabbitmq, kafka, graphql, openapi) | não é lean |
| é diagnóstico (`DIAGNOSTIC_MARKERS`: erro, 500, 404, 422, bug, falha, exception, quebrou, não funciona, crash, timeout, stack trace, defeito) | lean |
| não tem verbo de implementação (`IMPLEMENTATION_VERBS`) | lean |
| tem verbo de implementação, não é diagnóstico, e tokens ≤ 5 | lean |
| tem verbo de implementação e tokens ≥ 6 (e passou os tetos de 60/10) | não é lean |

`LEAN_MAX_LINES = 25` **não** entra em `isLean`. Entra em `PromptBuilderService::assembleLean`: depois de montar o envelope curto, `substr_count(envelope, "\n") + 1` não pode passar de 25; se passar, `PromptAssemblyException` com “Envelope lean excedeu o limite interno de linhas.” Quem chama `assemble` a partir do gerador captura `PromptAssemblyException` e devolve o corpo anterior (no lean, esse corpo foi forçado a `''`).

Envelope completo (`assemble`, quando não lean): seções `PAPEL E CONTEXTO`, `TAREFA` (pedido dentro de `UserIntentFrame::wrap` mais enquadramento se útil), `RESTRIÇÕES NEGATIVAS (NÃO FAÇA)` com `DEFAULT_CONSTRAINTS`, `ESQUEMA DE SAÍDA` dependente de `type` e de `proseOnly`, `AUTO-VALIDAÇÃO`.

### 4.7 NO_CODE

`PromptOutputPolicy::isProseOnly`: true se `intent.type === 'documentation'` (minúsculo) **ou** se a intenção normalizada (sem acento) contém um de: `sem codigo`, `nao quero codigo`, `nao gerar codigo`, `nao escreva codigo`, `nao escreva nenhum codigo`, `apenas documentacao`, `somente documentacao`, `so documentacao`, `documentacao apenas`, `apenas prosa`, `somente prosa`, `sem implementacao`, `nao implemente`, `sem blocos de codigo`. Também olha `intent.constraints`.

Se proseOnly, `buildNegativeConstraints` coloca no topo `NO_CODE_CONSTRAINT` = “Não gere código-fonte, snippets, stubs nem cercas de código.” O esquema de saída troca para três itens de prosa. A auto-validação ganha “A resposta está apenas em prosa/especificação, sem código-fonte.” `stripCodeInstructions` remove linhas cujo texto normalizado contém instruções de escrever código (lista em `isCodeInstruction`). `rewriteProseFraming` troca `CODE_TASK_LEAD` por `PROSE_TASK_LEAD`.

Isso altera o **prompt entregue ao usuário**, não a execução de um compilador. Não há verificação de que um modelo externo obedecerá a restrição.

### 4.8 Seleção de template

Constantes em `TemplateSelector`:

| Nome | Valor |
| --- | --- |
| `WEIGHT_LANGUAGE` | 4 |
| `WEIGHT_FRAMEWORK` | 3 |
| `WEIGHT_ARCHITECTURE` | 2 |
| `WEIGHT_TYPE` | 2 |
| `WEIGHT_CATEGORY` | 8 |
| `WEIGHT_TAG` | 3 |
| `MAX_TAG_SCORE` | 9 |
| `MAX_STACK_SCORE` | 7 |
| Piso do stack após o min | −3 (`max(-3, min(stack, 7))`) |
| `STYLE_PENALTY` | 6 (aplicada como −6) |
| `WEIGHT_TEXT_HINT` | 1 |
| `MAX_TEXT_SCORE` | 3 |
| Categoria compatível sem stack | 2 (`categoryScore`) |

Pontuação = category + tag + type + stack + stylePenalty. Só templates `is_active` true, `orderBy('id')`. Empate: o primeiro a atingir o maior score permanece, porque a troca exige `score > bestScore`. Como a lista vem por id crescente, empate fica com o menor id.

Stack classificado (tem linguagem, framework ou arquitetura no pivô): cada dimensão soma `weight * interseção` ou `−weight` se a intenção citou a dimensão e não houve interseção. Sem classificação: `textScore` no nome+corpo, teto 3.

Roleplay (`ROLEPLAY_HINTS` no nome/slug) perde 6 se a intenção não pediu roleplay.

Fallback se ninguém pontua acima de zero ou se o select lança: primeiro `is_active` e `is_generic` true, menor id; senão template ativo sem nenhum pivô, preferindo nome com `FALLBACK_HINTS`.

`askProviderForTemplate` só roda se o seletor recebeu um provedor cujo `name()` não é `null`. O container **não** injeta provedor (`AIServiceProvider`). Na geração normal o provedor do seletor é null e o método retorna null. A escolha fica 100% local.

### 4.9 O que é gravado da dedução

`CatalogHintResolver::resolve`: ID do formulário se `> 0`; senão o catálogo cujo `nome` ou `slug` (minúsculo, tamanho ≥ 2) aparece no haystack (intenção + architecture + technologies). Desempate: o rótulo mais longo. Framework, se não veio no formulário, só é buscado entre os da linguagem já resolvida. O formulário vence a dedução. Esses três IDs vão para `prompts`. Podem permanecer null.

### 4.10 AI_PROVIDER

| | `null` | `gemini` |
| --- | --- | --- |
| Classe | `NullAIProvider` | `GeminiAIProvider` |
| Rede | Não | `POST {base}/models/{model}:generateContent` |
| Chave | Não usa | Header `x-goog-api-key`. Ausente: `AIProviderException` “GEMINI_API_KEY não está configurada.” |
| Payload | Não envia | systemInstruction + contents user. `temperature` 0,2 na geração estruturada, `responseMimeType` application/json, schema com `valido`, `motivo_rejeicao`, `prompt_gerado` obrigatórios |
| Teto | — | JSON do payload > `maxPayloadBytes` (65536) recusa antes do HTTP |
| Timeout | — | `timeout` 15 s; `connectTimeout` `min(5, timeout)` |
| Tentativas | — | `retry(max(1, tries), 250 ms)`. Default tries 2. Repete se não há status (conexão) ou status em 429, 500, 502, 503, 504. Não repete 401/403/400 |
| Redação | O gerador já redige antes | O provider redige instruction, userText e o mapa de variáveis de novo |
| Saída | Array com `valido` bool. Rejeita keysmash, falta de marcador de software, few-shots de salada e injection | Devolve `['_raw' => texto]`. Parse fail-closed no gerador |
| Falha | Vira `InvalidIntentException` no gerador | Idem. Não há troca silenciosa para o provider null |
| Análise de intenção | Usada (instância local) | `analyzeIntent` existe na classe Gemini e **não** é chamada pelo `PromptGeneratorService` |

`NullAIProvider::generateStructuredPrompt` ainda pode devolver `valido: false` com mensagens próprias (“A entrada não apresenta um objetivo ou escopo de software coerente.”, “Não foi possível identificar um fluxo…”, “A entrada tenta contornar as regras de validação e não descreve um objetivo de software.”) ou `UNCLEAR_MESSAGE`. Essas frases chegam ao usuário via `InvalidIntentException::unclear`.

### 4.11 Persistência e resposta

Campos gravados: `user_id`, `template_id`, `architecture_id`, `language_id`, `framework_id`, `input_text`, `output_text`. Não grava `is_useful` no create.

Opt-out: nenhum insert; o payload de cache inclui `saved=false`.

Falha de insert: resposta de sucesso com aviso; log `prompt_persist_failed`.

Idempotência: 5 s por padrão; a segunda resposta é o payload cacheado, inclusive o mesmo `prompt_id` se a primeira gravou. Não cria segunda linha.

HTML: redirect 302 para `/` com flash. JSON: 201 e o array (`saved`, `prompt_id`, `prompt`, `selected_template_id`, `flash`, mais `toArray()` que repete `prompt`, `template`, `intent`, `degraded`).

### 4.12 Eventos por ramo

| Ramo | Evento |
| --- | --- |
| Injection na intenção ou variável | `prompt_injection_detected` |
| XSS/SQLi ou sanidade na intenção; XSS/SQLi na variável | `guardrail_rejected` |
| Segredo na intenção (inspect do controller) | `sensitive_data_redacted` |
| Exceção de provedor com timeout/cURL 28/ConnectionException | `provider_timeout` |
| Outra falha de provedor ou JSON que vira throw antes do decode | `provider_error` |
| Insert falhou | `prompt_persist_failed` |
| Geração ok | nenhum evento de sucesso |
| Cache de idempotência servido | nenhum evento novo |

`Log::error` do controller em rejeição de intenção, template incompatível e falha inesperada registra só a classe da exceção, não o texto.

---

## 5. MAPA DE ROTAS

Grupo `web` em todas as rotas de `routes/web.php`, mais os middleware da coluna. `GET|HEAD /up` não mostrou middleware no `gatherMiddleware()` (rota de health do framework). Fonte da lista: `php artisan route:list` (51 rotas).

| Método | URI | Ação | Middleware além de `web` | Perfil | Observação |
| --- | --- | --- | --- | --- | --- |
| GET\|HEAD | `/up` | health do framework | (vazio no gather) | público | Corpo HTTP NÃO VERIFICADO |
| GET\|HEAD | `/` | `PromptController@index` | auth, password.changed | USU/ADM | Nome `home` |
| GET\|HEAD | `/login` | `AuthController@showLogin` | guest | visitante | |
| POST | `/login` | `AuthController@login` | guest, throttle:5,1, throttle:login-email-ip | visitante | 5/min por IP e 5/min por e-mail\|IP |
| POST | `/register` | `AuthController@register` | guest, throttle:10,1 | visitante | |
| POST | `/logout` | `AuthController@logout` | auth | autenticado | Sem password.changed |
| GET\|HEAD | `/senha-obrigatoria` | `ProfileController@editForcedPassword` | auth | autenticado | |
| PUT | `/senha-obrigatoria` | `ProfileController@updateForcedPassword` | auth, throttle:10,1 | autenticado | |
| GET\|HEAD | `/perfil` | `ProfileController@edit` | auth, password.changed | USU/ADM | |
| PUT | `/perfil` | `ProfileController@update` | auth, password.changed, throttle:20,1 | USU/ADM | |
| DELETE | `/perfil` | `ProfileController@destroy` | auth, password.changed, throttle:5,1 | USU/ADM | |
| POST | `/perfil/avatar` | `ProfileController@updateAvatar` | auth, password.changed, throttle:10,1 | USU/ADM | |
| GET\|HEAD | `/privacidade` | view `privacy` | — | público | |
| POST | `/prompts/generate` | `PromptController@generate` | auth, password.changed, throttle:10,1 | USU/ADM | |
| DELETE | `/prompts/historico` | `PromptController@clearHistory` | auth, password.changed, throttle:5,1 | USU/ADM | |
| GET\|HEAD | `/prompts/{prompt}` | `PromptController@show` | auth, password.changed | dono | |
| POST | `/prompts/{prompt}/feedback` | `PromptController@feedback` | auth, password.changed, throttle:20,1 | dono | |
| DELETE | `/prompts/{prompt}` | `PromptController@destroy` | auth, password.changed, throttle:20,1 | dono | |
| GET\|HEAD | `/api/languages/{language}/frameworks` | closure | auth, password.changed | USU/ADM | JSON |
| GET\|HEAD | `/languages` | `LanguageController@index` | — | público | |
| POST | `/languages` | `LanguageController@store` | auth, password.changed, can:admin, throttle:20,1 | ADM | |
| GET\|HEAD | `/languages/create` | create | idem admin | ADM | |
| GET\|HEAD | `/languages/{language}` | show | idem admin | ADM | |
| PUT\|PATCH | `/languages/{language}` | update | idem admin | ADM | |
| DELETE | `/languages/{language}` | destroy | idem admin | ADM | |
| GET\|HEAD | `/languages/{language}/edit` | edit | idem admin | ADM | |
| GET\|HEAD | `/frameworks` | index | — | público | |
| POST, create, edit, update, delete | `/frameworks…` | FrameworkController | admin + throttle:20,1 | ADM | Sem rota `show` |
| GET\|HEAD | `/architectures` | index | — | público | |
| POST, create, edit, update, delete | `/architectures…` | ArchitectureController | admin + throttle:20,1 | ADM | Sem `show` |
| GET\|HEAD | `/templates` | index | — | público | |
| POST, create, edit, update, delete | `/templates…` | TemplateController | admin + throttle:20,1 | ADM | Sem `show` |
| GET\|HEAD | `/admin` | closure redirect | admin | ADM | Sem nome de rota |
| GET\|HEAD | `/admin/dashboard` | `AdminDashboardController@index` | admin | ADM | |
| POST | `/admin/metricas/corte` | resetMetrics | admin, throttle:10,1 | ADM | |
| DELETE | `/admin/metricas/corte` | clearMetricsReset | admin, throttle:10,1 | ADM | |
| GET\|HEAD | `/admin/auditoria` | `AdminAuditLogController@index` | admin | ADM | |
| GET\|HEAD | `/admin/users` | `AdminUserController@index` | admin | ADM | |
| PUT | `/admin/users/{user}/password` | resetPassword | admin, throttle:10,1 | ADM | |

Rota HTTP `/storage`: **NÃO ENCONTRADO** em `route:list`. Arquivos de avatar são servidos pelo symlink público do disco `public` (`User::avatarUrl` monta `url('storage/'.$this->avatar)`). Existência do symlink nesta máquina: **NÃO VERIFICADO**.

`GET`/`HEAD` numa URI que só tem POST/PUT/DELETE: 404, sem header `Allow`. Outro método incompatível: 405, sem `Allow`. Evidência: `FriendlyHttpRenderer::statusFor` e `tests/Feature/ErrorPagesTest.php`.

---

## 6. REGRAS DE NEGÓCIO E PARÂMETROS

| ID | Regra | Valor | Arquivo / símbolo | Teste |
| --- | --- | --- | --- | --- |
| RN01 | Intenção mínima | 10 caracteres | `IntentAnalyzer::MIN_INPUT_LENGTH` | `IntentValidationTest`, `InputEdgeCasesTest` |
| RN02 | Intenção máxima | 1000 | `IntentAnalyzer::MAX_INPUT_LENGTH` | idem |
| RN03 | Objetivo normalizado máximo | 300 | `IntentAnalyzer::MAX_OBJECTIVE_LENGTH` | `IntentAnalyzerTest` |
| RN04 | Variáveis | no máximo 20; cada valor 2000 | `GeneratePromptRequest::rules` | `InputSurfaceEdgeCasesTest` |
| RN05 | Nome de variável | `^[A-Za-z_][A-Za-z0-9_]*$` | `GeneratePromptRequest::withValidator` | idem |
| RN06 | UTF-8 | `mb_check_encoding` | `GeneratePromptRequest` | `InputEdgeCasesTest` |
| RN07 | NUL e CRLF | NUL removido; CR vira LF; trim | `sanitizeUserText` | `InputEdgeCasesTest` |
| RN08 | Senha | mínimo 8, letras e números, confirmada | `PasswordRules::policy` | `PasswordPolicyTest` |
| RN09 | Senha vazada | `uncompromised()` só se `APP_ENV=production` | `PasswordRules::policy` | `ProductionConfigTest` |
| RN10 | Papel no cadastro | não está em `$fillable`; default de coluna `USU` | `User`, schema `users.role` | `AuthSecurityTest` |
| RN11 | Throttle login por IP | 5 por 1 minuto | `routes/web.php` `throttle:5,1` | `RouteThrottleTest` |
| RN12 | Throttle login e-mail+IP | 5 por minuto, chave `strtolower(email)\|ip` | `AppServiceProvider` `login-email-ip` | `RouteThrottleTest` |
| RN13 | Throttle cadastro | 10 por minuto | `routes/web.php` | `RouteThrottleTest` |
| RN14 | Throttle geração | 10 por minuto | `routes/web.php` | `RouteThrottleTest` |
| RN15 | Throttle feedback, delete unitário, escrita de catálogo, update de perfil | 20 por minuto | `routes/web.php` | `RouteThrottleTest` |
| RN16 | Throttle senha forçada, avatar, reset admin, métricas | 10 por minuto | `routes/web.php` | `RouteThrottleTest` |
| RN17 | Throttle excluir conta e limpar histórico | 5 por minuto | `routes/web.php` | `RouteThrottleTest` |
| RN18 | Sessão | driver database, 120 min, encrypt default true, httpOnly default true, SameSite lax | `config/session.php`, `.env.example` | `ProductionConfigTest` (parte) |
| RN19 | Retenção | 90 dias default; comando recusa days < 1 | `config/privacy.php`, `PrunePromptsCommand` | NÃO ENCONTRADO teste do comando |
| RN20 | Idempotência | 5 s; chave usuário + sha256(texto redigido \| flag) | `config/security.php`, `PromptController::generate` | `Phase3SecurityObservabilityTest` ou `PromptControllerTest` (o arquivo exato do caso de 5 s: NÃO VERIFICADO o nome do método) |
| RN21 | Avatar tipos | jpg, jpeg, png, webp; max 2048 KB | `UpdateAvatarRequest` | `ProfileAvatarTest` |
| RN22 | Avatar reprocesso | MIME real jpeg/png/webp; lado máx. 512; PNG compressão 6; sem SVG | `AvatarSanitizer` | `AvatarSanitizerTest` |
| RN23 | Último ADM | não exclui e não tira o role se count ADM ≤ 1 | `User::booted`, `DeleteAccountRequest` | `LastAdminProtectionTest` |
| RN24 | Métricas corte | chave `metrics_reset_at`; filtro `created_at >=`; não apaga linhas | `PromptMetricsService` | `MetricsResetTest` |
| RN25 | Top templates / stacks | 5 e 8 | `TOP_TEMPLATES`, `TOP_STACKS` | `AdminDashboardTest` |
| RN26 | Satisfação | `round(uteis * 100 / avaliados, 1)` ou null | `PromptMetricsService::summary` | `AdminDashboardTest` |
| RN27 | Template inativo | fora do `select` | `TemplateSelector::selectBest` | `TemplateSelectorTest` |
| RN28 | Exclusão linguagem | bloqueada se há framework ou template | `LanguageController::destroy` | `CatalogReferentialIntegrityTest` |
| RN29 | Exclusão arquitetura | bloqueada se há template no pivô | `ArchitectureController::destroy` | `CatalogReferentialIntegrityTest` |
| RN30 | FKs de prompt para catálogo | ON DELETE SET NULL | schema, migration `2026_10_02_020000_...` | `CatalogReferentialIntegrityTest` |
| RN31 | FK prompt→user | ON DELETE CASCADE | schema | `LastAdminProtectionTest` / delete de conta |
| RN32 | Histórico no menu | 30 mais recentes | `AppServiceProvider` view composer | NÃO VERIFICADO o nome do teste |
| RN33 | Lean tamanho | ≤ 60 caracteres e ≤ 10 tokens, mais as regras da seção 4.6 | `InputSanityGuardrail::isLean` | `InputSanityGuardrailTest` (unit 19 + feature 4) |
| RN34 | Lean linhas | teto 25 do envelope montado | `InputSanityGuardrail::LEAN_MAX_LINES` | `PromptBuilderServiceTest` |
| RN35 | Senha temporária | 16 caracteres via `Str::password` até passar a política; flag true | `AdminUserController::temporaryPassword` | `AdminUserTest`, `PasswordResetTest` |
| RN36 | Auditoria página | 20 por página | `AdminAuditLogController` | `Phase3SecurityObservabilityTest` |
| RN37 | Request id | 8 a 128, `[A-Za-z0-9._-]` | `AssignRequestId::isValid` | `Phase3SecurityObservabilityTest` |
| RN38 | Log de segurança | rotação 30 arquivos | `config/logging.php` `security-daily` | `Phase3SecurityObservabilityTest` |
| RN39 | Gemini | timeout 15, tries 2, payload 65536, retry 250 ms, status 429/5xx | `config/services.php`, `GeminiAIProvider` | `GeminiAIProviderTest` (20) |
| RN40 | User-Agent no log | cortado em 180 | `SecurityLogger::enrich` | `Phase3SecurityObservabilityTest` |
| RN41 | Nome de perfil | max 255 | `UpdateProfileRequest` | `PasswordPolicyTest` ou perfil |
| RN42 | Catálogo nome/slug | max 100 | controllers de language/framework/architecture | `CatalogPopulationTest` |
| RN43 | Descrição de arquitetura | max 5000 | `ArchitectureController` | NÃO VERIFICADO o método |
| RN44 | Corpo de template | max 500000 | `StoreTemplateRequest` | `TemplateCatalogTest` |
| RN45 | Nome de template no update | max 150 (coluna) | `UpdateTemplateRequest` | idem |
| RN46 | Nome de template no store | max 255 na request contra coluna 150 | `StoreTemplateRequest` vs schema | divergência; teste que force 200 caracteres: NÃO VERIFICADO |
| RN47 | Bloco | A, B ou C; default de coluna `A` | `Template::BLOCOS`, schema | `TemplateCatalogTest` |
| RN48 | Versão de template | default `'1'` se vazia; coluna default `1` | `TemplateController` | NÃO VERIFICADO |
| RN49 | Bcrypt | 12 no exemplo; 4 na suíte | `.env.example`, `phpunit.xml` | — |
| RN50 | Lista de eventos de segurança | 32 nomes em `SecurityLogger::EVENTS` | `SecurityLogger` | teste que rejeita evento desconhecido: `Phase3SecurityObservabilityTest` (existência do caso: o arquivo tem 23 testes; o método exato NÃO VERIFICADO) |

---

## 7. MODELO DE DADOS FINAL

Fonte: `information_schema` do schema `gueass_db` em 2026-10-03, não só as migrations. Engine InnoDB e `utf8mb4_unicode_ci` em todas. PK indicada. `ON UPDATE` das FKs observadas: `NO ACTION`. `ON DELETE` na tabela de FKs da seção 0 do dump.

### 7.1 `users`

| Coluna | Tipo | Null | Default | Notas |
| --- | --- | --- | --- | --- |
| id | bigint unsigned AI | não | — | PK |
| name | varchar(255) | não | — | |
| email | varchar(255) | não | — | UNIQUE `users_email_unique` |
| email_verified_at | timestamp | sim | null | **SEM USO** de fluxo (só cast e factory) |
| password | varchar(255) | não | — | hash |
| must_change_password | tinyint(1) | não | 0 | |
| role | varchar(255) | não | `USU` | valores usados: `USU`, `ADM` |
| avatar | varchar(255) | sim | null | caminho relativo |
| remember_token | varchar(100) | sim | null | **SEM USO** (não há “lembrar-me” nas views) |
| created_at / updated_at | timestamp | sim | null | |

Eloquent: `prompts()` hasMany. Não há belongsToMany.

### 7.2 `prompts`

| Coluna | Tipo | Null | Default |
| --- | --- | --- | --- |
| id | bigint unsigned AI | não | PK |
| user_id | bigint unsigned | sim | FK users ON DELETE CASCADE |
| template_id | bigint unsigned | sim | FK templates ON DELETE SET NULL |
| architecture_id | bigint unsigned | sim | FK architectures ON DELETE SET NULL |
| language_id | bigint unsigned | sim | FK languages ON DELETE SET NULL |
| framework_id | bigint unsigned | sim | FK frameworks ON DELETE SET NULL |
| input_text | text | não | |
| output_text | longtext | não | |
| is_useful | tinyint(1) | sim | null; índice `prompts_is_useful_index` |
| created_at / updated_at | timestamp | sim | |

Índices adicionais: cada FK. Eloquent: belongsTo template, architecture, language, framework, user.

### 7.3 `languages`

id PK AI; nome varchar(100) NOT NULL; slug varchar(100) NOT NULL UNIQUE; timestamps null. Sem UNIQUE em `nome` (a validação do controller exige unique). Eloquent: `frameworks()` hasMany, `templates()` belongsToMany.

### 7.4 `frameworks`

id; nome varchar(100); slug varchar(100) UNIQUE; language_id NOT NULL FK languages ON DELETE CASCADE; timestamps. Eloquent: `language()` belongsTo, `templates()` belongsToMany.

### 7.5 `architectures`

id; nome varchar(100); descricao text NOT NULL; timestamps. Sem slug. Sem UNIQUE em nome. Eloquent: `templates()` belongsToMany.

### 7.6 `templates`

| Coluna | Tipo | Null | Default |
| --- | --- | --- | --- |
| id | bigint AI | não | PK |
| nome | varchar(150) | não | |
| slug | varchar(120) | sim | UNIQUE |
| descricao | text | sim | |
| intent_type | varchar(40) | sim | |
| bloco | varchar(1) | não | `A` |
| corpo_template | text | não | |
| versao | varchar(20) | não | `1` |
| is_active | tinyint(1) | não | 1 |
| is_generic | tinyint(1) | não | 0 |
| timestamps | timestamp | sim | |

Eloquent: hasMany prompts; belongsToMany languages, frameworks, architectures. `resolveBloco` também infere A/B/C pelo nome `(A1)` ou pelo `intent_type` se o valor da coluna não for A/B/C.

### 7.7 Pivôs

`language_template`, `framework_template`, `architecture_template`: id AI PK; `{entidade}_id` + `template_id` NOT NULL; UNIQUE do par; timestamps null; FKs ON DELETE CASCADE dos dois lados. Índice extra só em `template_id`.

### 7.8 `audit_logs`

id AI; user_id null FK users ON DELETE SET NULL; action varchar(64) NOT NULL; target_type varchar(191) null; target_id bigint unsigned null; ip varchar(45) null; metadata json null; created_at timestamp NOT NULL default CURRENT_TIMESTAMP. Sem `updated_at` (`AuditLog` `$timestamps = false`). Índice `(action, created_at)`. Eloquent: belongsTo user.

### 7.9 `app_settings`

key varchar(64) PK (não AI; model `$incrementing = false`, `$keyType = string`); value text null; timestamps null. Uso: `metrics_reset_at`.

### 7.10 Tabelas de framework

| Tabela | Uso pela aplicação de domínio |
| --- | --- |
| sessions | Sim, `SESSION_DRIVER=database`. Colunas: id varchar(255) PK, user_id bigint null índice, ip_address varchar(45), user_agent text, payload longtext, last_activity int índice. Sem FK no information_schema |
| cache | Sim se `CACHE_STORE=database` (default do config e do exemplo). key PK varchar(255), value mediumtext, expiration int índice |
| cache_locks | Infra do cache database. key PK, owner, expiration |
| jobs | Tabela existe. Nenhum job em `app/`. **SEM USO** de domínio. Colunas padrão Laravel: id, queue, payload longtext, attempts tinyint, reserved_at, available_at, created_at |
| job_batches | **SEM USO** de domínio |
| failed_jobs | **SEM USO** de domínio. uuid UNIQUE |
| password_reset_tokens | **SEM USO** pela aplicação (não há broker). email PK varchar(255), token varchar(255), created_at null. Citada só em `config/auth.php` |
| migrations | Controle do artisan. id, migration varchar(255), batch int |

### 7.11 Divergências migration / model / request

- `StoreTemplateRequest` aceita `nome` até 255; coluna e `UpdateTemplateRequest` usam 150.
- `email_verified_at` e `remember_token` existem no model (cast / hidden) e não têm fluxo.
- `PromptPipelineResult::$degraded` não é preenchido com true.
- Unique de `languages.nome` e `architectures.nome` é só validação, não índice.
- `Template::languages()` não declara nome de tabela pivô; o Laravel infere `language_template`, que existe.

```mermaid
%% DER GUEASS — schema gueass_db observado
erDiagram
    users ||--o{ prompts : "user_id CASCADE"
    users ||--o{ audit_logs : "user_id SET NULL"
    templates ||--o{ prompts : "template_id SET NULL"
    languages ||--o{ prompts : "language_id SET NULL"
    frameworks ||--o{ prompts : "framework_id SET NULL"
    architectures ||--o{ prompts : "architecture_id SET NULL"
    languages ||--o{ frameworks : "language_id CASCADE"
    languages ||--o{ language_template : "CASCADE"
    templates ||--o{ language_template : "CASCADE"
    frameworks ||--o{ framework_template : "CASCADE"
    templates ||--o{ framework_template : "CASCADE"
    architectures ||--o{ architecture_template : "CASCADE"
    templates ||--o{ architecture_template : "CASCADE"

    users {
        bigint id PK
        varchar name
        varchar email UK
        timestamp email_verified_at "sem uso de fluxo"
        varchar password
        tinyint must_change_password
        varchar role
        varchar avatar
        varchar remember_token "sem uso de fluxo"
    }
    prompts {
        bigint id PK
        bigint user_id FK
        bigint template_id FK
        bigint architecture_id FK
        bigint language_id FK
        bigint framework_id FK
        text input_text
        longtext output_text
        tinyint is_useful
    }
    languages {
        bigint id PK
        varchar nome
        varchar slug UK
    }
    frameworks {
        bigint id PK
        varchar nome
        varchar slug UK
        bigint language_id FK
    }
    architectures {
        bigint id PK
        varchar nome
        text descricao
    }
    templates {
        bigint id PK
        varchar nome
        varchar slug UK
        text descricao
        varchar intent_type
        varchar bloco
        text corpo_template
        varchar versao
        tinyint is_active
        tinyint is_generic
    }
    language_template {
        bigint id PK
        bigint language_id FK
        bigint template_id FK
    }
    framework_template {
        bigint id PK
        bigint framework_id FK
        bigint template_id FK
    }
    architecture_template {
        bigint id PK
        bigint architecture_id FK
        bigint template_id FK
    }
    audit_logs {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar target_type
        bigint target_id
        varchar ip
        json metadata
        timestamp created_at
    }
    app_settings {
        varchar key PK
        text value
    }
```

---

