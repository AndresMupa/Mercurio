---
description: Generar y ejecutar pruebas de aislamiento multi-tenant
---

Genera y ejecuta las pruebas de aislamiento para las tablas y endpoints tocados en este slice.

Las pruebas deben **intentar activamente** acceder a recursos de otro tenant, no solo
comprobar el camino feliz. Como mínimo:

1. Lectura directa por id de un recurso de otro tenant → debe fallar.
2. Listado sin filtro explícito → no debe devolver filas de otro tenant.
3. Escritura sobre un recurso de otro tenant → debe fallar.
4. Recurso alcanzado por relación indirecta (persona, sede, documento adjunto) → debe fallar.
5. Exportación, reporte, búsqueda global y notificación → no deben filtrar.
6. Cola o job que corre fuera del contexto de la petición → debe respetar el tenant.
7. RLS activa incluso si el guard de aplicación se desactiva.

Si alguna prueba pasa cuando debía fallar, es un **P0 SECURITY INCIDENT**:
detén el slice y regístralo en `STATE.md`.

Alcance: $ARGUMENTS
