# GUEASS

Gerador de prompts de software (Laravel 12 / PHP 8.2). Ambiente alvo: execução
local com MySQL 8, conforme a ERS.

## Requisitos

- PHP 8.2+ (extensões: `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `openssl`)
- Composer
- Node.js 20+ e npm
- MySQL 8

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

   O seeder cria o catálogo (linguagens, arquiteturas, templates). Em
   local/testing também cria usuários de demonstração com senha vinda de
   `DEMO_ADMIN_PASSWORD` / `DEMO_USER_PASSWORD` ou aleatória (exibida uma
   vez no terminal). Em production o seeder **não** cria usuários.

5. Compile os assets e suba o servidor:

   ```bash
   npm run build
   php artisan serve
   ```

   No Laragon, o virtual host do projeto substitui o `php artisan serve`.

## Administrador inicial

```bash
php artisan gueass:create-admin
```

O comando pede e-mail e senha (ou lê `ADMIN_EMAIL` / `ADMIN_PASSWORD`). A
senha precisa ter pelo menos 8 caracteres, com letras e números.

## Testes

A suíte usa SQLite em memória (`phpunit.xml`). Não altera o MySQL local.

```bash
php artisan test
```

Linha de base e auditorias de dependências: `docs/baseline-testes.md`.

Os assets (Bootstrap, Font Awesome, Orbitron, Cropper.js e Chart.js) vêm
do Vite, sem CDN. A CSP usa nonce nos scripts inline do tema.

O gerador recusa prompt injection (jailbreak, revelação de system prompt,
troca de papel) na intenção e nas variáveis, sempre, mesmo com
`AI_PROVIDER=gemini`. Segredos reconhecíveis são substituídos por
`[REDACTED:tipo]` antes do histórico e antes de qualquer provedor externo.
Na tela do gerador há a opção de não salvar o prompt. No perfil: exportar
o histórico em JSON e excluir a conta com confirmação de senha. O comando
`php artisan gueass:prune-prompts` remove prompts mais antigos que
`PROMPT_RETENTION_DAYS` (padrão 90).

## Produção (HTTPS)

O `AppServiceProvider` força `https` só quando `APP_ENV=production`. Em
local (HTTP) isso fica desligado. Variáveis que exigem HTTPS também
nascem desligadas:

- `SESSION_SECURE_COOKIE=false` no exemplo; `true` só em production com HTTPS
- `TRUSTED_PROXIES` vazio (não confiar em todos os proxies)
- `APP_DEBUG=false` em production
- `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`

## Variáveis de IA (opcional)

- `AI_PROVIDER=null` — provedor offline (padrão local)
- `AI_PROVIDER=gemini` — exige `GEMINI_API_KEY` (nunca commitar o valor);
  envia só texto já redigido, com `GEMINI_TIMEOUT` e `GEMINI_MAX_PAYLOAD_BYTES`
