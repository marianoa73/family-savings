# Cómo funciona

## El circuito

1. **Ganar:** el hijo/a carga un ingreso: nota del colegio (con foto opcional), tarea de la casa, regalo, venta de algo propio, cumpleaños o
   fecha especial. Los montos sugeridos (por nota, por tarea) los define un adulto.
2. **Aprobar:** el ingreso queda **pendiente** en "Para aprobar". Un adulto lo aprueba, lo rechaza o lo corrige. Recién ahí suma al saldo.
3. **Ahorrar:** el saldo se ve en grande con su historial y un gráfico del mes. El hijo/a puede crear **metas** con una barra de avance.
4. **Rescatar:** para usar el dinero pide un **rescate** con un motivo (también desde una meta). El monto queda reservado.
5. **Entregar:** un adulto lo aprueba y lo marca como entregado, por transferencia o en efectivo. El **Efectivo** que el hijo/a tiene en mano se lleva aparte.

## Premios por nota (valores de ejemplo)

La tabla `premios` define cuánto paga cada nota. El esquema trae estos valores iniciales, que se editan desde Configuración:

| Nota | Premio |
|---|---|
| 10 | 20.000 |
| 9 | 10.000 |
| 8 | 5.000 |
| 7 | 2.500 |
| menos de 7 | 0 (se anota igual) |

## Reglas que cuidan el registro

- **Nada se borra.** Un error se anula con un movimiento inverso; se conserva el original, quién lo cargó, quién lo revisó y cuándo.
- **Carga siempre individual:** cada movimiento es de un solo hijo/a (no hay "ambos").
- **Devaluación y Fondo:** un adulto puede aplicar una devaluación (con motivo) que descuenta saldo libre y lo manda a un fondo común,
  devolver un demérito desde ese fondo, o aplicar un demérito del tutor. Anular un movimiento revierte también el fondo.
- **Mensualidad y interés opcional** para ahorrar (0 = apagado).
- **Categorías desactivables** por los adultos.

## Estética

Look "Terminal": tablero con lista de movimientos tipo *watchlist*, importes en verde/rojo, tipografía monoespaciada para los números
y tema **claro por defecto**, con modo oscuro guardado en el perfil de cada usuario.
