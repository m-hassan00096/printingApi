#!/bin/sh
set -eu

cd /var/www/html

# Create writable Laravel directories, including after a persistent volume is mounted.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Migrations are deliberately manual, not run on each container restart.
exec apache2-foreground
