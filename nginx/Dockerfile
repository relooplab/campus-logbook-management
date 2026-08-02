# ============================================================================
# Nginx image dengan konfigurasi vhost yang di-BAKE ke image.
#
# Konfigurasi sengaja di-bake (bukan bind-mount) agar stack dapat berjalan
# saat di-deploy dari Portainer: file project stack tersimpan di dalam
# filesystem container Portainer, sedangkan bind-mount di-resolve oleh
# Docker daemon terhadap filesystem HOST, sehingga mount relatif gagal.
# ============================================================================
FROM nginx:1.27-alpine
COPY default.conf /etc/nginx/conf.d/default.conf
