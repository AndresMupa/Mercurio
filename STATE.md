# STATE.md — Testigo entre sesiones

> **Léelo al abrir la sesión. Actualízalo antes de cerrarla.**
> Si este archivo está desactualizado, la siguiente sesión trabaja a ciegas.

---

## Slice actual

**Slice 0 — Foundation** · **A1, A2, B1, B2, B3, B4 y B5 completos**, siguiente **B6** de `PLAN.md`
`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`

Estado: esquema, dominio, autorización, auditoría y autenticación construidos. **364
pruebas, 538 aserciones.** Las 216 celdas de la matriz de permisos tienen prueba propia, los
nueve invariantes del SPEC también, y los siete de la matriz. B4 encontró y cerró **una fuga
real de datos P3 hacia los ficheros de log**; B5 resolvió el nudo del tenant antes de
autenticar. El DoD de nivel B queda en verde salvo TYPECHECK, sin herramienta por una
restricción de red del entorno. El harness operativo se cumple solo: un commit con una prueba
de aislamiento rota **no pasa**, verificado a mano. El colegio ancla dio luz verde al piloto
el 23 de agosto de 2026.

**Hay cuatro cosas esperando respuesta humana**, todas en `## Bloqueos`: si la aplicación
debe poder borrar un tenant, dos huecos de la máquina de estados que B2 interpretó, y **qué
permisos le corresponden al `rector`**, que la matriz nombra como rol pero deja sin columna
—y eso bloquea su pantalla del paso B7—.

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

## Paso A1 — hecho el 23-ago-2026

Laravel 11.56 + Inertia 2 + Vue 3 en `apps/platform`, PostgreSQL 16 y Redis 7 en
`docker-compose.yml`, con los dos roles de base de datos separados.

**Lo que quedó construido**

| Qué | Dónde |
|---|---|
| Roles `platform_owner` y `platform_app` | `docker/postgres/initdb/010-roles.sql` |
| Base de pruebas con los mismos dueños y privilegios | `docker/postgres/initdb/020-test-database.sql` |
| Conexiones `pgsql` (runtime) y `pgsql_owner` (migraciones) | `apps/platform/config/database.php` |
| Comprobaciones de la fundación, fuente única | `apps/platform/app/Domains/Shared/FoundationCheck.php` |
| Comando `php artisan platform:check-foundation` | `apps/platform/app/Console/Commands/CheckFoundation.php` |
| Pantalla de estado (entregable «responde en el navegador») | `apps/platform/resources/js/Pages/Estado.vue` |
| Pruebas del entregable A1 | `apps/platform/tests/Feature/FoundationTest.php` |
| Reinicio de base entre pruebas | `apps/platform/tests/Concerns/ResetsPlatformDatabase.php` |
| Tokens del sistema de diseño | `apps/platform/resources/css/app.css` |

**Verificado de verdad, no por inspección**

- Los dos roles: `rolsuper = f`, `rolbypassrls = f`, `rolcreatedb = f`, `rolcreaterole = f`,
  y `pg_has_role(platform_app, platform_owner, 'USAGE') = f` — sin herencia entre ellos.
- `platform_owner` es dueño de la base y del esquema `public`; `platform_app` no puede
  crear tablas ni ejecutar `ALTER TABLE ... NO FORCE ROW LEVEL SECURITY`. Ambas cosas
  tienen prueba propia.
- 19 pruebas en verde (64 aserciones), repetibles; 13 en el grupo `tenant-isolation`.
- La pantalla renderiza en Chromium contra el servidor real, no solo en la respuesta HTTP.

**Decisiones tomadas en este paso**

1. **Sesión, caché y colas en Redis, no en base de datos.** Las tablas `sessions` y `cache`
   de Laravel guardan `user_id`, IP y user agent sin `tenant_id`. Crearlas habría abierto una
   excepción a la regla 1 de `CLAUDE.md` en el primer día del proyecto.
2. **Se eliminaron las conexiones sqlite, mysql, mariadb y sqlsrv.** Una prueba que cayera en
   sqlite correría sin RLS y el grupo `tenant-isolation` pasaría sin comprobar nada.
3. **Se eliminó el modelo `App\Models\User` del andamiaje.** Describe un esquema que no es el
   nuestro —sin `tenant_id`, sin `person_id`, sin MFA— y dejarlo invitaba a construir la
   autenticación encima. `config/auth.php` apunta a `App\Domains\Identity\Domain\User`, que
   crea el paso B2. Hasta entonces no resuelve, y es lo correcto: no hay autenticación todavía.
4. **Las pruebas no usan `RefreshDatabase` ni `DatabaseTransactions`.** La primera migra con la
   conexión por defecto, que corre con el rol de aplicación y —a propósito— no puede crear
   tablas. La segunda daría falsos verdes: en PostgreSQL una sentencia fallida aborta la
   transacción, y varias pruebas de aislamiento comprueban justamente que una sentencia falle;
   su segunda expectativa pasaría por «transacción abortada» y no por la política de RLS.
   Se limpia con `TRUNCATE ... CASCADE` desde la conexión del dueño.
5. **`BindTenantToConnection` queda registrado desde el primer día**, para que ninguna ruta
   nazca fuera de la capa de aplicación del aislamiento.

## Hallazgo corregido en A1 — RLS que fallaba a gritos, no cerrada

La política de `2026_08_24_000200_enable_row_level_security.php` comparaba contra
`current_setting('app.tenant_id', true)::uuid`, y su comentario afirmaba que sin tenant fijado
la comparación da NULL y la política «falla cerrada».

**Eso solo es cierto en una conexión que nunca fijó la variable.** En cuanto una petición la
fija, ni `RESET` ni `set_config(..., NULL, ...)` la devuelven a NULL: la dejan en cadena vacía.
Comprobado en PostgreSQL 16.13. A partir de ahí, `''::uuid` lanza
`invalid input syntax for type uuid` en **toda** consulta posterior de esa conexión agrupada.

No filtra datos —por eso no es incidente P0— pero convierte cada petición sin tenant en un
error 500 en producción, que es justo el escenario de un worker de colas o una conexión
agrupada ya usada. Lo detectó la prueba «sigue aislando aunque el guard de aplicación esté
desactivado», que estaba en rojo.

**Corregido** a `NULLIF(current_setting('app.tenant_id', true), '')::uuid` en `USING` y en
`WITH CHECK` de las 13 tablas. Ahora la comparación queda `tenant_id = NULL`, devuelve cero
filas y falla cerrada de verdad.

> **Para B1:** la migración se corrigió porque el grupo `tenant-isolation` no podía quedar en
> rojo (§25 del harness) y porque nunca se ha desplegado. **La revisión crítica completa contra
> `specs/identity/SPEC.md` sigue siendo trabajo de B1**, y este hallazgo es una señal de que
> el resto de la migración merece la misma desconfianza. Revisar en particular si algún otro
> punto del diseño asume que `app.tenant_id` puede volver a NULL.

