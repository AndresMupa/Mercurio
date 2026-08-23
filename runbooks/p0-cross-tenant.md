# Runbook — P0: sospecha de exposición cross-tenant

1. **Detener** todo desarrollo en curso. No hacer commit de nada.
2. Registrar en `STATE.md` bajo `## Bloqueos`: hora, slice, tabla o endpoint, evidencia.
3. Reproducir con una prueba automatizada que falle de forma determinista.
4. Determinar alcance: ¿qué tenants, qué clasificación de datos, cuántos registros, desde cuándo?
5. Contener: revocar tokens afectados, desactivar el endpoint, aplicar RLS faltante.
6. Verificar en la auditoría si hubo acceso real, no solo posibilidad de acceso.
7. Notificar al Responsable del tratamiento (el cliente) según los tiempos del DPA.
8. Corregir, añadir prueba de regresión al grupo `tenant-isolation`, y solo entonces reanudar.
9. Escribir el post-mortem en `docs/adr/` si el diseño debe cambiar.
