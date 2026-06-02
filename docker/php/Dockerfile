# Estágio 1 — compila os assets do frontend com Node 22 e Vite
FROM node:24-alpine3.23 AS assets

# Diretório de trabalho do build de assets
WORKDIR /build

# Copia metadados do npm antes do código para maximizar cache de layer
COPY package.json package-lock.json ./

# Instala dependências de forma determinística pelo lockfile
RUN npm ci --no-audit --no-fund

# Copia o arquivo de configuração do Vite
COPY vite.config.js ./

# Copia o diretório de fontes do frontend
COPY resources ./resources

# Gera os assets de produção com hash em public/assets
RUN npm run build

# Estágio 2 — runtime do PHP-FPM 8.5 sobre Alpine 3.23
FROM php:8.5.6-fpm-alpine3.23

# Variáveis de ambiente fixadas na imagem
ENV TZ=${TZ} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/root/.composer \
    COMPOSER_CACHE_DIR=/root/.composer/cache \
    COMPOSER_NO_INTERACTION=1

# Pacotes runtime essenciais que ficam permanentes na imagem
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    tzdata

# Instala todas as extensões PHP em um único bloco para reduzir layers
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    postgresql-dev icu-dev oniguruma-dev \
    curl-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql intl mbstring gd curl zip \
    && (php -m | grep -qi '^Zend OPcache$' || docker-php-ext-enable opcache) \
    && pecl install igbinary && docker-php-ext-enable igbinary \
    && pecl install redis    && docker-php-ext-enable redis \
    && pecl install pcov     && docker-php-ext-enable pcov \
    && apk add --no-cache --virtual .runtime-deps \
    libpq icu-libs oniguruma libjpeg-turbo libpng freetype \
    libcurl libzip \
    && apk del .build-deps \
    && rm -rf /tmp/pear ~/.pearrc /var/cache/apk/*

# Cria o diretório de cache em disco usado pelo OPcache file_cache
RUN mkdir -p /tmp/opcache && chmod 1777 /tmp/opcache

# Copia o php.ini customizado para a pasta de configurações adicionais
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copia o binário do Composer da imagem oficial sem instalar manualmente
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Define padrões globais do Composer para builds rápidos
RUN composer config --global process-timeout 1800 \
    && composer config --global preferred-install dist \
    && composer config --global optimize-autoloader true \
    && mkdir -p /root/.composer/cache

# Diretório padrão de trabalho dentro do container
WORKDIR /var/www

# Copia o código da aplicação PHP para a imagem
COPY . .

# Copia os assets já compilados do estágio 1 sobre o diretório public/assets
COPY --from=assets /build/public/assets ./public/assets

# Porta TCP onde o PHP-FPM escuta
EXPOSE 9000

# Comando padrão do container — inicia o PHP-FPM em foreground
CMD ["php-fpm"]