## Paso A2 — hecho el 23-ago-2026

El harness operativo. A partir de aquí las reglas dejan de depender de la disciplina de quien
programa: hay máquinas que las sostienen.

| Qué | Dónde |
|---|---|
| Hook de pre-commit, versionado y falla cerrado | `scripts/pre-commit-tenant-isolation.sh` |
| Activación por clon (`core.hooksPath`) | `scripts/install-git-hooks.sh` · `scripts/git-hooks/` |
| Compuerta de arranque: la app no opera si el rol puede saltarse la RLS | `apps/platform/app/Providers/PlatformServiceProvider.php` |
| Pruebas del cableado de esa compuerta | `apps/platform/tests/Feature/BootGuardTest.php` |
| Playwright + E2E contra la pantalla servida | `apps/platform/playwright.config.js` · `tests/E2E/foundation.spec.js` |
| Cada compuerta del DoD, un comando | `composer lint · test:prepare · test · test:tenant` · `npm run e2e · build` |

### La verificación manual que exige el PLAN — hecha, no asumida

No basta con que el hook exista; el entregable de A2 es que **un commit con una prueba de
aislamiento rota no pase**. Se comprobó rompiendo el aislamiento de verdad:

1. `ALTER POLICY tenant_isolation ON people USING (true)` en la base de pruebas — una fuga
   real entre tenants, no un fallo simulado.
2. El grupo `tenant-isolation` se puso en rojo: 4 pruebas fallando.
3. `git commit` → **código de salida 1, sin commit creado, HEAD intacto**.
4. Política restaurada y suite de nuevo en verde (22 pruebas, 68 aserciones).

También se verificó la compuerta de arranque por el mismo método: con `BYPASSRLS` concedido
a `platform_app`, `platform:check-foundation` sale con código 1, cualquier consulta a la base
lanza `RuntimeException` y la suite se pone en rojo. Privilegio revocado después.

### Decisiones tomadas en este paso

1. **El hook falla cerrado.** Si no puede ejecutar las pruebas —falta `vendor`, la base no
   responde— bloquea igual. Un hook que deja pasar el commit cuando no pudo comprobar nada
   es peor que no tenerlo: da confianza sin respaldarla.
2. **Se corrigió la ruta del script.** Invocaba `./vendor/bin/pest` desde la raíz y la
   aplicación vive en `apps/platform`; tal como estaba no habría ejecutado nada y habría
   dejado pasar todo.
3. **Los hooks viven versionados en `scripts/git-hooks/`** y se activan apuntando
   `core.hooksPath`, no copiando a `.git/hooks`. Así viajan con el repositorio.
4. **La compuerta de arranque se engancha a `ConnectionEstablished`, no al boot del
   framework.** En el arranque no hay conexión que interrogar, y forzarla obligaría a
   conectar en peticiones que no tocan la base. Al conectar cubre además la reconexión y
   un `GRANT` hecho en caliente. Coste: una consulta a `pg_roles` por proceso.
5. **La pantalla de estado es la única superficie que informa del fallo en vez de morir con
   él.** Un diagnóstico que se cae en lugar de decir qué está roto no sirve. Queda
   documentado en el controlador para que el patrón no se copie en pantallas de producto.
6. **No se usó `skill-creator`.** `PLAN.md` lo marca como opcional y el loop del harness ya
   está sostenido por `CLAUDE.md`, `STATE.md` y los comandos de `.claude/commands/`.
   Empaquetarlo como skill habría añadido una capa sin resolver ningún problema abierto.

## Paso B1 — hecho el 23-ago-2026

Revisión crítica de las tres migraciones contra `specs/identity/SPEC.md`,
`docs/architecture/core-entities.md` y `docs/architecture/data-classification.md`.

### Hallazgo P0 — cualquier tenant podía enumerar a todos los demás

`tenants` era la única tabla del esquema sin RLS. No tiene columna `tenant_id` —su propio
`id` es la llave del aislamiento— y por eso quedó fuera de la lista de trece tablas de la
migración 000200. Nadie lo había notado porque **la prueba que comprobaba la cobertura de RLS
enumeraba a mano esas mismas trece tablas**: comprobaba lo que alguien recordó anotar.

Comprobado antes de tocar nada: situado como el tenant `uno`, un `SELECT` sobre `tenants`
devolvía también la fila de `dos` —nombre, slug, plan, región, estado del DPA—. Es decir,
**la lista de clientes de la plataforma**. No filtraba datos de personas: `people`,
`identities` y el resto sí estaban protegidas. Pero es una lectura cruzada entre tenants, y
CA-01 no admite ninguna: «no obtiene ninguna fila del tenant B».

**Corregido:** `tenants` entra en la RLS con política sobre su propio `id`. La lista de tablas
de la migración pasó a ser un mapa `tabla => columna del tenant`, que es lo que permite
tratar el caso de `tenants` sin excepciones escritas a mano.

**Y para que no vuelva a pasar por el mismo camino:** las pruebas de cobertura de RLS ya no
enumeran tablas. Preguntan al catálogo de PostgreSQL por toda tabla de `public` sin RLS
activa, sin RLS forzada o sin política `tenant_isolation`. Una tabla nueva mal configurada
rompe la prueba sola, sin que nadie tenga que acordarse de añadirla a una lista.

### Segundo defecto — inyección SQL en la migración de auditoría

La migración 000300 interpolaba `env('DB_APP_ROLE')` directamente en sentencias `GRANT` y
`REVOKE` que corren con el dueño del esquema, el rol de mayor privilegio del sistema. Un
identificador de PostgreSQL no se puede pasar como parámetro enlazado, así que interpolar es
inevitable; hacerlo sin validar el contenido no lo es.

Corregido con lista blanca de identificador, comprobación de que el rol existe y —más
importante— tomando el rol de `config('database.connections.pgsql.username')` en vez de
`env()`. Dos motivos: `env()` devuelve null con la configuración cacheada, así que un
despliegue normal podía ejecutar la migración sin rol y dejar la auditoría sin blindar; y lo
que hay que blindar es el rol con el que la aplicación se conecta de verdad, no una variable
que puede desincronizarse de él.

### Tercer defecto — un comentario que afirmaba algo falso

La migración justificaba usar un trigger en vez de un `CHECK` diciendo que «CURRENT_DATE no
es inmutable y un CHECK con función no inmutable rompe restauraciones». **PostgreSQL 16
acepta ese CHECK sin protestar** —comprobado ejecutándolo—. El trigger sigue siendo la
elección correcta, pero por otras razones, y ahora están escritas las de verdad. Un
comentario falso es peor que ninguno: el siguiente que lo lea construirá encima.

