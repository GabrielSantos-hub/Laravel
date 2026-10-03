# GUEASS

Gerador de prompts de software (Laravel 12 / PHP 8.2+). Ambiente alvo:
execução local com MySQL 8, conforme a ERS.

## Requisitos

- PHP **8.2+** com a extensão **`gd`** (avatar) e também `pdo_mysql`,
  `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `openssl`
- Composer
- Node.js 20+ e npm
- MySQL 8

### Qual PHP este projeto usa

Há **três** interpretadores no ambiente do autor. O PHP do **XAMPP não é
o PHP do projeto**.

| Uso | Binário | `php.ini` carregado | gd |
| --- | --- | --- | --- |
| Terminal PATH (`php`) | `C:\xampp\php\php.exe` (8.2.12, CLI) | `C:\xampp\php\php.ini` | sim (`extension=gd`) |
| Apache do Laragon (httpd 2.4.66, `PHP/8.3.30`) | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php8apache2_4.dll` | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini` (`PHPIniDir` em `C:\laragon\etc\apache2\mod_php.conf`) | sim |
| Tarefa `GUEASS_schedule` | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` | o mesmo `php.ini` 8.3.30 | sim |

Para artisan/testes alinhados ao Apache, use o `php.exe` 8.3.30 do Laragon
explicitamente. Confira com `php -m` / `php -i` e procure `gd`.

O virtual host `Laravel.test` do Laragon aponta `DocumentRoot` para a
**raiz do repositório** (não `public/`). Há `.htaccess` na raiz que nega
dotfiles e arquivos sensíveis e reescreve para `public/`. O DocumentRoot
**correto** continua sendo `public/`. Corrigir o vhost exige reiniciar o
Apache do Laragon — não é feito automaticamente por este repositório.

## Configuração local

1. Instale as dependências PHP e JavaScript:

   ```bash
   composer install
   npm install
   ```

2. Copie o exemplo de ambiente e gere a chave da aplicação:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. O MySQL **do Laragon** (8.4.3, porta **3308**, banco `gueass_db`) roda
   como serviço Windows `MySQL_Laragon` (início automático). Não abra o
   MySQL pelo Laragon: dois processos na 3308 conflitam. Os serviços
   `MYSQL95` (3306) e `MySQL80` (3307) são de outros usos e não devem ser
   alterados. O `.env.example` continua com `3306` (padrão da ERS); no
   `.env` local use **3308**.

   ```powershell
   Get-Service MySQL_Laragon
   net start MySQL_Laragon
   net stop MySQL_Laragon
   ```

   Para remover o serviço (não apaga o datadir `C:\laragon\data\mysql-8.4`):

   ```powershell
   net stop MySQL_Laragon
   sc.exe delete MySQL_Laragon
   ```

   Confira no `.env`:

   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3308` (neste ambiente; o exemplo do repositório segue `3306`)
   - `DB_DATABASE=gueass_db`
   - `DB_USERNAME` / `DB_PASSWORD` do usuário do projeto (não commitar)

4. Rode as migrations, o catálogo inicial e o link de storage:

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   ```

   Nunca use `migrate:fresh`, `migrate:reset` nem `db:wipe` neste projeto.

   Para **só** povoar ou atualizar o catálogo (idempotente, não apaga
   usuários):

   ```bash
   php artisan db:seed --class=LanguageSeeder
   php artisan db:seed --class=ArchitectureSeeder
   php artisan db:seed --class=TemplateSeeder
   ```

   Contagens atuais: 15 linguagens, 32 frameworks, 16 arquiteturas, 26
   templates ativos. `php artisan db:seed` completo também cria usuários
   de demonstração em local/testing (`DEMO_ADMIN_PASSWORD` /
   `DEMO_USER_PASSWORD` ou senha aleatória, exibida uma vez). Em
   production o seeder **não** cria usuários.

   Em demonstrações, defina `APP_DEBUG=false` no `.env` local. Não altere
   o `.env` por script; o valor fica com quem opera a máquina.

5. Compile os assets e suba o servidor:

   ```bash
   npm run build
   php artisan serve
   ```

   No Laragon, o virtual host do projeto substitui o `php artisan serve`
   (ver nota do `DocumentRoot` acima).

## Administrador inicial

```bash
php artisan gueass:create-admin
```

O comando pede e-mail e senha (ou lê `ADMIN_EMAIL` / `ADMIN_PASSWORD`). A
senha precisa ter pelo menos 8 caracteres, com letras e números. `role`
não é mass-assignable: o comando grava `ADM` via `forceFill`.

## Testes

A suíte usa SQLite em memória (`phpunit.xml`). Não altera o MySQL local.

```bash
php artisan test
```

Linha de base e auditorias de dependências: `docs/baseline-testes.md`.
Regras de negócio: `docs/regras-negocio.md`. Levantamento técnico:
`docs/levantamento-final.md`. Bateria de segurança:
`docs/testes-de-seguranca.md`. O código desta árvore está **congelado**.

Os assets (Bootstrap, Font Awesome, Orbitron, Cropper.js e Chart.js) vêm
do Vite, sem CDN. A CSP usa nonce nos scripts inline do tema.

## Segurança

Controles e limitações: `docs/seguranca-owasp.md`. Em resumo:

- O gerador recusa prompt injection (jailbreak, revelação de system
  prompt, troca de papel) na intenção e nas variáveis, sempre, mesmo com
  `AI_PROVIDER=gemini`.
- Segredos reconhecíveis viram `[REDACTED:tipo]` antes do histórico e de
  qualquer provedor externo.
