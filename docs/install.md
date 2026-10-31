# Instalación

Requisitos: **PHP 8.3** con GD (WebP), Fileinfo y EXIF, **MariaDB 10.11**, HTTPS y un hosting que permita variables de entorno en `.htaccess`.

1. Crear una base de datos y su usuario vacíos.
2. Crear una carpeta de datos **fuera** de la web (por ejemplo `~/familia-datos`) y copiar ahí `examples/db.env.example` como `db.env`
   (y, si querés recuperar el PIN por mail, `mail.env.example` como `mail.env`). Permisos `600`.
3. Publicar `src/web/` como carpeta web y dejar `src/app/`, `src/sql/` y `src/herramientas/` un nivel arriba, fuera de ella.
4. En `web/.htaccess` agregar, como primera línea, el contenido de `examples/htaccess.example` con la ruta real de la carpeta de datos.
5. Abrir el sitio: el esquema y las migraciones se aplican solos.
6. Crear los usuarios desde la línea de comandos (pide el PIN sin eco):
   ```bash
   php src/herramientas/usuario.php alta Nombre admin    # adulto
   php src/herramientas/usuario.php alta Nombre nino     # hijo/a
   ```
7. Probar primero con un `DB_PREFIJO` distinto (por ejemplo `prueba_`) para no tocar los datos reales.

Antes de actualizar el sitio, **hacer un respaldo de la base y del código**.
