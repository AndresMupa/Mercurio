# STATE.md — Testigo entre sesiones

> **Léelo al abrir la sesión. Actualízalo antes de cerrarla.**
> Si este archivo está desactualizado, la siguiente sesión trabaja a ciegas.

---

## Slice actual

**Slice 0 — Foundation** · paso **A1 completado**, siguiente **A2** de `PLAN.md`
`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`

Estado: **A1 en verde salvo la compuerta TYPECHECK**, que no pudo instalarse por una
restricción de red del entorno (ver `## Bloqueos`). El colegio ancla dio luz verde al piloto
el 23 de agosto de 2026.

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

Consecuencia: **el DoD nivel A de A1 está en verde en BUILD, LINT, UNIT, INTEGRATION y
DOCUMENTATION, y sin verificar en TYPECHECK.** No se marca como cumplido lo que no se ejecutó.

> **Acción para A2**, que es donde vive el harness operativo:
> `composer require --dev larastan/larastan` y un `phpstan.neon` en nivel 5 como mínimo.

## Deuda conocida

| Qué | Por qué importa | Cuándo se paga |
|---|---|---|
| Compuerta TYPECHECK sin herramienta | Es una de las seis del DoD nivel A | A2 |
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

**Paso A2 de `PLAN.md`:** armar el harness operativo. Pest ya está instalado y el grupo
`tenant-isolation` ya corre (13 pruebas), así que A2 se reduce a:

1. Instalar Playwright y dejar un E2E mínimo contra la pantalla de estado.
2. Conectar `scripts/pre-commit-tenant-isolation.sh` como hook de git y **verificar a mano
   que bloquea de verdad** rompiendo una prueba de aislamiento a propósito. El script asume
   `./vendor/bin/pest` desde la raíz del repositorio y la aplicación vive en `apps/platform`:
   hay que ajustar la ruta o el hook no ejecutará nada.
3. Añadir `TenantContext::assertRoleCannotBypassRls()` al arranque de la aplicación.
   `FoundationCheck` ya lo envuelve; falta engancharlo a un `ServiceProvider` para que la
   aplicación se niegue a levantar.
4. Instalar `larastan/larastan` y cerrar la compuerta TYPECHECK (bloqueo 2).