- Na tela do gerador há a opção de não salvar o prompt. No histórico da
  barra lateral (e no gerador): «Limpar meu histórico» apaga só os
  prompts do usuário logado, com confirmação. No perfil: excluir a conta
  com confirmação de senha.
- No painel admin, «Zerar métricas» não apaga prompts: corta a leitura
  a partir de `metrics_reset_at`. «Considerar todo o histórico» remove
  o corte.
- Cabeçalhos: CSP (`script-src` com nonce; `style-src` ainda inclui
  `'unsafe-inline'` — ver limitações), `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`. HSTS
  só em production com HTTPS.
- Avatar: JPEG/PNG/WEBP, recusa SVG, reprocessa com **gd**.
- Eventos de segurança em lista fechada (`SecurityLogger`); e-mails
  mascarados; senha, token e conteúdo da intenção não entram no canal.

### Como ler os logs

- Aplicação: `storage/logs/laravel.log`.
- Segurança (JSON, um arquivo rotativo): `storage/logs/security.log`
  (Monolog `RotatingFileHandler`, `SECURITY_LOG_DAYS`, padrão 30). Cada
  linha tem o evento (`login_failed`, `prompt_injection_detected`, …),
  `request_id` no contexto compartilhado e e-mail já mascarado.
- Auditoria administrativa: tabela `audit_logs`, tela `/admin/auditoria`
  (somente leitura). Registra quem fez o quê, quando e de qual IP, nas
  alterações de catálogos e nas redefinições de senha.
- Páginas de erro mostram «código de referência» = `X-Request-Id`.

Não copie o conteúdo de `security.log` para issues públicas: ainda pode
conter metadados de tamanho/categoria.

A política de privacidade em `/privacidade` descreve o que o código faz:
dados da conta, histórico com segredos já mascarados, retenção configurável,
exclusão pelo perfil e o fato de o provedor externo de IA ficar desligado
por padrão. Não há exportação do histórico.

### Política de retenção

| Dado | Padrão | Onde |
| --- | --- | --- |
| Histórico de prompts | 90 dias | `PROMPT_RETENTION_DAYS` / `php artisan gueass:prune-prompts` |
| Canal `security` | 30 dias (arquivos rotativos) | `SECURITY_LOG_DAYS` |
| Sessões | 120 minutos | `SESSION_LIFETIME` |
| Conta do usuário | até exclusão no perfil | `DELETE /perfil` |

## Agendamento no Windows (`gueass:prune-prompts`)

O Laravel agenda `gueass:prune-prompts` diariamente (`routes/console.php`).
No Windows isso só corre se `php artisan schedule:run` for chamado com
frequência. Tarefa `GUEASS_schedule` (a cada minuto, utilizador atual,
sem senha armazenada, sem privilégio elevado):

```powershell
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
$wd  = "C:\laragon\www\Laravel"
$action = New-ScheduledTaskAction -Execute $php -Argument "artisan schedule:run" -WorkingDirectory $wd
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName "GUEASS_schedule" -Action $action -Trigger $trigger -Principal $principal
```

Verificar:

```powershell
schtasks /Query /TN "GUEASS_schedule" /V /FO LIST
Get-WinEvent -LogName Microsoft-Windows-TaskScheduler/Operational -MaxEvents 20 |
  Where-Object { $_.Message -like "*GUEASS_schedule*" }
```

Remover:

```powershell
schtasks /Delete /TN "GUEASS_schedule" /F
```

## Variáveis de IA (opcional)

- `AI_PROVIDER=null` — provedor offline (padrão local)
- `AI_PROVIDER=gemini` — exige `GEMINI_API_KEY` (nunca commitar o valor);
  envia só texto já redigido, com `GEMINI_TIMEOUT` (15), `GEMINI_TRIES`
  (2) e `GEMINI_MAX_PAYLOAD_BYTES` (65536). Sem chave, timeout, JSON
  inválido ou trecho suspeito: **fail-closed** (a geração é recusada).
  Não há degradação silenciosa para o provedor `null`.

Outras variáveis documentadas em `.env.example`: `PROMPT_RETENTION_DAYS`,
`SECURITY_LOG_DAYS`, `SECURITY_LOG_STDERR`, `GENERATE_IDEMPOTENCY_SECONDS`,
`TRUSTED_PROXIES` (vazio; nunca `*`), `SESSION_SECURE_COOKIE`.

## Produção (HTTPS) — checklist

O `AppServiceProvider` força `https` só quando `APP_ENV=production`. Em
local (HTTP) isso fica desligado.

- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e secreta
- [ ] `SESSION_SECURE_COOKIE=true` (somente com HTTPS)
- [ ] `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`
- [ ] `TRUSTED_PROXIES` só com IPs reais do proxy; nunca `*`
- [ ] PHP 8.2+ com `gd` no mesmo binário do servidor web
- [ ] `DocumentRoot` do virtual host = diretório `public/`
- [ ] `composer audit` e `npm audit` sem vulnerabilidades conhecidas
- [ ] `php artisan migrate` (sem `fresh`/`wipe`); `storage:link`; `npm run build`
- [ ] Administrador via `php artisan gueass:create-admin` (o seeder **não**
      cria usuários em production)
- [ ] `GEMINI_API_KEY` só se `AI_PROVIDER=gemini`; senão deixe `null`
- [ ] Agendar `php artisan schedule:run` (no Windows, tarefa
      `GUEASS_schedule` com o **mesmo** `php.exe` do servidor)
- [ ] Backups do MySQL; retenção de `security.log` e de prompts conferida
- [ ] HTTPS no proxy/servidor; HSTS é ligado pela aplicação só em
      production com request segura
