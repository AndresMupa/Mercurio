# SPEC — Slice 0 · Foundation (Identity, Organization, People)

`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`
`BOUNDED CONTEXTS: Identity & Access · People & Organization · Billing & Tenancy (mínimo)`

## Propósito

Construir el núcleo sobre el que se apoya todo lo demás: tenant aislado, organización, sedes,
personas con relación laboral, acceso con MFA y auditoría inmutable. Nada de esto se puede
reparar después sin migración destructiva.

## Frontera

**Dentro:** tenant, organización, entidad jurídica, sede, persona, identidad, cargo, relación,
asignación, usuario, rol con alcance, auditoría, clasificación de datos, snapshots de matrícula.

**Fuera:** reportes (Slice 1), SG-SST (Slice 2), cualquier dato clínico, nómina, estudiantes
identificados (ADR 0003).

## Invariantes del dominio

| # | Invariante | Dónde se garantiza |
|---|---|---|
| 1 | Toda fila con datos de persona tiene `tenant_id` | constraint `NOT NULL` + RLS |
| 2 | Ninguna consulta cruza tenants | RLS `FORCE` + middleware + tests |
| 3 | `people.birth_date` implica mayoría de edad | `CHECK` constraint + test |
| 4 | Una persona puede tener varias relaciones vigentes | modelo |
| 5 | Una relación no se borra: se cierra con `valid_to` | política de repositorio + test |
| 6 | `audit_events` no admite `UPDATE` ni `DELETE` | `REVOKE` al rol de aplicación |
| 7 | Toda lectura P3 declara `purpose` | `AuthorizationContext` + test |
| 8 | Revocada la relación, el acceso cesa en la siguiente petición | resolución de permisos por petición |
| 9 | `enrollment_snapshots` no contiene identificadores | revisión de esquema + test |

## Máquina de estados — `relationships`

```
PLANNED → ACTIVE → SUSPENDED → ACTIVE
                 ↘ ENDED (terminal)
```

| Transición | Actor | Precondiciones | Efectos | Auditoría |
|---|---|---|---|---|
| `PLANNED → ACTIVE` | admin_rrhh, owner | `valid_from <= hoy`, persona con identidad | habilita acceso y bandejas | `relationship.activated` |
| `ACTIVE → SUSPENDED` | admin_rrhh, owner | motivo obligatorio | suspende accesos derivados | `relationship.suspended` |
| `ACTIVE → ENDED` | admin_rrhh, owner | `valid_to` informado | revoca accesos en la siguiente petición | `relationship.ended` |
| cualquiera → borrado | — | **prohibido** | — | — |

## Criterios de aceptación

- [ ] **CA-01** Un usuario autenticado del tenant A no obtiene ninguna fila del tenant B, ni por id directo, ni por listado, ni por relación indirecta, ni por exportación.
- [ ] **CA-02** Con el middleware de tenant desactivado, la RLS sigue impidiendo la lectura cruzada. La defensa no depende de la aplicación.
- [ ] **CA-03** Crear una persona con `birth_date` de menor de edad falla en base de datos, no solo en validación de formulario.
- [ ] **CA-04** Leer `people.sex`, `people.birth_date` o `identities.document_number` sin `purpose` válido devuelve 403 y genera `audit_events` con `result = denied`.
- [ ] **CA-05** Toda lectura P3 exitosa genera un `audit_events` con `purpose`, `actor`, `correlation_id` y clasificación.
- [ ] **CA-06** Un intento de `UPDATE` o `DELETE` sobre `audit_events` con el rol de aplicación falla con error de privilegios.
- [ ] **CA-07** Cerrar una relación revoca el acceso del usuario asociado en la siguiente petición, sin necesidad de cerrar sesión.
- [ ] **CA-08** Un `role_assignment` vencido no otorga permisos, aunque el rol siga asignado.
- [ ] **CA-09** El login exige MFA para todo rol con techo P3.
- [ ] **CA-10** El seeder genera un tenant demo completo con la forma del ancla —64 personas, 29 aulas, 883 estudiantes como conteos— sin un solo dato real.
- [ ] **CA-11** `enrollment_snapshots` no tiene ninguna columna que permita identificar a un estudiante.
- [ ] **CA-12** Las migraciones corren en limpio y son reversibles hasta el punto anterior a datos productivos.

## Pruebas obligatorias (DoD nivel B)

| Grupo | Qué cubre |
|---|---|
| `unit` | invariantes de dominio, máquina de estados, cálculo de vigencias |
| `integration` | repositorios, transacciones, `set_config` del tenant |
| `authorization` | matriz de `docs/architecture/permissions.md`, celda por celda |
| `tenant-isolation` | los siete escenarios de `/tenant-test` — **bloquean el commit** |
| `e2e` | alta de persona → relación → asignación → consulta → auditoría |
| `accessibility` | recorrido de alta de persona con teclado y lector de pantalla |
| `negative` | ids inexistentes, ids de otro tenant, `purpose` inválido, fechas imposibles |

## Riesgos abiertos

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Conexiones agrupadas filtran `app.tenant_id` entre peticiones | **P0** | `set_config` por petición + reset en el middleware de terminación + test de fuga |
| Colas y jobs corren fuera del contexto de la petición | **P0** | el tenant viaja en el payload del job; el job lo fija antes de tocar la BD |
| El rol de BD de la aplicación tiene `BYPASSRLS` por descuido | **P0** | verificación en el arranque: la app se niega a levantar si el rol la puede saltar |
