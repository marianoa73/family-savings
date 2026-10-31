# Family Savings

**Un mini banco familiar para que los chicos aprendan a ahorrar: ellos ven su plata, sus logros y sus metas; los adultos aprueban y administran.**
Sitio web propio (PHP + MariaDB) con estética de plataforma de trading, pensado para que los chicos se vayan familiarizando con cómo se ven los brokers.

[English summary](README.en.md) · Autor: Mariano Araujo · Licencia MIT

## En un minuto

1. **Problema:** a los chicos se les da plata "por tandas" y no ven nada: no hay registro de qué lograron, cuánto juntaron ni para qué.
   Tampoco pueden tener una cuenta bancaria propia.
2. **Idea:** un libro de cuentas familiar que **simula un banco**. Los adultos son los administradores; cada hijo/a tiene su
   cuenta, ve su saldo, su historial y sus metas.
3. **Qué hace:** convierte los logros (notas, ayuda en la casa, estudio, regalos) en premios **visibles**, y completa el circuito:
   **ganar → ahorrar → pedir el rescate → obtener**.
4. **Qué no es:** no se conecta a ningún banco ni guarda datos bancarios. Es un registro informativo; el dinero real lo entregan los adultos.

![El circuito del ahorro: ganar, aprobar, ahorrar, pedir rescate, entregar y obtener](docs/assets/01-circuito-ahorro.png)

## Cómo se ve

Capturas del sitio funcionando, con usuarios y movimientos **ficticios** (Ana, Beto y Tutor). **Hacé clic en una miniatura para verla en tamaño completo.**

<table>
<tr><td align="center" valign="top"><a href="docs/screenshots/01-ingreso.png"><img src="docs/screenshots/thumbs/01-ingreso.jpg" alt="Ingreso: nombre y PIN de 6 dígitos" width="280"></a><br><sub>Ingreso: nombre y PIN de 6 dígitos</sub></td><td align="center" valign="top"><a href="docs/screenshots/02-recuperacion.png"><img src="docs/screenshots/thumbs/02-recuperacion.jpg" alt="Recuperación del PIN por mail" width="280"></a><br><sub>Recuperación del PIN por mail</sub></td><td align="center" valign="top"><a href="docs/screenshots/03-tablero-hijo.png"><img src="docs/screenshots/thumbs/03-tablero-hijo.jpg" alt="Tablero del hijo/a: saldo, gráfico y movimientos" width="280"></a><br><sub>Tablero del hijo/a: saldo, gráfico y movimientos</sub></td></tr>
<tr><td align="center" valign="top"><a href="docs/screenshots/04-cargar-logro.png"><img src="docs/screenshots/thumbs/04-cargar-logro.jpg" alt="Cargar una nota con su premio" width="280"></a><br><sub>Cargar una nota con su premio</sub></td><td align="center" valign="top"><a href="docs/screenshots/05-metas-rescate.png"><img src="docs/screenshots/thumbs/05-metas-rescate.jpg" alt="Metas con barra de avance y pedido de rescate" width="280"></a><br><sub>Metas con barra de avance y pedido de rescate</sub></td><td align="center" valign="top"><a href="docs/screenshots/06-adulto-pendientes.png"><img src="docs/screenshots/thumbs/06-adulto-pendientes.jpg" alt="Adulto: aprobar, rechazar o corregir" width="280"></a><br><sub>Adulto: aprobar, rechazar o corregir</sub></td></tr>
<tr><td align="center" valign="top"><a href="docs/screenshots/07-adulto-resumen.png"><img src="docs/screenshots/thumbs/07-adulto-resumen.jpg" alt="Adulto: resumen de saldos" width="280"></a><br><sub>Adulto: resumen de saldos</sub></td><td align="center" valign="top"><a href="docs/screenshots/08-adulto-accesos.png"><img src="docs/screenshots/thumbs/08-adulto-accesos.jpg" alt="Adulto: registro de ingresos" width="280"></a><br><sub>Adulto: registro de ingresos</sub></td><td align="center" valign="top"><a href="docs/screenshots/09-tablero-hijo-oscuro.png"><img src="docs/screenshots/thumbs/09-tablero-hijo-oscuro.jpg" alt="Tema oscuro" width="280"></a><br><sub>Tema oscuro</sub></td></tr>
<tr><td align="center" valign="top"><a href="docs/screenshots/10-tablero-hijo-movil.png"><img src="docs/screenshots/thumbs/10-tablero-hijo-movil.jpg" alt="En el celular" width="240"></a><br><sub>En el celular</sub></td></tr>
</table>