`SPEC.md` (invariante 3) y `core-entities.md` decían «CHECK constraint»; ahora dicen trigger,
que es lo que hay.

### Lo verificado, criterio por criterio

| Criterio | Cómo se verificó | Estado |
|---|---|---|
| **CA-01** | Lectura por id, listado, relación indirecta en las 13 tablas con un grafo completo del otro tenant, y enumeración de `tenants` | verde; la parte de exportación no aplica: no existe hasta el Slice 1 |
| **CA-02** | Guard de aplicación desactivado y conexión purgada; la RLS sostiene sola | verde |
| **CA-03** | Trigger probado en los tres casos frontera: 18 años exactos hoy **acepta**, un día menos **rechaza**, y también protege el `UPDATE` | verde |
| **CA-06** | `UPDATE` y `DELETE` sobre `audit_events` con el rol de aplicación fallan; privilegios reales tras el ciclo completo: solo `INSERT, SELECT` | verde |
| **CA-11** | Columnas reales de `enrollment_snapshots` leídas del catálogo; ningún identificador | verde |
| **CA-12** | `migrate:rollback` de los tres pasos y vuelta a migrar, en las dos bases. Tras el rollback no quedan tablas, funciones, políticas ni triggers huérfanos: solo `migrations` | verde |

### `/tenant-test` — los siete escenarios del comando

Escenarios 1 (lectura por id), 2 (listado sin filtro), 3 (escritura sobre recurso ajeno),
4 (relación indirecta) y 7 (RLS con el guard desactivado): **cubiertos**.

Escenario 6 (cola fuera del contexto de la petición): **cubierto**, nuevo. Es un riesgo P0
declarado en el SPEC. Un worker que arranca sin `app.tenant_id` no ve ninguna fila; con el
tenant del payload ve solo ese.

Escenario 5 (exportación, reporte, búsqueda global, notificación): **no aplica todavía**.
Ninguna de las cuatro existe hasta el Slice 1. No se marca como cubierto lo que no existe.

Además, las pruebas de aislamiento sobre tablas vacías eran vacías: `count() == 0` salía
verdadero tanto si la RLS filtraba como si no había nada que filtrar. Ahora el otro tenant
tiene una fila en cada tabla y la prueba falla si el fixture no la pobló.

### Lo que se decidió NO hacer en B1

Faltaban clasificaciones de `data-classification.md` en la migración (`users.email`,
`users.password`, `audit_events.context`, `relationships`, `assignments.weekly_hours`): se
anotaron. Pero la regla 5 de ese documento —«un campo sin clasificación rompe el build»— **no
existe como mecanismo**: nada la comprueba. Su sitio natural es B2, junto a los invariantes
del dominio, no una migración.


## Paso B2 — hecho el 23-ago-2026

El dominio expresado en código. Modelos, repositorios, máquina de estados y los nueve
invariantes del SPEC con prueba propia.

### Lo construido

| Qué | Dónde |
|---|---|
| Aislamiento en el modelo: global scope + `tenant_id` impuesto al crear | `app/Domains/Shared/Domain/BelongsToTenant.php` |
| Modelo base del núcleo (uuid + tenancy) | `app/Domains/Shared/Domain/PlatformModel.php` |
| Registro de clasificación por columna | `app/Domains/Shared/Domain/DataClassification.php` |
| Identificador de correlación por petición | `app/Domains/Shared/CorrelationId.php` |
| Auditoría como modelo, append-only también en PHP | `app/Domains/Shared/Domain/AuditEvent.php` |
| Identity: Tenant, User, Role, RoleAssignment | `app/Domains/Identity/Domain/` |
| People: Person, PersonIdentity, Relationship, Assignment, Organization, LegalEntity, Site, Position, EnrollmentSnapshot | `app/Domains/People/Domain/` |
| Máquina de estados con cuatro clases de transición | `app/Domains/People/Domain/Transitions/` |
| Contratos de repositorio (Domain) e implementación Eloquent (Infrastructure) | `app/Domains/People/{Domain,Infrastructure}/` |
| Nueve invariantes, cada uno con su prueba | `tests/Domains/People/InvariantsTest.php` |
| Reglas de arquitectura comprobadas | `tests/Domains/Shared/ArchitectureTest.php` |

### Decisiones tomadas en este paso

1. **El estado es un enum, no booleanos.** `is_active` + `is_suspended` admite cuatro
   combinaciones de las que dos no significan nada, y nada impide escribirlas. Un enum
   solo admite los estados que existen.
2. **El borrado de una relación lo impide el modelo, no solo el repositorio.** El SPEC dice
   que el invariante 5 se garantiza con «política de repositorio + test», pero una política
   que vive solo en el repositorio se salta llamando a `delete()` desde cualquier sitio.
   El evento `deleting` lanza, y el contrato del repositorio **no tiene** método de borrado
   —hay una prueba que lo comprueba por reflexión—.
3. **Sin tenant en el contexto, el global scope devuelve cero filas en vez de lanzar.** Es
   exactamente lo que hace la RLS en la misma situación. Si una capa lanzara y la otra
   filtrara, el comportamiento dependería de cuál actuara primero.
4. **El `tenant_id` que llegue de fuera se ignora.** Se impone el del contexto al crear.
   Respetarlo sería dejar que el cuerpo de una petición decidiera a qué tenant escribe.
   Hay prueba.
5. **Las transiciones escriben su `AuditEvent`.** `PLAN.md` asigna la auditoría del flujo
   completo a B4, pero la regla 4 de `CLAUDE.md` no admite que una operación relevante
   ocurra sin auditoría, y un cambio de estado de una relación laboral lo es. B4 amplía a
   lecturas P3, denegaciones y errores.
6. **La autorización NO se implementó aquí.** Las transiciones *declaran* `allowedRoles()`
   como dato, para que B3 lo consuma, pero no lo comprueban: decidir necesita el
   `AuthorizationContext` completo —alcance, relación, clasificación, propósito—, no el
   nombre de un rol.
7. **Una relación nace `planned`, no `active`.** La columna tiene `active` por defecto en
   base de datos, así que crear una fila directamente activa sigue siendo posible —y hace
   falta para la carga masiva de D1—. Pero el repositorio expone `startPlanned()`: por el
   camino normal, activar es una transición con precondiciones y con su evento.

### Defecto encontrado y corregido dentro del propio paso

La primera versión de las transiciones generaba un `correlation_id` nuevo en cada
`AuditEvent`. Eso produce una columna llamada «correlación» que no correlaciona nada: dos
eventos de la misma operación salían con identificadores distintos, y reconstruir qué pasó
en un incidente —que es para lo que existe la columna— habría sido imposible.

