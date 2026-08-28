#!/usr/bin/env bash
set -euo pipefail

# Render は待ち受けポートを $PORT で渡してくる。ローカル確認用に既定値を持たせる。
PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:[0-9]+>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf

# 設定とルートをキャッシュして起動後のレスポンスを速くする。
# 環境変数はビルド時ではなく起動時に揃うため、ここで実行する必要がある。
php artisan config:cache
php artisan route:cache

# マイグレーションは起動時に適用する。
# 無料枠はインスタンスが1つなので同時実行の競合が起きない。
php artisan migrate --force

exec apache2-foreground
