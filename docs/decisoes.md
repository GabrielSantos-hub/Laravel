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
MYSQL95 do Windows não foram alterados. Para subir o servidor do Laragon:

`C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe --defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini`
