# Matriz de clasificación de datos

> Fase Cero, artefacto 3. Todo campo con información de una persona tiene un nivel.
> Sin nivel asignado, la migración se bloquea.

| Nivel | Definición | Controles mínimos |
|---|---|---|
| **P0** | Público | — |
| **P1** | Interno | autenticación |
| **P2** | Personal | RLS + auditoría de escritura |
| **P3** | Sensible | + auditoría de lectura, cifrado de campo, `PURPOSE` declarado |
| **P4** | Clínico / altamente restringido | + conexión y credenciales de BD separadas, doble autorización, lectura siempre auditada |

## Clasificación por campo — Slice 0 y Slice 1

| Tabla | Campo | Nivel | Nota |
|---|---|---|---|
| `tenants` | todos | P1 | |
| `organizations`, `legal_entities` | todos | P1 | `tax_id` es público en Colombia |
| `sites` | todos | P1 | `dane_code` es público |
| `positions` | todos | P1 | |
| `people` | `given_names`, `family_names` | **P2** | |
| `people` | `birth_date` | **P3** | fecha de nacimiento completa |
| `people` | `sex` | **P3** | exigido por el Módulo III del C600 |
| `people` | `education_level`, `teaching_statute`, `teaching_grade` | **P2** | |
| `identities` | `document_number` | **P3** | documento de identidad |
| `relationships` | todos | **P2** | el tipo de vínculo es dato laboral |
| `assignments` | `weekly_hours` | **P2** | |
| `enrollment_snapshots` | todos | **P1** | agregado sin PII (ADR 0003) |
| `users` | `email` | **P2** | |
| `users` | `password`, `mfa_secret` | **P3** | hash y secreto, nunca legibles |
| `audit_events` | `context` | **P2** | prohibido copiar contenido P3/P4 dentro |

## Reglas

1. **Ningún campo P4 existe todavía.** El Clinical Vault entra en el Slice 5 con su propia conexión.
2. `sex` y `birth_date` se guardan porque una obligación legal los exige (Ley 2335 de 2023, art. 19
   para el C600). Esa base legal se registra como `PURPOSE` en cada lectura y queda en la auditoría.
3. Los agregados de salida son P1. **Analytics jamás lee tablas P3.**
4. Ningún dato P3 aparece en logs, trazas, telemetría, correos, mensajes de error, nombres de
   archivo ni URLs.
5. Un campo sin clasificación asignada rompe el build.

## Retención

| Dato | Retención | Fuente |
|---|---|---|
| `audit_events` | 5 años mínimo | política interna; soporte probatorio |
| Evidencias del SG-SST | según Decreto 1072 de 2015 y política del cliente | Decreto 1072 de 2015 |
| Historia clínica ocupacional *(futuro)* | mínimo 20 años | Resolución 1843 de 2025 |
| `enrollment_snapshots` | permanente | serie estadística, sin PII |

La retención legal vence al derecho de supresión. Una solicitud de supresión sobre un dato con
retención obligatoria se registra, se responde y **no se ejecuta**, con la norma como motivo.
