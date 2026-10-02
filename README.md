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

3. Crie o banco MySQL `gueass_db` e confira no `.env`:

   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=gueass_db`
   - `DB_USERNAME=root`
   - `DB_PASSWORD` vazio (ou a senha local, se houver)

4. Rode as migrations, o catálogo inicial e o link de storage:

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   ```

   O seeder cria o catálogo (linguagens, arquiteturas, templates) e usuários
   de demonstração definidos em `database/seeders/DatabaseSeeder.php`.

5. Compile os assets e suba o servidor:

   ```bash
   npm run build
   php artisan serve
   ```

   No Laragon, o virtual host do projeto substitui o `php artisan serve`.

## Testes

A suíte usa SQLite em memória (`phpunit.xml`). Não altera o MySQL local.

```bash
php artisan test
```

Linha de base e auditorias de dependências: `docs/baseline-testes.md`.

## Variáveis de IA (opcional)

- `AI_PROVIDER=null` — provedor offline (padrão local)
- `AI_PROVIDER=gemini` — exige `GEMINI_API_KEY` (nunca commitar o valor)