Salió al evaluar la compuerta OBSERVABILITY del DoD, no de leer el código. Corregido con
`CorrelationId`, uno por petición, fijado por el middleware y aceptando `X-Correlation-Id`
para seguir una operación entre servicios. Con prueba.

### Dos huecos del SPEC que este paso obliga a decidir

1. **`SUSPENDED → ACTIVE` está en el diagrama del SPEC pero no en su tabla de transiciones.**
   No declara actor, precondiciones ni evento. Se implementó `ReactivateRelationship` con
   evento propio `relationship.reactivated`, distinto de `relationship.activated`: reanudar
   no es lo mismo que activar por primera vez y la auditoría tiene que poder distinguirlos.
2. **`SUSPENDED → ENDED` no está contemplado.** La tabla solo admite cerrar desde `ACTIVE`.
   Si se respetara al pie de la letra, una relación suspendida no podría cerrarse nunca y
   habría que reactivarla para poder terminarla, que es peor. Se admite.

Las dos son interpretaciones razonables, pero son **mías**, no del SPEC. Conviene
confirmarlas o corregirlas antes de B9.

### `/dod` — nivel B declarado, resultado real

| Compuerta | Resultado |
|---|---|
| BUILD | **verde** · `vite build` |
| LINT | **verde** · Pint |
| TYPECHECK | **sin verificar** · no hay analizador instalable en este entorno (`## Bloqueos`) |
| UNIT | **verde** · 55 pruebas, 143 aserciones |
| INTEGRATION | **verde** · contra PostgreSQL real y con el rol de aplicación |
| DOCUMENTATION | **verde** · `STATE.md`, `PLAN.md`, `SPEC.md` y `core-entities.md` |
| AUTHORIZATION | **rojo** · no existe todavía: es el paso B3 |
| TENANT ISOLATION | **verde** · 26 pruebas en el grupo |
| E2E | **verde** · 3 en Chromium |
| ACCESSIBILITY | **parcial** · la única pantalla existente está cubierta con teclado y sin depender del color; B2 no añadió interfaz |
| MIGRATIONS | **verde** · ciclo rollback + migrate limpio |
| OBSERVABILITY | **parcial** · correlación real por petición, health check y comprobación de dependencias; faltan logs estructurados, métricas, tracing y monitoreo de colas |

**El Slice 0 no está terminado**, y eso es lo esperado: AUTHORIZATION es B3 y el DoD del
slice se cierra en B9. Lo que sí queda cerrado es el paso B2. Ninguna prueba se desactivó
ni se bajó de nivel.


## Paso B3 — hecho el 23-ago-2026

Autorización RBAC + ABAC con propósito. Era la compuerta que estaba en rojo en el DoD.

### Lo construido

| Qué | Dónde |
|---|---|
| Contexto completo de decisión | `app/Domains/Identity/Domain/Authorization/AuthorizationContext.php` |
| Matriz recurso × acción × rol **como dato** | `.../Authorization/PermissionMatrix.php` |
| Propósito como lista cerrada | `.../Authorization/Purpose.php` |
| Roles con alcance y techo de clasificación | `.../Authorization/RoleKey.php` |
| Decisión con motivo y regla | `.../Authorization/AuthorizationDecision.php` |
| Punto único de decisión, con las seis condiciones ABAC | `app/Domains/Identity/Application/Authorizer.php` |
| Policies que lo consumen | `app/Domains/{People,Identity}/Interfaces/Policies/` |
| 216 celdas de la matriz, una prueba cada una | `tests/Domains/Identity/PermissionMatrixTest.php` |
| Los siete invariantes + CA-04, CA-05, CA-07, CA-08 | `tests/Domains/Identity/AuthorizationInvariantsTest.php` |

**305 pruebas, 420 aserciones.** El grupo `tenant-isolation` pasa de 26 a 30.

### Decisiones tomadas en este paso

1. **La matriz es dato, no código.** No hay un solo `if` sobre nombres de rol fuera de esa
   tabla. Cambiar quién puede hacer qué se hace editando una estructura que se lee al lado
   del documento y se coteja de un vistazo.
2. **Falla cerrada en todo.** Un recurso que la matriz no declara se deniega
   (`matrix.undeclared`); un rol sin columna no obtiene nada. Un recurso nuevo no nace
   accesible por descuido.
3. **El tenant se comprueba antes que el rol.** Un acierto de rol sobre un recurso de otro
   tenant no es un permiso concedido: es una fuga. Hay prueba de que la regla que deniega
   es `abac.tenant` y no la de la matriz.
4. **La prueba de la matriz está transcrita a mano del documento.** Si se generara desde
   `PermissionMatrix`, compararía la implementación consigo misma y pasaría siempre. Como
   está, una discrepancia entre documento y código sale en rojo.
5. **`✓ˢ` exige que el contexto diga de quién es el recurso, también al listar.** La
   alternativa —permitir el listado y confiar en que la consulta filtre— falla abierta:
   quien olvide filtrar enseña la planta entera. Así, olvidarlo deniega.
6. **La denegación conserva su regla.** Una primera versión aplanaba todas las
   denegaciones a `matrix.deny`, con lo que «te falta el propósito» y «tu rol no llega a
   P3» quedaban indistinguibles en la auditoría. Corregido: la decisión viaja entera.

### CA-07 no estaba implementado y este paso lo descubrió

`role_assignments` vigente no basta: si la persona ya no trabaja aquí, el acceso tiene que
cesar. La primera versión del `Authorizer` solo miraba roles, así que un exempleado con su
rol aún asignado seguía entrando.

Corregido: **la relación laboral vigente es condición de acceso**, resuelta en cada
petición y nunca cacheada ni guardada en sesión. Cerrar la relación corta el acceso en la
siguiente petición sin cerrar sesión, que es literalmente lo que pide CA-07. Los usuarios
sin persona asociada —cuentas de plataforma— no pasan por esta puerta: no tienen relación
laboral que cerrar.

### `/threat` — hallazgo serio, encontrado y cerrado dentro del paso

**La clasificación del dato llegaba desde el llamador.** Comprobado con una sonda, no
razonado: un `auditor` con techo P2 pidiendo un recurso P3 era **denegado si se declaraba
la clasificación y permitido si se omitía**. Un parámetro olvidado desactivaba la
comprobación del techo del rol, y además la lectura P3 no quedaba auditada.

En el Slice 0 el daño real era limitado —la matriz ya deniega a los roles de techo P2 los
recursos P3 concretos que hoy existen—, pero la puerta quedaba abierta para el Slice 4 y
el 5, donde entra P4 y el Clinical Vault.

**Corregido:** la clasificación la declara la matriz, junto al permiso, como hace el
documento. Si el llamador aporta una, se toma **el máximo**, nunca la recibida: nadie puede
rebajar la sensibilidad de un recurso pasando un nivel más bajo o ninguno. Tres pruebas de
regresión, incluida una que comprueba que la traza sigue diciendo P3 aunque el llamador
diga P1.

