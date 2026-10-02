#!/bin/sh
# Start do container de produção do GUEASS (Render).
#
# Ordem: valida segredos de ambiente → gera nginx.conf → migrate --force
# → PHP-FPM → Nginx em foreground.
# Não executa seed, key:generate, nem imprime o valor de variáveis.

set -e

if [ -z "${APP_KEY:-}" ]; then
    echo "[start.sh] ERRO: APP_KEY ausente no ambiente. Defina a variavel no painel do Render. Abortando." >&2
    exit 1
fi

# Fallback só para testes locais; o Render injeta PORT em runtime.
# O valor não é ecoado (regra: não imprimir variáveis).
: "${PORT:=8080}"

echo "[start.sh] Gerando configuracao do Nginx..."
envsubst '$PORT' < /etc/nginx/templates/nginx.conf.template > /tmp/nginx.conf

echo "[start.sh] Validando a configuracao do Nginx..."
nginx -t -c /tmp/nginx.conf

# SQLite: garante o arquivo vazio quando o driver for sqlite e o path
# não for :memory:. Em MySQL/Postgres o arquivo não é criado.
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
    db_file="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    if [ "$db_file" != ":memory:" ] && [ ! -f "$db_file" ]; then
        mkdir -p "$(dirname "$db_file")"
        touch "$db_file"
    fi
fi

echo "[start.sh] Aplicando migrations (migrate --force, sem seed)..."
php artisan migrate --force --no-interaction

echo "[start.sh] Iniciando PHP-FPM em segundo plano..."
php-fpm --nodaemonize &

echo "[start.sh] Iniciando Nginx em primeiro plano..."
exec nginx -c /tmp/nginx.conf -g "daemon off;"
