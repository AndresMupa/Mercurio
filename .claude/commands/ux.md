---
description: Diseñar o auditar interfaz siguiendo el sistema de diseño
---

Trabajo de interfaz bajo el sistema de diseño del proyecto.

1. Lee `docs/ux/design-system.md`, `docs/ux/personas.md` y `docs/ux/journeys.md`.
2. Determina en qué momento estás:
   - **Todavía no hay código de esta pantalla** → invoca la skill `design` y produce el lienzo
     con todos los estados, incluidos vacío y error. No programes hasta que el humano lo apruebe.
   - **Hay gráficos o indicadores** → invoca `dataviz` antes de la primera línea de código de gráfico.
   - **La pantalla ya está construida** → invoca `ux-refactor-lead` y audita contra el sistema
     de diseño y el lienzo aprobado, con parches quirúrgicos que no rompan la arquitectura.
3. Verifica siempre: estado antes que dato · origen del dato visible · el color nunca como único
   canal · WCAG 2.2 AA con teclado y lector de pantalla · `tabular-nums` en columnas numéricas ·
   estados vacíos que explican y ofrecen la acción.
4. Actualiza `STATE.md`.

Pantalla o alcance: $ARGUMENTS
