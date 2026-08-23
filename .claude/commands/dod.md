---
description: Evaluar la Definition of Done del slice actual
---

Evalúa la Definition of Done del slice actual según `AGENTS.md` §23.

1. Lee el `DoD LEVEL` declarado al abrir el slice en `STATE.md`.
2. Ejecuta y reporta cada gate del nivel, con resultado real, no estimado.

**Nivel A:** BUILD · LINT · TYPECHECK · UNIT · INTEGRATION · DOCUMENTATION
**Nivel B:** A + AUTHORIZATION · TENANT ISOLATION · E2E · ACCESSIBILITY · MIGRATIONS · OBSERVABILITY
**Nivel C:** B + SECURITY REVIEW · THREAT TESTS · DATA RETENTION REVIEW · LEGAL CHECK · REVISIÓN HUMANA

3. Un gate en rojo significa que el slice **no está terminado**. No lo marques como hecho.
4. No desactives pruebas para obtener verde. No bajes de nivel sin justificación escrita.
5. Actualiza `STATE.md` con el resultado.
