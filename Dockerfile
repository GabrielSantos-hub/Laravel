# syntax=docker/dockerfile:1
#
# Imagem de produção do GUEASS (Laravel 12) para o Render.
# Duas etapas:
#   1) frontend-builder: compila os assets com Vite 7 + Tailwind 4 (Node 20).
#   2) production: PHP-FPM + Nginx, com apenas o necessário para servir a
#      aplicação (sem devDependencies do Node, sem dependências de dev do
#      Composer, sem .env, sem .git).
#
# Nada de lógica da aplicação é alterado aqui: nenhuma migration, seed,
# storage:link ou key:generate é executado durante o build ou o start.

########################################################################
# Etapa 1 — build dos assets de produção (Vite 7 + Tailwind 4)
# Esta etapa não faz parte da imagem final; existe só para gerar
# public/build/manifest.json.
########################################################################
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Copia primeiro só os manifestos de dependências para aproveitar cache
# de camada quando resources/ mudar sem mudar as dependências.
COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

# Gera public/build/manifest.json e os arquivos versionados de CSS/JS.
RUN npm run build


########################################################################
# Etapa 2 — imagem final de produção: PHP-FPM + Nginx
########################################################################
FROM php:8.2-fpm-alpine AS production

LABEL description="GUEASS (Laravel 12) - PHP-FPM + Nginx para o Render"

# ------------------------------------------------------------------
# Sistema, Nginx e extensões PHP
# ------------------------------------------------------------------
# - nginx: servidor web que recebe as requisições do Render.
# - gettext: fornece o `envsubst`, usado pelo start.sh para injetar a
#   porta dinâmica ($PORT) no nginx.conf em tempo de execução.
# - libpng-dev / libjpeg-turbo-dev / freetype-dev: bibliotecas exigidas
#   para compilar a extensão gd com suporte a PNG/JPEG (upload de avatar).
# - postgresql-dev: necessária para compilar pdo_pgsql.
# - oniguruma-dev: necessária para compilar mbstring.
# - $PHPIZE_DEPS (toolchain de build): instalada como pacote virtual e
#   removida ao final, para manter a imagem final pequena.
#
# ctype, tokenizer, xml (dom/simplexml/xmlwriter), fileinfo, openssl e o
# núcleo do pdo já vêm habilitados por padrão na imagem oficial
# php:8.2-fpm-alpine. Só pdo_pgsql, mbstring e gd exigem instalação
# explícita porque dependem de bibliotecas de sistema extras.
RUN apk add --no-cache \
        nginx \
        gettext \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        postgresql-dev \
        oniguruma-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        mbstring \
        gd \
    && apk del --no-cache .build-deps

# Confere, ainda durante o build, que todas as extensões exigidas pelo
# Laravel/GUEASS estão realmente presentes. Se faltar alguma, o build
# falha aqui — com o nome exato da extensão ausente — em vez de o
# problema aparecer só em produção no Render.
RUN php -r ' \
    $required = ["pdo", "pdo_pgsql", "mbstring", "tokenizer", "xml", "ctype", "fileinfo", "gd", "openssl"]; \
    $missing = array_filter($required, fn ($ext) => !extension_loaded($ext)); \
    if ($missing) { \
        fwrite(STDERR, "Extensoes PHP obrigatorias ausentes: " . implode(", ", $missing) . PHP_EOL); \
        exit(1); \
    } \
    fwrite(STDOUT, "OK: todas as extensoes PHP obrigatorias estao presentes." . PHP_EOL); \
'

WORKDIR /var/www/html

# Binário oficial do Composer, copiado da imagem oficial (nenhum script
# externo é baixado ou executado).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copia o código da aplicação (respeitando o .dockerignore: sem vendor/,
# node_modules/, .env, .git, storage de desenvolvimento, testes, etc.).
# O `composer install` roda depois de copiar o código porque o script
# post-autoload-dump do projeto executa "php artisan package:discover",
# que exige app/, bootstrap/ e routes/ já presentes no diretório.
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Assets de produção gerados na Etapa 1 (public/build/manifest.json).
# Continuam fora do Git — o .gitignore atual não foi alterado; eles só
# existem dentro desta imagem Docker.
COPY --from=frontend-builder /app/public/build ./public/build

# Garante a existência da estrutura de diretórios que o Laravel precisa
# poder escrever em runtime (logs, cache, sessões, views compiladas),
# independentemente do que o .dockerignore filtrou, e ajusta o
# proprietário para o usuário que vai executar PHP-FPM/Nginx.
RUN mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Template do Nginx. A porta real (${PORT}) só é conhecida quando o
# Render inicia o container — por isso a substituição acontece no
# start.sh (em runtime), não durante o build da imagem.
COPY nginx.conf /etc/nginx/templates/nginx.conf.template

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Container roda inteiramente como usuário não-root: nem PHP-FPM nem
# Nginx precisam de privilégio de root, já que ambos já iniciam como
# www-data (o mesmo usuário configurado no pool padrão do PHP-FPM).
USER www-data

# Apenas documental: a porta efetiva usada pelo Nginx vem da variável
# de ambiente PORT, lida em runtime pelo start.sh/nginx.conf — o Render
# decide esse valor, não a imagem.
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/start.sh"]
