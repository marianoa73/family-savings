# Límites

- **No hay pruebas automáticas en este repositorio.** El sitio se probó durante el desarrollo con usuarios ficticios, pero esa batería no se incluye.
- **Cómo se sacaron las capturas:** con el HTML real que genera el PHP del proyecto (versión 8.4 en el entorno de prueba, mientras que el código se verificó con `php -l` en 8.3), mostrado en Chromium sin navegar por HTTPS. No son capturas de un navegador usando el sitio publicado.
- **Las capturas son de una copia de prueba con datos ficticios**, no del sitio en uso (mostrarían datos de menores). En alguna aparecen recuadros vacíos donde el navegador de captura no tenía fuente de emojis, y textos del navegador en inglés ("Choose File").
- **La interfaz está en español** y los montos están pensados en pesos argentinos.
- **No es un servicio bancario:** no mueve dinero ni valida pagos; los adultos entregan el dinero real y lo registran.
- **Subida de fotos:** tiene límite por cuenta y por hora, pero falta una cuota total de disco, limpieza de fotos huérfanas y límite por IP.
- **Sin doble factor** de autenticación: el PIN de 6 dígitos con bloqueo es el único control de acceso.
- **Instalación manual:** pensada para un hosting compartido con PHP y MariaDB; no hay instalador.
- **Código sin framework:** la lógica y las vistas están en archivos PHP grandes, lo que dificulta tocarlos sin cuidado.
- Se desarrolló con ayuda de asistentes de IA (ver [AI Split Raspberry Pi](https://github.com/marianoa73/ai-split-raspberry-pi)); la revisión y la publicación son humanas.
