#!/bin/sh
# Script de start do container de produção do GUEASS.
#
# 1) Gera /etc/nginx/nginx.conf a partir do template, substituindo
#    ${PORT} pelo valor real fornecido pelo Render em tempo de execução.
# 2) Inicia o PHP-FPM em segundo plano.
# 3) Inicia o Nginx em primeiro plano (processo principal do container).
#
# Não executa migrations, seeders, storage:link nem key:generate — essa
# etapa cuida só da infraestrutura do container.

set -e

# Fallback só para testes locais sem Docker Compose/Render; em produção
# o Render sempre injeta a variável PORT real antes do container iniciar.
: "${PORT:=8080}"

echo "[start.sh] Gerando /etc/nginx/nginx.conf para a porta ${PORT}..."
envsubst '$PORT' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

echo "[start.sh] Validando a configuração do Nginx..."
nginx -t

echo "[start.sh] Iniciando PHP-FPM em segundo plano..."
php-fpm --nodaemonize &

echo "[start.sh] Iniciando Nginx em primeiro plano na porta ${PORT}..."
exec nginx -g "daemon off;"
