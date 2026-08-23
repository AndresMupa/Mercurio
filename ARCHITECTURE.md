# ARCHITECTURE.md

## Forma general

**Modular monolith** en Laravel 11, dividido en bounded contexts, preparado para extraer
servicios cuando exista una razón medible. No microservicios sin ADR aprobado.

```
app/
└── Domains/
    ├── Identity/          {Domain, Application, Infrastructure, Http}
    ├── People/
    ├── HrCore/
    ├── Sst/
    ├── Incidents/
    ├── OccupationalHealth/
    ├── ClinicalVault/     ← conexión de BD separada
    ├── Nursing/           ← conexión de BD separada
    ├── Compliance/
    ├── Evidence/
    ├── Billing/
    └── Analytics/
```

Regla: la lógica de negocio no vive en componentes Vue ni en controladores HTTP.

## Aislamiento multi-tenant — tres capas

1. **Aplicación** — middleware que resuelve el tenant y global scope en los modelos.
2. **Base de datos** — Row Level Security de PostgreSQL sobre `tenant_id`, activa también
   para el rol de aplicación. Cada request ejecuta `SET LOCAL app.tenant_id = ...`.
3. **Pruebas** — suite que intenta activamente leer recursos de otro tenant y que rompe el
   build si lo consigue.

Una filtración cross-tenant es **P0 SECURITY INCIDENT** y detiene el desarrollo.

## Clinical Vault

- Conexión Laravel `clinical`, credenciales y usuario de base de datos propios.
- Sin relaciones Eloquent hacia modelos de RR. HH. La unión se hace por identificador y
  siempre pasando por una política de autorización con propósito declarado.
- Toda lectura genera `AuditEvent`. Toda lectura exige `PURPOSE`.
- Cifrado de campo con clave gestionada en KMS, distinta de la del resto de la plataforma.

Objetivo de diseño: **un error en el código de RR. HH. debe ser físicamente incapaz de leer
el vault**, no solo estar prohibido de hacerlo.

## Autorización — RBAC + ABAC

El rol es un input de la decisión, nunca la decisión.

```
AuthorizationContext {
  user, tenant, legalEntity, site, relationship,
  resource, action, purpose, dataClassification
}
```

Se inyecta en las Policies de Laravel. `purpose` es obligatorio para P3 y P4.

## Auditoría

Tabla append-only. El usuario de aplicación no tiene privilegios de `UPDATE` ni `DELETE`
sobre ella. La integridad de la auditoría es un invariante, no una funcionalidad.

## Estados

Máquinas de estado explícitas con clases de transición. Nunca booleanos para procesos.
Cada transición declara actor permitido, precondiciones, validaciones, efectos,
notificaciones y evento de auditoría.

## Integraciones

Adapters versionados. El dominio nunca depende de una API externa. Transactional outbox
para integraciones críticas. Toda operación crítica es idempotente.

## Multi-país

Country Packs como concepto desde el día uno: **ninguna norma se escribe en el código**.
Pero no se construye abstracción multi-país hasta tener un segundo país con cliente pagando.
La preparación es no hardcodear; la generalización prematura es deuda.

## Despliegue

Docker + IaC. Región configurable. Residencia de datos, cifrado, backups, PITR y recuperación
son decisiones explícitas de arquitectura, no efectos colaterales del proveedor de hosting.
