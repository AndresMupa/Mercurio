# STATE.md — Testigo entre sesiones

> **Léelo al abrir la sesión. Actualízalo antes de cerrarla.**
> Si este archivo está desactualizado, la siguiente sesión trabaja a ciegas.

---

## Slice actual

**Slice 0 — Foundation** · paso **A1** de `PLAN.md`
`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`

Estado: **Fase Cero completa. Listo para ejecutar el paso A1 de `PLAN.md`.**
El colegio ancla dio luz verde al piloto el 23 de agosto de 2026.

> **`PLAN.md` es el documento que se ejecuta.** Un paso por sesión, en orden, marcando la casilla
> y haciendo commit al terminar cada uno.

## Lectura obligatoria antes de empezar

`docs/anchor/colegio-finlandes.md` — cifras reales del ancla, las tres obligaciones anuales,
los campos que el C600 y el EVI imponen al modelo de personas, y las señales de riesgo detectadas.
La Fase Cero se modela contra ese documento, no contra supuestos.

## Fase Cero (§28 del harness) — completa

- [x] 1. `docs/architecture/domain-map.md`
- [x] 2. `docs/architecture/permissions.md`
- [x] 3. `docs/architecture/data-classification.md`
- [x] 4. `docs/legal/legal-matrix.md` (semilla slices 0 y 1)
- [x] 5. `docs/architecture/core-entities.md`
- [x] 6. `specs/identity/SPEC.md` (criterios CA-01 a CA-12)

Producidos además, fuera del mínimo: `specs/reporting/SPEC.md` (Slice 1),
`docs/ux/{personas,journeys,design-system}.md`, `docs/skills.md` y `PLAN.md`.

## Decisiones tomadas

| Fecha | Decisión | Dónde |
|---|---|---|
| 2026-08-23 | Stack: Laravel 11 + Inertia + Vue 3 + PostgreSQL 16 | `CLAUDE.md` §2 · ADR 0001 |
| 2026-08-23 | Producto: suite completa RR. HH. + SST; secuencia comercial iniciando donde no hay competencia | `AGENTS.md` §29 |
| 2026-08-23 | Cliente ancla: Colegio Finlandés Juan Pablo II, Madrid (Cundinamarca) | `docs/anchor/colegio-finlandes.md` |
| 2026-08-23 | Nómina fuera de alcance en v1; se integra | `AGENTS.md` §2 |
| 2026-08-23 | Frontera de habilitación en enfermería escolar | ADR 0002 |

## Decisiones confirmadas el 23-ago-2026

| Decisión | Dónde |
|---|---|
| Slice 1 = Motor de reportes obligatorios (C600 + EVI), SG-SST pasa a Slice 2 | `ROADMAP.md` |
| Los estudiantes se modelan como agregado, sin identidad | ADR 0003 |
| Datos sintéticos hasta que el Slice 0 pase su DoD; reales solo del personal | `PLAN.md` B6 y D1 |
| El colegio ancla confirmó el piloto | `docs/anchor/colegio-finlandes.md` |

## Bloqueos

_(Ninguno registrado. Todo bloqueo se escribe aquí antes de improvisar una solución.)_

## Preguntas abiertas para el ancla

1. ¿Existe la autoevaluación de estándares mínimos SG-SST de 2025 y se reportó antes del 31 de julio de 2026?
2. Conteo exacto de trabajadores vinculados: ¿supera 50? Define el conjunto de estándares aplicable.
3. ¿Dónde se registra hoy lo que atiende la enfermería, y quién autoriza la administración de medicamentos?
4. ¿Hay contratistas con obligaciones de SST en sede (transporte, aseo, alimentación)?
5. El comedor escolar figura atendiendo 0 estudiantes y sin cumplimiento de la Res. 2674 de 2013. ¿Se sirve alimento?
6. ¿Quién diligencia hoy el C600 y cuánto tiempo toma?

## Deuda conocida

_(Vacío)_

## Próximo paso concreto

**Paso A1 de `PLAN.md`:** levantar Laravel 11 + PostgreSQL 16 + Redis en Docker, con los dos roles
de base de datos separados (`platform_owner` y `platform_app`, este último sin BYPASSRLS).
Todavía sin migraciones de dominio.
