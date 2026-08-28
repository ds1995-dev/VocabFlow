# 本番用イメージ。Render は PHP のネイティブランタイムを持たないため Docker で動かす。
# Node のビルドステージは不要（welcome.blade.php に Vite マニフェスト非存在時の
# フォールバックがあり、API-only の本アプリではビルド成果物を使わない）。
FROM php:8.4-apache

# Laravel の動作に必要な PHP 拡張を入れる。
# pdo_mysql は必須。intl/zip/bcmath は Laravel の一般的な要件、opcache は本番の実行速度用。
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libicu-dev \
        unzip \
        ca-certificates \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl opcache bcmath \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# 本番向けの php.ini を採用する
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# public/ を DocumentRoot にし、Laravel のルーティングに必要な rewrite を有効化する
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# 依存だけ先に入れてレイヤーキャッシュを効かせる。
# この時点ではアプリコードが無いので、スクリプトとオートローダ生成は後回しにする。
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# オートローダを最適化し、Laravel が書き込むディレクトリを用意して権限を渡す
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
