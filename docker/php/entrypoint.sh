#!/bin/sh
set -e

# Pastikan folder yang ditulis Laravel bisa diakses
if [ -d /var/www/html ]; then
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache 2>/dev/null || true
fi

exec "$@"
