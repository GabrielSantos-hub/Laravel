# Decisões de projeto

## 2026-10-02 — Ambiente local com MySQL 8

Hospedagem no Render e uso de Docker/Postgres abandonados; ambiente alvo =
execução local com MySQL 8, conforme a ERS; mantidas as atualizações de
segurança de dependências da Fase 0 (`league/commonmark`, axios, Vite e
transitivas). A suíte de testes continua em SQLite `:memory:` via
`phpunit.xml`.
