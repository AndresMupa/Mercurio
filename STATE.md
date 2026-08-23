# STATE.md — Testigo entre sesiones

> **Léelo al abrir la sesión. Actualízalo antes de cerrarla.**
> Si este archivo está desactualizado, la siguiente sesión trabaja a ciegas.

---

## Slice actual

**Slice 0 — Foundation** · **Etapa A completa (A1 y A2)**, siguiente **B1** de `PLAN.md`
`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`

Estado: **A1 y A2 en verde salvo la compuerta TYPECHECK**, que no pudo instalarse por una
restricción de red del entorno (ver `## Bloqueos`). El harness operativo ya se cumple solo:
un commit con una prueba de aislamiento rota **no pasa**, verificado a mano. El colegio ancla
dio luz verde al piloto el 23 de agosto de 2026.

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

## Deuda conocida

| Qué | Por qué importa | Cuándo se paga |
|---|---|---|
| Compuerta TYPECHECK sin herramienta | Es una de las seis del DoD nivel A. A2 no pudo cerrarla: `api.github.com` bloqueado | Primera sesión con red sin restricción |
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

**Paso B1 de `PLAN.md`:** migraciones, RLS y auditoría bloqueada. Aquí empieza el Slice 0 de
verdad y el DoD sube a **nivel B**.

Antes de tocar nada, leer: `docs/architecture/core-entities.md`,
`docs/architecture/data-classification.md` y `specs/identity/SPEC.md`.

El trabajo de B1 es la **revisión crítica de las tres migraciones contra la especificación**,
no volver a escribirlas. Ya corren en limpio y el grupo `tenant-isolation` está en verde, así
que lo que queda es lo que ninguna prueba puede decidir por ti:

1. Contrastar las 13 tablas y cada columna contra `core-entities.md` y la clasificación de
   datos. La migración declara clasificaciones en comentarios (`// P3`); comprobar que
   coinciden con `data-classification.md`.
2. Verificar CA-01, CA-02, CA-03, CA-06, CA-11 y CA-12 del SPEC uno por uno.
3. **Desconfiar del resto de la migración 000200.** El hallazgo del `NULLIF` (arriba) salió
   de una prueba en rojo; puede haber más supuestos igual de frágiles que ninguna prueba
   actual toca.
4. Revisar la deuda de privilegios de la migración 000300 anotada en `## Deuda conocida`.
5. Ejecutar `/tenant-test` y `/dod`.

> **Nota de entorno para quien retome:** sin Docker, los servicios locales se levantan con
> `pg_ctlcluster 16 main start` y `redis-server --daemonize yes`. La base `platform` y los
> dos roles ya existen y sobreviven entre sesiones; `platform_test` se re-migra con
> `composer test:prepare`.
