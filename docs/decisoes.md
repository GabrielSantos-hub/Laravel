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

## 2026-10-02 — Fase 2: prompt injection e dados sensíveis

Detecção de prompt injection centralizada no `InputSanityGuardrail` (fonte
única, independente do `AI_PROVIDER`). A intenção entra no envelope como
dado delimitado. Segredos viram `[REDACTED:tipo]` antes do histórico e do
Gemini. Retenção do histórico: `PROMPT_RETENTION_DAYS` (padrão 90).
Provedor padrão continua `null` (offline).