No se detuvo el paso por §26: era código sin desplegar, se cerró en el mismo paso y quedó
cubierto por pruebas.

### `/threat` — el resto del análisis

| Frente | Resultado |
|---|---|
| **Cross-tenant** | El tenant se comprueba primero y hay prueba de que ni el `owner` cruza. Riesgo residual aceptado abajo. |
| **Escalamiento a P4** | Ningún rol de la matriz alcanza P4, ni el `owner`, ni declarándolo. Con prueba, antes de que el Slice 5 exista. |
| **Fuga por canal lateral** | El `context` de auditoría solo lleva regla y motivo; hay prueba de que no aparecen nombres, documentos ni fechas de nacimiento. `password` y `mfa_secret` están en `$hidden`. |
| **Datos de menores** | Sin caminos nuevos: sigue sostenido por el trigger de base de datos. |
| **Integridad de auditoría** | Tres capas: privilegios de PostgreSQL, eventos del modelo y Policy. Ninguna de las tres se puede desactivar desde las otras. |
| **Error humano** | Cada equivocación tiene su regla distinta en la traza —`abac.tenant`, `abac.self`, `abac.alcance`, `abac.vigencia`—, que es lo que permite diagnosticar sin reproducir. |
| **Retención** | B3 no borra nada. |

**Riesgo residual aceptado:** cuando la acción es de colección —listar, crear— no hay
`resourceTenantId` que comparar, y esa comprobación se salta. La protección por fila
descansa entonces en el global scope y la RLS, que hacen imposible cargar una fila ajena;
30 pruebas del grupo `tenant-isolation` lo cubren. Queda un hueco teórico: una Policy que
tenga la instancia delante y olvide pasar su `tenant_id`. Severidad baja, sin camino
conocido para explotarlo, anotado en `## Deuda conocida`.


## Paso B4 — hecho el 23-ago-2026

Auditoría append-only en el flujo real. **320 pruebas, 456 aserciones.**

### Lo construido

| Qué | Dónde |
|---|---|
| Punto único de escritura de auditoría | `app/Domains/Shared/Application/AuditRecorder.php` |
| Auditoría de escrituras en todo modelo del núcleo | `app/Domains/Shared/Domain/RecordsAuditTrail.php` |
| Redacción de datos sensibles antes del disco | `.../Infrastructure/Logging/RedactSensitiveData.php` |
| Correlación y tenant en cada línea de log | `.../Infrastructure/Logging/AddRequestContext.php` |
| Log estructurado JSON en los canales reales | `config/logging.php` |
| `result = error` ante cualquier excepción | `bootstrap/app.php` |
| La prueba que exige el paso | `tests/Domains/Shared/AuditTrailTest.php` |

### Había una fuga de P3 en los logs, y era real

Comprobado, no razonado. Con una sonda desechable contra un canal sin redactor:

```
sin redactor, ¿aparece «Zzyzx»?     => SÍ APARECE
sin redactor, ¿aparece la fecha?    => SÍ APARECE
```

El vector no es que alguien escriba `Log::info($persona->birth_date)`. Es la excepción de
base de datos: el mensaje de `QueryException` trae **la consulta con los valores ya
interpolados**, así que un `INSERT` fallido sobre `people` escribía el nombre, el sexo y la
fecha de nacimiento en el fichero de log. Y los logs viven años, se copian a herramientas de
observabilidad y los lee gente sin propósito declarado para ese dato. La regla 4 de
`data-classification.md` lo prohíbe desde la Fase Cero; nada lo impedía.

**Corregido** con un procesador de Monolog que redacta la cola `SQL: …` entera y las claves
clasificadas P3/P4 del contexto. Se redacta el bloque completo en vez de intentar separar
valores de estructura: se pierde comodidad al depurar —hay que ir a la traza para saber qué
consulta era— y se gana que el log no sea una segunda copia de la base de datos sin control
de acceso. Con cinco años de retención, el cambio compensa.

La prueba correspondiente busca cuatro valores P3 deliberadamente raros —`Zzyzx`, `Qwghlm`,
un documento y una fecha imposibles de teclear por casualidad— y falla si alguno aparece.
Además comprueba que **no pasa por estar el log vacío**: exige que el `SQLSTATE` y el
`correlation_id` sigan ahí.

### Decisiones tomadas en este paso

1. **Un solo punto de escritura.** Había dos sitios llamando a `AuditEvent::create()` con su
   propia idea de qué campos rellenar. Dos se convierten en cinco, y entonces cada evento
   trae lo que su autor recordó poner: la traza deja de ser comparable justo cuando hace
   falta compararla. Ahora `AuditRecorder` garantiza correlación, actor y clasificación
   siempre.
2. **El contexto de auditoría no admite campos P3, y se comprueba.** El error que se comete
   de verdad no es inventarse un campo raro: es volcar el modelo entero con `toArray()` o
   añadir el documento «para tener más detalle». `AuditRecorder` recorre las claves en toda
   su profundidad y **lanza** si alguna está clasificada P3 o P4. No filtra en silencio: un
   evento que se escribe a medias sin que nadie se entere es peor que uno que falla.
3. **Las escrituras registran nombres de campo, nunca valores.** `['fields' => ['birth_date']]`
   dice que la fecha cambió; `['birth_date' => '1985-03-12']` sería copiar el dato. La
   distinción —nombre como *valor* sí, como *clave* no— tiene su propia prueba.
4. **Un error no guarda el mensaje de su excepción.** Por lo mismo que los logs: el mensaje
   trae la consulta con los valores. Se guarda clase, fichero y línea, que es lo que sirve
   para encontrar el fallo.
5. **`Relationship` no audita ediciones genéricas.** Sus cambios de estado ya dejan eventos
   con significado —`relationship.activated`, `relationship.ended`— desde las clases de
   transición. Registrar además «relationships.updated» contaría dos veces el mismo hecho
   con dos nombres. La creación sí se audita: no pasa por ninguna transición.
6. **Auditar un error nunca puede tapar el error original.** El manejador va en su propio
   `try`: si auditar falla —la base caída, que es cuando más errores hay— se degrada a log,
   nunca a silencio.
7. **La redacción no es configurable por entorno.** Un log sin redactar «solo en desarrollo»
   acaba copiándose a un entorno compartido, y el fichero vive años.

### Lo que sigue sin resolver

`audit_events` no admite `INSERT` sin tenant resuelto: la RLS lo rechaza, y hace bien. Un
login fallido ocurre **antes** de resolver el tenant, así que hoy ese suceso no se puede
auditar. `recordError()` lo detecta y deja constancia en el log en vez de perderlo, pero eso
es una mitigación, no la solución. **Es trabajo de B5**, que es quien decide cómo se resuelve
el tenant antes de autenticar. Anotado en `## Deuda conocida`.


