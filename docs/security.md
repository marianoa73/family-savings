# Seguridad

Los datos son de menores de edad, así que el diseño parte de guardar lo mínimo.

| Tema | Qué se hace |
|---|---|
| Datos personales | Sólo un nombre de pila y, si hace falta, un mail para recuperar el PIN. Nada bancario |
| PIN | 6 dígitos, se guarda sólo el hash **Argon2id**; se carga sin eco por línea de comandos, nunca por chat |
| Intentos fallidos | Bloqueo por tiempo por usuario y también por IP (la IP se guarda con hash y sal) |
| Sesión | Se cierra a los 10 minutos de inactividad; cookies seguras |
| Formularios | Token CSRF; consultas preparadas (PDO sin emulación) |
| Recuperación | Link de un solo uso por mail, vence a los 30 minutos y tiene tope por hora |
| Archivos | `app/`, `sql/` y la carpeta de datos quedan **fuera** de la web; permisos 600 en secretos |
| Fotos de notas | Se reciben sólo JPG/PNG/WebP, se recodifican (sin EXIF/GPS) y se sirven únicamente al dueño o a un adulto |
| Registro de ingresos | Los adultos ven quién entró, cuándo y desde qué dispositivo, incluidos los intentos fallidos |
| Cabeceras | `X-Frame-Options`, `nosniff`, HSTS; `noindex` para que no aparezca en buscadores |

**Límite conocido:** la subida de fotos tiene tope por cuenta y por hora, pero no una cuota total de disco ni limpieza automática de fotos
huérfanas. Ver [`limitations.md`](limitations.md).
