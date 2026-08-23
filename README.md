# Plataforma de Personas, Trabajo, Salud y Seguridad

Repositorio-harness listo para abrir en Claude Code.

## Cómo arrancar

```bash
git init
git add -A
git commit -m "chore: harness inicial"
claude
```

Al abrir la sesión, Claude Code lee `CLAUDE.md` automáticamente y desde ahí queda bajo el
harness completo de `AGENTS.md`.

**Primer mensaje:**

> Lee STATE.md y PLAN.md. Ejecuta el paso A1 y nada más. Al terminar, actualiza STATE.md
> y marca la casilla en PLAN.md.

La Fase Cero ya está hecha. `PLAN.md` tiene los pasos A1 a D3 con el prompt literal de cada uno.

## Mapa del repositorio

| Ruta | Qué contiene |
|---|---|
| `CLAUDE.md` | Constitución operativa. Lo lee Claude Code en cada sesión. |
| `PLAN.md` | **El documento que se ejecuta.** Pasos A1 a D3 con su prompt, skills y criterios. |
| `AGENTS.md` | **Master Harness v2.** El documento de referencia completo. |
| `STATE.md` | Testigo entre sesiones. Se lee al abrir, se escribe al cerrar. |
| `PRODUCT.md` | Visión, posicionamiento, cliente ancla, frontera comercial. |
| `ARCHITECTURE.md` | Forma del sistema y materialización en Laravel. |
| `SECURITY.md` | Responsable/Encargado, clasificación, controles, retención. |
| `docs/legal/` | Marco normativo colombiano verificado y matriz legal viva. |
| `docs/architecture/` | Domain map, permisos, clasificación, entidades núcleo. |
| `docs/anchor/` | Ficha del cliente ancla con cifras reales. |
| `docs/ux/` | Personas, recorridos y sistema de diseño. |
| `docs/skills.md` | Qué skill se invoca en qué momento. |
| `docs/business/` | Empaquetado y precios. |
| `docs/adr/` | Decisiones de arquitectura. |
| `specs/<contexto>/SPEC.md` | Especificación obligatoria por bounded context. |
| `apps/platform/` | Aplicación Laravel: migraciones, RLS, contexto de tenant y pruebas de aislamiento. |
| `.claude/commands/` | `/slice` `/legal-check` `/threat` `/tenant-test` `/dod` `/ux` |
| `scripts/` | Hook de pre-commit que bloquea si falla el aislamiento multi-tenant. |
| `runbooks/` | Procedimientos operativos e incidentes. |

## Las seis reglas que no se rompen

1. Ninguna tabla con datos de personas sin `tenant_id`, RLS y test de aislamiento.
2. Un permiso de RR. HH. nunca da acceso a datos clínicos.
3. Ninguna norma se escribe como `if` en el código.
4. Ninguna operación relevante sin `AuditEvent` append-only.
5. Ninguna decisión clínica, disciplinaria o de aptitud la ejecuta el software solo.
6. La plataforma no almacena identidad de menores de edad (ADR 0003).

## Estado normativo

Verificado a **23 de agosto de 2026**. Ver `docs/legal/colombia.md`.
Revisar antes de implementar cualquier obligación cuya verificación tenga más de 90 días.