## Paso B5 — hecho el 23-ago-2026

Autenticación, MFA y roles con alcance. **364 pruebas, 538 aserciones.**

### Lo construido

| Qué | Dónde |
|---|---|
| Función de resolución de tenant, `SECURITY DEFINER` | `database/migrations/2026_08_25_000100_add_tenant_resolver.php` |
| Resolución por subdominio o cabecera | `app/Domains/Identity/Application/TenantResolver.php` |
| Middleware que resuelve antes de autenticar | `app/Http/Middleware/ResolveTenantFromRequest.php` |
| TOTP según RFC 6238 | `app/Domains/Identity/Domain/Totp.php` |
| Quién necesita segundo factor, deducido del techo del rol | `app/Domains/Identity/Application/MfaRequirement.php` |
| Intento de acceso: límite, no enumeración, MFA, auditoría | `app/Domains/Identity/Application/LoginAttempt.php` |
| Vectores del RFC y pruebas del flujo | `tests/Domains/Identity/{TotpTest,AuthenticationTest}.php` |

### El nudo que B4 dejó abierto, resuelto

Desde B1 `tenants` está bajo RLS, lo que hacía imposible el login: hay que saber a qué
tenant pertenece quien entra **antes** de poder fijar `app.tenant_id`. Y por lo mismo, un
intento de acceso fallido no se podía auditar.

La salida no fue relajar la política, sino una función que es la única grieta y está tallada
para no servir para nada más: devuelve **un uuid**, exige slug exacto, solo resuelve tenants
activos y `REVOKE`a a todo el mundo salvo al rol de aplicación.

**`SECURITY DEFINER` por sí solo no bastaba**, y encontrarlo costó una vuelta: la primera
versión devolvía NULL incluso para un tenant activo, porque `FORCE ROW LEVEL SECURITY`
—puesto en B1 a propósito— aplica la política **también al dueño de la tabla**, que es
justamente bajo quien corre una función `SECURITY DEFINER`. Hizo falta una política de solo
lectura para el dueño del esquema. Las políticas permisivas se combinan con OR, así que el
rol de aplicación sigue viendo solo su fila y **no gana ninguna capacidad de enumerar**;
hay prueba de que `%`, `colegio` y `colegio%` devuelven null y de que la tabla entera
sigue dando cero filas.

Se descartó la alternativa de activar la política con una variable de sesión
(`app.tenant_lookup = 'on'`) porque el rol de aplicación puede fijarla él mismo con
`set_config`: sería una puerta con la llave puesta al lado.

**Con esto, un intento de acceso fallido ya queda auditado**, que es lo que B4 no podía
hacer.

### TOTP escrito a mano, y por qué eso es defendible

`api.github.com` sigue bloqueado, así que no se puede instalar ninguna librería de segundo
factor. Escribirlo no es «criptografía propia»: TOTP es HMAC-SHA1 sobre un contador de
tiempo más un truncado, todo con `hash_hmac` de PHP. Lo delicado es equivocarse en el
truncado o en el relleno del contador, y contra eso hay una defensa concreta: **el RFC 6238
publica vectores de prueba y la implementación se compara con los seis, uno a uno**. Pasan
todos. Si mañana se instala una librería, se cambia por dentro y las pruebas siguen valiendo.

Decisiones que no son cosméticas: SHA1 porque es lo que implementan los autenticadores
reales; ventana de ±1 intervalo para el reloj desfasado sin regalar dos minutos a un código
robado; comparación en tiempo constante y **sin salir del bucle al acertar**, porque salir
antes revelaría por tiempo qué intervalo coincidió.

### No enumeración de usuarios, también en el tiempo

Correo inexistente y contraseña incorrecta devuelven **el mismo resultado y el mismo
mensaje**. El motivo real vive solo en `audit_events`, que sí puede leer quien tenga
permiso, y hay prueba de que la auditoría sí los distingue.

La uniformidad tiene que ser además temporal: si el caso «usuario inexistente» volviera
antes por no ejecutar el hash, el reloj delataría lo que el mensaje calla. Por eso se
verifica siempre contra un hash señuelo. **Ese señuelo se genera con el driver configurado y
no se escribe a mano**: el literal inventado de la primera versión no era bcrypt válido y
`Hash::check` lanzaba, que es como se descubrió.

El correo probado **no** se guarda en la auditoría: es P2, y cuando hay usuario
`actor_user_id` ya dice quién. Hay prueba.

### Decisiones tomadas en este paso

1. **Quién necesita MFA se deduce del techo del rol, no de una lista aparte.** Cuando se
   añada un rol con techo P3 quedará cubierto sin que nadie se acuerde.
2. **Un rol P3 sin MFA configurado no entra «mientras tanto».** Dejarlo pasar hasta que lo
   configure sería justo el agujero que el segundo factor viene a tapar.
3. **Un rol vencido deja de exigir MFA**, porque deja de otorgar nada (CA-08). El motivo por
   el que se pide un segundo factor no puede ser un rol que ya no sirve.
4. **El límite de intentos es por tenant, correo e IP a la vez.** Solo por IP deja pasar el
   ataque distribuido; solo por correo permite bloquear a alguien a propósito desde fuera.
5. **Sesión cifrada, solo por HTTPS y `SameSite=strict` por defecto**, no por variable de
   entorno. La sesión vive en Redis, que en un incidente puede volcarse entero. Se prefiere
   que cueste bajar la protección a que cueste subirla.
6. **Si la sesión no corresponde al tenant resuelto, se aborta con 403** en vez de decidir
   cuál de los dos gana.
7. **No resolver el tenant no cambia la respuesta.** Contestar «ese colegio no existe» sería
   la enumeración que el resolver evita.

### Criterios

| Criterio | Estado |
|---|---|
| **CA-07** — cerrar la relación revoca el acceso en la siguiente petición | verde desde B3; sigue con prueba |
| **CA-08** — un `role_assignment` vencido no otorga permisos | verde desde B2/B3; B5 añade que tampoco exige MFA |
| **CA-09** — el login exige MFA para todo rol con techo P3 | verde, con prueba por rol y también de que los de techo P2 no lo necesitan |


## Decisiones tomadas

