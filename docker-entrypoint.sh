#!/bin/sh
# ============================================================================
# Thesis Logbook Management — entrypoint
# 1. Pastikan named volume /var/www/public (dibagi ke nginx) berisi aset
#    frontend + pdfjs + css yang di-bake di image (dari /public-dist).
# 2. Buat symlink storage -> public/storage agar foto profil dapat diakses.
# ============================================================================
set -e

# Selalu sinkronkan aset bawaan image (build/pdfjs/css) dari /public-dist.
# Tanpa sinkronisasi ulang, named volume public/ menyimpan aset Vite yang STALE
# setelah image diperbarui (hash aset berubah) -> viewer PDF/anotasi rusak.
# Hapus build/pdfjs/css lama agar tidak menumpuk, lalu salin ulang dari image.
rm -rf /var/www/public/build /var/www/public/pdfjs /var/www/public/css
cp -a /public-dist/. /var/www/public/

# Pastikan storage:link selalu ada.
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link || true
fi

# Teruskan ke perintah utama (php-fpm / queue / scheduler / reverb).
exec "$@"
