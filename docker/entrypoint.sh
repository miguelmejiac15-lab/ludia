#!/bin/sh
# Arranque del contenedor de Ludia.
#
# Hace tres cosas antes de levantar Apache, en este orden y por este motivo:
#
#   1. Espera a la base de datos. Vive en OTRO contenedor, así que al desplegar
#      puede tardar unos segundos en aceptar conexiones. Sin esperar, el
#      instalador fallaría por una carrera de arranque y no por un problema real.
#   2. Arregla los permisos de `uploads`. Es un volumen persistente: la primera
#      vez llega vacío y propiedad de root, y Apache corre como www-data. Sin
#      esto, subir una imagen falla con un error de permisos que parece del
#      código.
#   3. Corre las migraciones. Son idempotentes a propósito (así está escrito en
#      el masterplan), de modo que esto se ejecuta en CADA despliegue sin
#      riesgo: si no hay nada nuevo, no hace nada.

set -eu

echo "[ludia] arrancando"

# ---------------------------------------------------------------------------
# 1. Esperar a la base de datos
# ---------------------------------------------------------------------------
espera=0
limite=60

until php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = (int) (getenv("DB_PORT") ?: 3306);
    $conexion = @fsockopen($host, $port, $e, $m, 2);
    exit($conexion ? 0 : 1);
' 2>/dev/null; do
    espera=$((espera + 2))
    if [ "$espera" -ge "$limite" ]; then
        echo "[ludia] ERROR: la base de datos en ${DB_HOST:-?}:${DB_PORT:-3306} no responde tras ${limite}s."
        echo "[ludia] Revisa que el contenedor de MariaDB esté arriba y en la misma red docker."
        exit 1
    fi
    echo "[ludia] esperando la base de datos... (${espera}s)"
    sleep 2
done

echo "[ludia] base de datos accesible"

# ---------------------------------------------------------------------------
# 2. Permisos del volumen de imágenes
# ---------------------------------------------------------------------------
if [ -d /var/www/html/uploads ]; then
    chown -R www-data:www-data /var/www/html/uploads || \
        echo "[ludia] aviso: no se pudieron ajustar los permisos de uploads"
fi

# ---------------------------------------------------------------------------
# 3. Esquema y migraciones
#
# Se cae a propósito si falla. Un despliegue fallido se ve y se arregla; un
# sitio sirviendo páginas sobre un esquema a medio migrar da errores raros
# durante días y nadie sabe de dónde vienen.
# ---------------------------------------------------------------------------
echo "[ludia] aplicando esquema y migraciones"
if ! php /var/www/html/sql/instalar.php; then
    echo "[ludia] ERROR: fallaron las migraciones. No se levanta el sitio."
    exit 1
fi

echo "[ludia] listo, levantando Apache"
exec "$@"