| Fecha | Decisión | Dónde |
|---|---|---|
| 2026-08-23 | Stack: Laravel 11 + Inertia + Vue 3 + PostgreSQL 16 | `CLAUDE.md` §2 · ADR 0001 |
| 2026-08-23 | Producto: suite completa RR. HH. + SST; secuencia comercial iniciando donde no hay competencia | `AGENTS.md` §29 |
| 2026-08-23 | Cliente ancla: Colegio Finlandés Juan Pablo II, Madrid (Cundinamarca) | `docs/anchor/colegio-finlandes.md` |
| 2026-08-23 | Nómina fuera de alcance en v1; se integra | `AGENTS.md` §2 |
| 2026-08-23 | Frontera de habilitación en enfermería escolar | ADR 0002 |
| 2026-08-23 | Slice 1 = Motor de reportes obligatorios (C600 + EVI), SG-SST pasa a Slice 2 | `ROADMAP.md` |
| 2026-08-23 | Los estudiantes se modelan como agregado, sin identidad | ADR 0003 |
| 2026-08-23 | Datos sintéticos hasta que el Slice 0 pase su DoD; reales solo del personal | `PLAN.md` B6 y D1 |
| 2026-08-23 | El colegio ancla confirmó el piloto | `docs/anchor/colegio-finlandes.md` |
| 2026-08-23 | Sesión, caché y colas en Redis para no crear tablas de personas sin `tenant_id` | A1, arriba |

## Bloqueos

### 1. `docker compose up` no se pudo ejecutar en el entorno de la sesión

La política de egreso de la organización devuelve **403** para
`production.cloudfront.docker.com`, que es donde el registro de Docker sirve las capas de las
imágenes. Los manifiestos se descargan; las capas, no. Ninguna imagen puede descargarse.

`docker-compose.yml`, el `Dockerfile` y los scripts de arranque están escritos y
`docker compose config` los valida, pero **el arranque por contenedores está sin ejecutar**.

Lo que sí se verificó, contra el PostgreSQL 16.13 y el Redis instalados en la máquina:
`010-roles.sql` y `020-test-database.sql` ejecutados tal cual, atributos de los dos roles,
migraciones con el rol dueño, la aplicación sirviendo y la pantalla renderizando en Chromium.
Es decir: **la sustancia del paso A1 está verificada; falta confirmar la orquestación**.

> **Acción para la próxima sesión en una red sin esa restricción:** `docker compose up` y
> confirmar que responde en `localhost:8080`. Si algo falla ahí será de la orquestación, no
> del diseño.

### 2. No se pudo instalar el analizador estático (compuerta TYPECHECK del DoD)

`api.github.com` y `codeload.github.com` devuelven **403** por la misma política. Composer
descarga de `repo.packagist.org` sin problema y clona por git desde `github.com`, pero
`phpstan/phpstan` se sirve como zipball de `api.github.com`, así que `larastan/larastan`
no se puede instalar aquí. Se revirtió el intento y `composer.json`/`composer.lock` quedaron
consistentes (`composer validate` en verde, `composer install --dry-run` sin operaciones).

Consecuencia: **el DoD nivel A de A1 y de A2 está en verde en BUILD, LINT, UNIT,
INTEGRATION y DOCUMENTATION, y sin verificar en TYPECHECK.** No se marca como cumplido lo
que no se ejecutó. A2 tampoco pudo cerrarlo: la restricción es del entorno, no del paso.

> **Acción en la primera sesión con red sin esa restricción:**
> `composer require --dev larastan/larastan` y un `phpstan.neon` en nivel 5 como mínimo.
> Es lo único que queda abierto de la Etapa A.

### 3. Decisión de producto pendiente — ¿puede la aplicación borrar un tenant?

Encontrado en la auditoría de B1, **no corregido a propósito**: los doce `FOREIGN KEY` que
apuntan a `tenants` son `ON DELETE CASCADE`, y `platform_app` tiene `DELETE` sobre `tenants`
por los privilegios por defecto del script de roles. Un solo `DELETE FROM tenants` desde la
aplicación destruye organización, sedes, personas, identidades, relaciones, asignaciones,
usuarios, roles y matrículas de ese tenant, sin vuelta atrás.

Lo que sí está bien resuelto: `audit_events` **no** tiene `FOREIGN KEY` a `tenants`, así que
la auditoría sobrevive al borrado. Eso sostiene la retención de 5 años de
`data-classification.md` y parece deliberado.

No se toca porque es una decisión de producto y de derecho, no una de ingeniería, y §5 de
`CLAUDE.md` dice que eso se pregunta en vez de improvisarlo:

- **Recomendación:** revocar `DELETE` sobre `tenants` al rol de aplicación. Nadie lo usa hoy,
  el ciclo de vida ya se expresa con `tenants.status`, y darlo de baja convierte una
  operación irreversible en imposible desde el código de producto.
- **Contra:** el derecho de supresión del titular puede exigir borrado efectivo. Si es así,
  el borrado debe ser un procedimiento explícito con respaldo previo y responsable
  identificado, no un privilegio permanente de la aplicación.

Mientras no haya respuesta, el riesgo sigue abierto y **ningún paso posterior debería
implementar borrado de tenant**.

### 4. Dos huecos de la máquina de estados que B2 tuvo que interpretar

`specs/identity/SPEC.md` define la máquina como
`PLANNED → ACTIVE → SUSPENDED → ACTIVE` y `ACTIVE → ENDED (terminal)`, pero su **tabla** de
transiciones solo declara tres filas. Dos caminos del diagrama quedan sin actor,
precondiciones ni evento de auditoría, y B2 no podía dejarlos sin implementar:

- **`SUSPENDED → ACTIVE`** — implementado como `ReactivateRelationship`, con evento propio
  `relationship.reactivated` en vez de reutilizar `relationship.activated`. Reanudar no es
  activar por primera vez y la auditoría tiene que poder distinguirlos.
- **`SUSPENDED → ENDED`** — admitido, aunque la tabla solo contemple cerrar desde `ACTIVE`.
  Respetarla al pie de la letra dejaría una relación suspendida sin forma de cerrarse:
  habría que reactivarla para terminarla, que es peor y ensucia la auditoría.

Las dos son interpretaciones defendibles, pero son **mías, no del SPEC**. Hace falta
confirmarlas o corregirlas —y en su caso añadir las filas a la tabla del SPEC con actor y
precondiciones— antes de B9.

### 5. `rector` es un rol sin permisos — y bloquea su pantalla del paso B7

`docs/architecture/permissions.md` lo lista en «Roles y alcance» con alcance
`legal_entity` y techo P3, pero **no tiene columna en la matriz recurso × acción**. No hay
ninguna celda que diga qué puede hacer.

B3 no le inventa permisos: la matriz falla cerrada, así que hoy el rector no puede hacer
nada. Hay prueba que lo deja constante y visible, no como un olvido silencioso.

Esto importa pronto: `PLAN.md` B7 pide diseñar «MY WORK del rector», y no se puede diseñar
la bandeja de alguien que no tiene permisos definidos.

- La descripción dice «alias de dirección», y comparte alcance y techo con `admin_rrhh`.
  La lectura más probable es que sea liderazgo y no administración de RR. HH.: vería
  personas, relaciones, matrícula y reportes, pero no gestionaría usuarios ni el DPA.