Dibujo ilustrativo del tablero de un hijo/a:

![Tablero de ejemplo: saldo disponible, gráfico Este mes, últimos movimientos y meta Bicicleta al 65 %](docs/assets/04-grafico-saldo.png)

## Arquitectura

El código de la aplicación, los datos y los secretos quedan **fuera** de la carpeta que publica el servidor web.

![Arquitectura: navegador, carpeta web, aplicación PHP, MariaDB, carpeta de datos, SMTP y herramienta de línea de comandos](docs/assets/03-arquitectura.png)

Ingreso y recuperación del PIN, en detalle en [`docs/login.md`](docs/login.md) y [`docs/recovery.md`](docs/recovery.md):

![Flujos de ingreso y de recuperación del PIN](docs/assets/02-login-y-recuperacion.png)

## Qué aprenden los chicos

- **Ahorrar con un objetivo:** metas con barra de avance ("llegaste" cuando se alcanza el monto).
- **Que el esfuerzo tiene recompensa visible:** cada nota o tarea queda anotada con su premio, no se pierde en una mano que da plata.
- **Estimular la ayuda en la casa y el estudio:** las tareas y las notas tienen un valor configurable por los adultos.
- **Pedir antes de gastar:** para usar el ahorro hay que pedir un rescate con un motivo; el monto queda reservado hasta que un adulto lo entrega.
- **Convivir con plataformas financieras:** tablero con saldo, gráfico del mes, lista de movimientos con importe y estado, tema claro u oscuro.

## Cómo trabajan los adultos

Los chicos cargan lo que lograron (con foto de la nota, si quieren) y **todo queda pendiente** hasta que un adulto lo revisa.
El adulto puede aprobarlo, **rechazarlo o corregirlo**; nada se borra: los errores se **anulan con un movimiento inverso** y
queda quién cargó, quién revisó y cuándo.

| Rol | Puede |
|---|---|
| **Hijo/a** | Ver su saldo e historial · cargar notas, tareas, regalos o ventas · armar metas · pedir rescates · anotar gastos · ver su Efectivo aparte · elegir avatar y tema |
| **Adulto (admin)** | Aprobar, rechazar o corregir lo cargado · entregar rescates (transferencia o efectivo) · anular movimientos · configurar premios por nota, tareas y mensualidad · aplicar deméritos o devoluciones · ver el registro de ingresos al sitio |

## Qué incluye

- [`src/app/`](src/app/) lógica (cuentas, notas, imágenes, avatares, sesiones, seguridad) y vistas.
- [`src/web/`](src/web/) páginas públicas, estilos (tipografías Inter y Noto Sans Mono servidas localmente) y gráfico SVG propio.
- [`src/sql/`](src/sql/) esquema de MariaDB y migraciones; la aplicación las aplica sola al abrir la base.
- [`src/herramientas/`](src/herramientas/) línea de comandos para crear usuarios y poner el PIN **sin eco** (`usuario.php`) y procesar fotos de notas.
- [`examples/`](examples/) plantillas de configuración sin datos reales.
- [`docs/`](docs/): [cómo funciona](docs/how-it-works.md), [ingreso](docs/login.md), [recuperación del PIN](docs/recovery.md), [alta de usuarios](docs/users.md), [seguridad](docs/security.md), [instalación](docs/install.md) y [límites](docs/limitations.md).

## Seguridad en una línea

PIN de 6 dígitos (se guarda sólo el hash Argon2id), bloqueo por tiempo y por IP, sesión que se cierra a los 10 minutos, token CSRF,
HTTPS, código y datos **fuera** de la carpeta web y recuperación del PIN por un link de un solo uso. Detalle en [`docs/security.md`](docs/security.md).

## Qué garantiza este repositorio y qué no

- **Sí:** que el código es el de un sitio que usa una familia real, sin sus datos, nombres ni credenciales, y que pasa `php -l` en PHP 8.3.
- **No:** que sea un producto listo para instalar con un clic, ni que sirva como servicio bancario. No hay pruebas automáticas en este
  repositorio más pruebas visuales que estas capturas. Ver [`docs/limitations.md`](docs/limitations.md).
