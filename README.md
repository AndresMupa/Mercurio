# Plataforma de Personas, Trabajo, Salud y Seguridad

Repositorio-harness listo para abrir en Claude Code.

## Cómo levantar el entorno

```bash
cp .env.example .env                          # variables de docker compose
cp apps/platform/.env.example apps/platform/.env   # configuración de Laravel
# cambia las tres contraseñas del .env de la raíz antes de seguir
docker compose up
```

La aplicación queda en <http://localhost:8080> y Vite en el 5173. El arranque migra con el
dueño del esquema y después ejecuta `platform:check-foundation`, que aborta si la separación
de roles no se cumple.

Las credenciales de base de datos viven **solo** en el `.env` de la raíz: con ellas se crean
los roles en PostgreSQL y con ellas se conecta la aplicación, así que no pueden
desincronizarse. `apps/platform/.env` aporta el resto de la configuración de Laravel.

### Los dos roles de base de datos

| Rol | Para qué | Lo que **no** puede |
|---|---|---|
| `platform_owner` | Dueño del esquema. Ejecuta las migraciones. | Servir peticiones. |
| `platform_app` | Runtime: peticiones, colas, comandos. | Ser superusuario, tener `BYPASSRLS`, crear tablas, alterar la RLS. |

Esa separación es la que convierte la RLS forzada y la auditoría append-only en garantías
y no en convenciones: la aplicación no puede deshacer sus propias restricciones porque no
es dueña de nada. Las migraciones **siempre** con el dueño:

```bash
php artisan migrate --database=pgsql_owner
```

### Pruebas

```bash
DB_DATABASE=platform_test php artisan migrate:fresh --database=pgsql_owner --force
./vendor/bin/pest                          # suite completa
./vendor/bin/pest --group=tenant-isolation # lo que bloquea el pre-commit
```

Las pruebas corren contra PostgreSQL real y con el rol de aplicación. No se sustituye por
sqlite: sin RLS, unas pruebas de aislamiento pasarían siempre sin comprobar nada.

## Cómo trabajar en el repositorio

Al abrir la sesión, Claude Code lee `CLAUDE.md` automáticamente y desde ahí queda bajo el
harness completo de `AGENTS.md`.

**Primer mensaje de cada sesión:**

> Lee STATE.md y PLAN.md. Ejecuta el paso que toca y nada más. Al terminar, actualiza
> STATE.md y marca la casilla en PLAN.md.

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
| `apps/platform/` | Aplicación Laravel 11 + Inertia + Vue 3: migraciones, RLS, contexto de tenant y pruebas de aislamiento. |
| `docker/` | Imagen de la aplicación y arranque de PostgreSQL con los dos roles. |
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