- **Es una suposición mía y no la implemento.** Hace falta que alguien añada la columna al
  documento antes de B7.

## Deuda conocida

| Qué | Por qué importa | Cuándo se paga |
|---|---|---|
| Compuerta TYPECHECK sin herramienta | Es una de las seis del DoD nivel A. A2 no pudo cerrarla: `api.github.com` bloqueado | Primera sesión con red sin restricción |
| En acciones de colección —listar, crear— no hay `resourceTenantId` que comparar y esa comprobación se salta | La protección por fila queda en el global scope y la RLS, que sí la cubren con 30 pruebas. Hueco teórico: una Policy con la instancia delante que olvide pasar su `tenant_id` | Cuando existan controladores reales (B8): que el contexto se construya desde el modelo y no a mano |
| TOTP implementado en el repositorio en vez de con una librería | `api.github.com` está bloqueado y no se puede instalar ninguna. Está verificado contra los seis vectores del RFC 6238, pero una librería mantenida recibe revisiones que este código no | Cuando la red lo permita: sustituir por dentro; las pruebas del RFC siguen valiendo |
| La deuda de Laravel 11 con CVE-2026-48019 sigue **sin tocar** | B5 no usó la regla de validación `email` —busca por correo directamente— así que el flujo actual no la dispara. **B8 sí construirá el formulario de acceso** y ahí sí | B8: usar `email:rfc,strict`, nunca la regla `email` por defecto |
| Solo hay Policies para Person, Relationship y AuditEvent | La matriz declara 17 recursos; el resto se autoriza llamando al `Authorizer` directamente, sin Policy | B8, al construir las pantallas que los usan |
| OBSERVABILITY: hay correlación por petición, tenant en cada línea, logs estructurados JSON, health check y comprobación de dependencias. **Faltan métricas, tracing distribuido y monitoreo de colas** (§21 del harness) | Es compuerta del DoD nivel B | Antes de B9, que es donde se cierra el DoD del slice |
| El invariante 5 lo sostienen el modelo y el repositorio, no la base | `platform_app` conserva `DELETE` sobre `relationships`. `audit_events` sí lo tiene revocado en base | Considerar revocarlo también aquí; encaja con la decisión pendiente del bloqueo 3 |
| `enrollment_snapshots` no tiene identificadores, pero la combinación sede + año + grado + jornada + sexo + rango de edad + condición con `headcount` bajo es cuasi-identificadora | Dentro del tenant es aceptable —el colegio ya conoce a sus estudiantes—; fuera del tenant no | C5: la supresión de celdas con `headcount < 5` en exportaciones ya está prevista en `core-entities.md` |
| El E2E de Playwright no corre en el hook de pre-commit | Solo el grupo `tenant-isolation` bloquea el commit; un E2E roto pasaría | Cuando exista CI, que es su sitio: 4 s por commit no se justifican |
| Laravel 11.56 arrastra tres avisos de seguridad sin parche en su rama, uno **alto**: CVE-2026-48019, inyección CRLF en la regla de validación `email`. Corregido solo en 12.60+ | El paso B5 implementa autenticación y validará correos | B5: usar `email:rfc,strict` en vez de la regla `email` por defecto, o evaluar con ADR el salto a Laravel 12 |
| `TenantContext::clear()` deja `app.tenant_id` en cadena vacía y ejecuta una consulta aunque nunca se hubiera fijado tenant | Una consulta por petición sin necesidad; ya no es un riesgo de corrección gracias al `NULLIF` | B2, al revisar el dominio |
| `docker compose up` sin ejecutar | Es el entregable literal de A1 | Primera sesión con red sin restricción |
| La migración 000300 solo concede privilegios sobre `audit_events`; el resto depende de `ALTER DEFAULT PRIVILEGES` del script de roles | Si alguien crea una tabla fuera del arranque, la aplicación podría quedarse sin permisos o con demasiados | B1, en la revisión crítica de las migraciones |

## Preguntas abiertas para el ancla

1. ¿Existe la autoevaluación de estándares mínimos SG-SST de 2025 y se reportó antes del 31 de julio de 2026?
2. Conteo exacto de trabajadores vinculados: ¿supera 50? Define el conjunto de estándares aplicable.
3. ¿Dónde se registra hoy lo que atiende la enfermería, y quién autoriza la administración de medicamentos?
4. ¿Hay contratistas con obligaciones de SST en sede (transporte, aseo, alimentación)?
5. El comedor escolar figura atendiendo 0 estudiantes y sin cumplimiento de la Res. 2674 de 2013. ¿Se sirve alimento?
6. ¿Quién diligencia hoy el C600 y cuánto tiempo toma?

## Próximo paso concreto

**Paso B6 de `PLAN.md`:** tenant demo con datos sintéticos. **DoD nivel A**, más ligero que
los anteriores.

Lo que pide: un seeder que genere un tenant con la forma del colegio ancla descrita en
`docs/anchor/colegio-finlandes.md` —una entidad jurídica, una sede rural con código DANE,
64 personas con su distribución real por tipo de personal, estatuto docente, nivel
educativo, sexo y rango de edad, y `enrollment_snapshots` que sumen 883 estudiantes por
grado, jornada y condición, con 13 en condición de discapacidad—. Más un segundo tenant
para las pruebas de aislamiento.

**Todos los nombres y documentos son ficticios y generados.** Criterios CA-10 y CA-11.

Cosas del terreno que conviene saber antes de empezar:

- `DatabaseSeeder` está vacío a propósito desde A1, con un comentario explicando por qué.
- **Crear un tenant exige fijar el contexto al id nuevo antes de insertar**: `tenants` está
  bajo RLS con su propio `id` como llave. El helper `createTenant()` de `tests/Pest.php` ya
  lo hace y sirve de referencia.
- Toda escritura deja `AuditEvent` desde B4. Un seeder de 64 personas generará bastantes
  eventos; es correcto, pero conviene saberlo antes de mirar la tabla.
- El trigger de mayoría de edad rechaza cualquier `birth_date` de menor: la distribución por
  rango de edad tiene que respetarlo.
- `enrollment_snapshots` no admite ninguna columna identificadora, y hay prueba.

> **Nota de entorno para quien retome:** sin Docker, los servicios locales se levantan con
> `pg_ctlcluster 16 main start` y `redis-server --daemonize yes`. La base `platform` y los dos
> roles ya existen y sobreviven entre sesiones; `platform_test` se re-migra con
> `composer test:prepare`. Los gates: `composer lint`, `composer test`, `composer test:tenant`,
> `npm run e2e` (con `PLAYWRIGHT_CHROMIUM_PATH=/opt/pw-browsers/chromium` en este entorno).
> La suite tarda ~30 s.
