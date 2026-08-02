#!/bin/sh
# ============================================================================
# Thesis Logbook Management — entrypoint
# 1. Pastikan named volume /var/www/public (dibagi ke nginx) berisi aset
#    frontend + pdfjs + css yang di-bake di image (dari /public-dist).
# 2. Buat symlink storage -> public/storage agar foto profil dapat diakses.
# ============================================================================
set -e

# Isi public/ dari salinan bawaan image jika volume kosong.
if [ -z "$(ls -A /var/www/public 2>/dev/null)" ]; then
    echo ">>> Mengisi public/ dari image (aset frontend)..."
    cp -a /public-dist/. /var/www/public/
fi

# Pastikan storage:link selalu ada.
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link || true
fi

# Teruskan ke perintah utama (php-fpm / queue / scheduler / reverb).
exec "$@"
