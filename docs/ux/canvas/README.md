# Lienzo del Slice 0 — paso B7

Doce artboards con las pantallas del Slice 0, sus estados vacíos y sus errores.

**Publicado en:** https://claude.ai/code/artifact/9883cebf-4044-4ea8-a694-ed6c6b496073

Los `.dc.html` de este directorio son la **fuente**. El fichero publicado se genera a partir
de ellos y no se versiona: son 2,6 MB de los que casi todo es el editor del lienzo, y se
reconstruye entero en cada cambio. Para regenerarlo hace falta la skill `design`; el
comando es el de su ayuda, con los doce artboards y `canvas.json`.

## Qué hay en cada uno

| Artboard | Qué decide |
|---|---|
| `Main` | Las cinco decisiones que necesitan aprobación, los tokens y la escala tipográfica |
| `Login` | Acceso, segundo factor, fallo sin enumeración y bloqueo por intentos |
| `MyWorkTalentoHumano` | La bandeja de la usuaria diaria: estado · responsable · siguiente acción · vence |
| `MyWorkRector` | Una sola pantalla, sin botones de alta ni de baja |
| `RectorMovil` | Firmar desde el móvil, sin formularios |
| `Personas` | Tabla densa, P3 enmascarado, selección múltiple y acciones en lote |
| `FichaPersona` | Datos, máquina de estados de la relación y quién ha leído sus datos |
| `AltaPersona` | J1 completo con sus cuatro caminos de error |
| `PurposeDialog` | El componente propio del producto. Único artboard con controles vivos |
| `CierreRelacion` | J5: qué se pierde y qué no, con las cifras de esa persona |
| `EstadosVacios` | Seis vacíos que dicen qué, por qué y cuál es la acción |
| `EstadosError` | Seis negativas que nombran el motivo sin filtrar lo que no toca |

## De dónde salen los valores

Los colores, los radios y la escala de espaciado vienen de
`apps/platform/resources/css/app.css`, que los declara desde A1. El lienzo **no los
reinventa**: los levanta tal cual. Lo que aporta es lo que faltaba —familia tipográfica,
escala fija y anatomía de los componentes—.

Los datos de todas las pantallas son los del tenant demo sembrado en B6: nombres y
documentos inventados, cifras reales del ancla.
