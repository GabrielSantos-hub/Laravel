# syntax=docker/dockerfile:1
#
# Imagem de produção do GUEASS (Laravel 12) para o Render.
# Duas etapas:
#   1) frontend-builder: compila os assets com Vite 7 + Tailwind 4 (Node 20).
#   2) production: PHP-FPM + Nginx, com apenas o necessário para servir a
#      aplicação (sem devDependencies do Node, sem dependências de dev do
#      Composer, sem .env, sem .git, sem storage/logs do host).
#
# Segredos (APP_KEY, GEMINI_API_KEY, senhas de banco) entram SOMENTE
# por variáveis de ambiente em runtime. O build não executa key:generate.

########################################################################
# Etapa 1 — build dos assets de produção (Vite 7 + Tailwind 4)
########################################################################
FROM node:20-alpine AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build


########################################################################
# Etapa 2 — imagem final de produção: PHP-FPM + Nginx
########################################################################
FROM php:8.2-fpm-alpine AS production

LABEL description="GUEASS (Laravel 12) - PHP-FPM + Nginx para o Render"

# - nginx / gettext (envsubst para ${PORT})
# - gd (PNG/JPEG) para upload de avatar
# - pdo_pgsql, pdo_mysql, pdo_sqlite: o app usa SQLite ou MySQL
#   conforme o ambiente; pgsql permanece para compatibilidade com o Render
RUN apk add --no-cache \
        nginx \
        gettext \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        postgresql-dev \
        oniguruma-dev \
        sqlite-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        gd \
    && apk del --no-cache .build-deps

RUN php -r ' \
    $required = ["pdo", "pdo_pgsql", "pdo_mysql", "pdo_sqlite", "mbstring", "tokenizer", "xml", "ctype", "fileinfo", "gd", "openssl"]; \
    $missing = array_filter($required, fn ($ext) => !extension_loaded($ext)); \
    if ($missing) { \
        fwrite(STDERR, "Extensoes PHP obrigatorias ausentes: " . implode(", ", $missing) . PHP_EOL); \
        exit(1); \
    } \
    fwrite(STDOUT, "OK: todas as extensoes PHP obrigatorias estao presentes." . PHP_EOL); \
'

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && rm -f /usr/bin/composer

COPY --from=frontend-builder /app/public/build ./public/build

RUN mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /etc/nginx/templates \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY nginx.conf /etc/nginx/templates/nginx.conf.template
COPY docker/php-fpm-www-data.conf /usr/local/etc/php-fpm.d/zz-www-data.conf
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Processo do container sem root. PHP-FPM workers e Nginx usam www-data.
# nginx.conf gerado em /tmp (gravável). Porta do Render é >1024.
USER www-data

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/start.sh"]
