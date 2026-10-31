# Alta y administración de usuarios

No hay pantalla de registro: **las cuentas las crea un adulto con la línea de comandos**, en el servidor, sin que el PIN pase por chat ni por pantalla.

```bash
php src/herramientas/usuario.php alta <nombre> <admin|nino>   # crea la cuenta
php src/herramientas/usuario.php pin <nombre>                 # PIN provisorio (obliga a cambiarlo)
php src/herramientas/usuario.php mail <nombre>                # carga o quita el mail de recuperación
php src/herramientas/usuario.php desbloquear <nombre|ip>      # quita un bloqueo
```

## Cómo se pide la información
- Se lee por entrada estándar **sin eco** (`stty -echo` en terminal; por pipe sin ejecutar `stty`). Nunca se pasa por argumentos de línea de comandos.
- Mensajes de error genéricos; no se imprime ningún dato privado.
- Sólo corre por CLI (`PHP_SAPI`); por web devuelve 404.

## Datos de cada usuario
| Dato | Regla |
|---|---|
| Nombre | 1–40 caracteres: letras latinas, números, espacios, punto, guion o guion bajo; único |
| Rol | `admin` (adulto) o `nino` (hijo/a) |
| PIN | 6 dígitos con las reglas de [ingreso](login.md); sólo se guarda el hash |
| Mail | Opcional, validado y de hasta 120 caracteres; sólo sirve para [recuperar el PIN](recovery.md) |
| Mensualidad | Monto para gastos, distinto por usuario |
| Tema | Claro por defecto u oscuro, guardado en el perfil |

## Primer ingreso
El PIN provisorio activa `debe_cambiar`: hasta cambiarlo, el usuario no puede cargar notas, cambiar avatar ni usar el resto del sitio.

## Qué ve cada rol
Ver el cuadro de roles en el [README](../README.md#cómo-trabajan-los-adultos) y el detalle de las pantallas en [cómo funciona](how-it-works.md).
