# CLAUDE.md — Constitución operativa del repositorio

> Claude Code lee este archivo automáticamente en cada sesión.
> Si estás leyendo esto, ya estás bajo estas reglas. No las negocies.

## 0. Antes de cualquier acción

Lee en este orden y no empieces hasta haberlo hecho:

1. `STATE.md` — dónde quedó la sesión anterior. **Es lo primero, siempre.**
2. `PLAN.md` — el paso que toca ejecutar. Un paso por sesión, en orden.
3. `AGENTS.md` — el harness completo (rol, loop obligatorio, Definition of Done, stop conditions).
4. `specs/<contexto>/SPEC.md` — del bounded context que vas a tocar.
5. `docs/architecture/permissions.md` y `docs/architecture/data-classification.md` — si el cambio toca datos de personas.
6. `docs/legal/legal-matrix.md` — si el cambio tiene efecto jurídico.
7. `docs/ux/design-system.md` y `docs/skills.md` — si el cambio toca interfaz.

Al terminar la sesión **actualiza `STATE.md`**. Si no lo haces, la siguiente sesión empieza a ciegas y el harness deja de existir.

## 1. Qué es este producto en una frase

Plataforma SaaS multiempresa de **personas, trabajo, salud y seguridad** —
RR. HH. + SG-SST + Salud Ocupacional + Enfermería + Cumplimiento —
con Colombia como primera jurisdicción y un colegio como cliente ancla.

## 2. Stack (decidido, no re-discutir sin ADR)

| Capa | Decisión |
|---|---|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | Inertia + Vue 3 + Vite |
| DB | PostgreSQL 16 |
| Aislamiento | Row Level Security + tenant guards de aplicación |
| Vault clínico | Conexión y credenciales de BD separadas |
| Colas | Laravel Queue + Horizon (Redis) |
| Archivos | S3-compatible, signed URLs, antivirus |
| Auth | Laravel + OIDC/SAML para enterprise, MFA obligatorio |
| Tests | Pest + Playwright |
| Infra | Docker + IaC, desplegable por región |

Cambiar cualquier fila exige un ADR en `docs/adr/` aprobado por el humano.

## 2b. Skills

Las skills se invocan en el momento que indica `PLAN.md`, nunca antes: primero el problema,
después el formato. `design` va **antes** de escribir frontend; `ux-refactor-lead` va **después**
de que exista sistema construido; `xlsx`, `pdf` y `docx` van cuando el contenido ya está resuelto.
Mapa completo en `docs/skills.md`.

## 3. Las seis reglas que no se rompen jamás

1. **Ninguna tabla que contenga datos de personas se crea sin `tenant_id`, política RLS y test de aislamiento.** Sin excepciones, ni "temporal", ni "para probar".
2. **Un permiso administrativo de RR. HH. nunca da acceso a datos clínicos.** El Clinical Vault vive en otra conexión, con otras credenciales y otra API.
3. **Ninguna norma jurídica se escribe como `if` en el código.** Va a `docs/legal/legal-matrix.md` y se consume como dato versionado con vigencia.
4. **Ninguna operación relevante ocurre sin `AuditEvent` append-only.**
5. **Ninguna decisión clínica, disciplinaria, de aptitud o de terminación la ejecuta el software de forma autónoma.** Siempre hay un humano responsable identificado.
6. **La plataforma no almacena identidad de menores de edad.** Los estudiantes existen solo como conteos agregados (ADR 0003). Hay un trigger en base de datos que lo impide.

## 4. Comandos de repositorio

| Comando | Uso |
|---|---|
| `/slice` | Abrir un vertical slice nuevo con el formato obligatorio |
| `/legal-check` | Verificar impacto normativo antes de implementar |
| `/threat` | Threat model del cambio en curso |
| `/tenant-test` | Generar y ejecutar tests de aislamiento multi-tenant |
| `/dod` | Evaluar la Definition of Done del slice actual |

## 5. Qué hacer cuando dudes

Si aparece ambigüedad jurídica, riesgo cross-tenant, o una decisión de producto
que no está en `PRODUCT.md`: **detente, escríbelo en `STATE.md` bajo `## Bloqueos`
y pregunta.** No improvises para poder seguir avanzando. Avanzar mal en este
dominio cuesta más que no avanzar.
