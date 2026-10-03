# Decisões de projeto

## 2026-10-02 — Ambiente local com MySQL 8

Hospedagem no Render e uso de Docker/Postgres abandonados; ambiente alvo =
execução local com MySQL 8, conforme a ERS; mantidas as atualizações de
segurança de dependências da Fase 0 (`league/commonmark`, axios, Vite e
transitivas). A suíte de testes continua em SQLite `:memory:` via
`phpunit.xml`.

## 2026-10-02 — Porta MySQL local 3308

A porta 3306 está ocupada pelo serviço Windows MYSQL95. O MySQL do Laragon
(8.4.3, datadir `C:\laragon\data\mysql-8.4`) foi configurado em 3308 no
`my.ini` (backup em `my.ini.bak-data`). O `.env` local usa 3308; o
`.env.example` permanece em 3306, padrão da ERS. Os serviços MySQL80 e
MYSQL95 do Windows não foram alterados.

## 2026-10-02 — Serviço Windows MySQL_Laragon

O `mysqld` do Laragon 8.4.3 passou a ser o serviço Windows `MySQL_Laragon`
(exibição: “MySQL Laragon 8.4 (GUEASS)”), com `--defaults-file` no `my.ini`
da porta 3308 e datadir `C:\laragon\data\mysql-8.4`. Início: automático
atrasado (`delayed-auto`). O Laragon **não** deve iniciar o próprio MySQL,
para não haver dois processos na 3308. MYSQL95 e MySQL80 não foram
alterados.

Verificar: `Get-Service MySQL_Laragon`. Parar/iniciar: `net stop` /
`net start MySQL_Laragon`. Remover (sem apagar o datadir):
`net stop MySQL_Laragon` e depois `sc.exe delete MySQL_Laragon`.

## 2026-10-02 — Três runtimes PHP e extensão gd

O Apache do Laragon (httpd 2.4.66, `PHP/8.3.30`) carrega
`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini` (`PHPIniDir` em
`C:\laragon\etc\apache2\mod_php.conf`). A tarefa `GUEASS_schedule` usa o
mesmo `php.exe`. O `php` do terminal PATH é o XAMPP
`C:\xampp\php\php.exe` (8.2.12, `C:\xampp\php\php.ini`) e **não** é o PHP
do projeto. `extension=gd` já estava descomentada nos dois `php.ini`;
nenhuma linha foi alterada e o Apache não precisou ser reiniciado. Upload
de avatar via HTTP em `http://laravel.test/public` devolveu 200 com PNG
reprocessado.

## 2026-10-02 — CSP `style-src 'unsafe-inline'` mantido

Há 87 atributos `style=""` em 20 blades da aplicação (94 se contar o
`welcome.blade.php` de scaffold, removido na Fase 4). As páginas de erro
ainda usam um bloco `<style>` em `resources/views/errors/layout.blade.php`.
Mover tudo para classes/Vite sem regressão visual é esforço alto. A
diretiva permanece; risco residual em `docs/seguranca-owasp.md`.

## 2026-10-02 — Fase 2: prompt injection e dados sensíveis

Detecção de prompt injection centralizada no `InputSanityGuardrail` (fonte
única, independente do `AI_PROVIDER`). A intenção entra no envelope como
dado delimitado. Segredos viram `[REDACTED:tipo]` antes do histórico e do
Gemini. Retenção do histórico: `PROMPT_RETENTION_DAYS` (padrão 90).
Provedor padrão continua `null` (offline).

## 2026-10-02 — Corpus adversarial independente (2.B)

Novo conjunto de 40 ataques de evasão e 40 pedidos legítimos, em arquivo
próprio, sem reutilizar o corpus da Fase 2. Detector reforçado
(paráfrase, duas frases, JSON `role:system`, hex/base64 como política).
Taxa no conjunto novo: 40/40 bloqueados e 40/40 aceitos; o corpus
original permaneceu sem falso positivo. Limitações residuais em
`docs/seguranca-owasp.md`.

## 2026-10-02 — Fase 3: logs, auditoria e resiliência

Canal `security` em JSON (30 dias, stderr opcional). `SecurityLogger` com
lista fechada de eventos, e-mails mascarados e processador anti-CRLF.
Request ID em `X-Request-Id`. Tabela `audit_logs` somente inserção, tela
admin somente leitura. Geração: persistência em try/catch, idempotência
de 5 s, timeout do provedor com mensagem amigável. Stack do histórico
deduz IDs do catálogo. Avatar reprocessado com GD; SVG recusado.

## 2026-10-02 — Fase 4: fechamento

Modo lean documentado (60 caracteres / 10 tokens; `LEAN_MAX_LINES=25` só
como invariante do envelope). Gemini continua fail-closed. Scaffold
`welcome.blade.php` e `ExampleTest` removidos; CRUDs de catálogo sem
`HasMiddleware` duplicado. Páginas 403/404/419/429/500 com `request_id`.
Levantamento final em `docs/levantamento-final.md`.

## 2026-10-03 — Ajustes finais e congelamento

- **Exportação do histórico removida** (`GET /perfil/exportar`, evento
  `data_exported`, botão do perfil). A exclusão de conta permanece.
- **Auditoria mantida.** A tela explica que registra quem, o quê, quando
  e de qual IP, para alterações de catálogos e redefinições de senha.
- **Página 404 mantida** para endereço inexistente e para GET/HEAD em
  URI que só aceita escrita (não revela a rota). 405 amigável para os
  demais métodos não permitidos.
- **Política de privacidade simplificada**, factual, sem prometer
  exportação; retenção lida de `config()`.
- **Catálogo ampliado** (15 linguagens, 32 frameworks, 16 arquiteturas,
  26 templates ativos). Seeders idempotentes por slug/nome.

## 2026-10-03 — Rodada final (código congelado)

- **Limpar meu histórico** apaga só os prompts do usuário autenticado.
  IDs no corpo são ignorados. Evento `history_cleared` com a contagem,
  nunca o conteúdo.
- **Zerar métricas** não apaga dados: grava `metrics_reset_at` em
  `app_settings`. O painel filtra `created_at >=` o corte e mostra
  «Métricas consideradas desde». «Considerar todo o histórico» remove
  o corte (`admin_metrics_reset` / `admin_metrics_reset_cleared`).
- **Bateria de segurança única** (12 categorias) em
  `docs/testes-de-seguranca.md`; uma segunda passada só no que falhou.
- **Código morto** documentado em `docs/limpeza-codigo-morto.md`.
  Comentários excessivos removidos (prova `php -w` idêntica).
- **Código congelado** após esta rodada: sem novas funcionalidades
  nesta árvore.

