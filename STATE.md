# STATE.md — Testigo entre sesiones

> **Léelo al abrir la sesión. Actualízalo antes de cerrarla.**
> Si este archivo está desactualizado, la siguiente sesión trabaja a ciegas.

---

## Slice actual

**Slice 0 — Foundation** · **A1, A2 y B1 completos**, siguiente **B2** de `PLAN.md`
`COMMERCIAL VALUE: —` (único slice estructural permitido) · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`

Estado: esquema, RLS y auditoría bloqueada verificados. **B1 encontró y cerró una exposición
cross-tenant real** (ver abajo). Todo en verde salvo la compuerta TYPECHECK, que sigue sin
herramienta por una restricción de red del entorno (`## Bloqueos`). El harness operativo se
cumple solo: un commit con una prueba de aislamiento rota **no pasa**, verificado a mano.
El colegio ancla dio luz verde al piloto el 23 de agosto de 2026.

**Hay una decisión de producto esperando respuesta humana** en `## Bloqueos`: si la aplicación
debe poder borrar un tenant.

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

## Deuda conocida

| Qué | Por qué importa | Cuándo se paga |
|---|---|---|
| Compuerta TYPECHECK sin herramienta | Es una de las seis del DoD nivel A. A2 no pudo cerrarla: `api.github.com` bloqueado | Primera sesión con red sin restricción |
| La regla 5 de `data-classification.md` —«un campo sin clasificación rompe el build»— no existe como mecanismo | Es una regla escrita que nada comprueba; hoy se sostiene por revisión humana | B2, junto a los invariantes del dominio: registro de clasificación por columna + prueba que exija nivel a toda columna del esquema |
| `audit_events` no admite `INSERT` sin tenant resuelto: la RLS lo rechaza | Un login fallido ocurre **antes** de resolver el tenant, y CA-04 exige auditar la denegación. Comprobado: el `INSERT` sin contexto falla | B4/B5: resolver el tenant desde la petición antes de autenticar, o abrir una vía de auditoría de plataforma |
| La RLS sobre `tenants` bloquea también la resolución de tenant por `slug` antes del login | B5 necesita encontrar el tenant para poder autenticar dentro de él | B5: resolver por host/slug y fijar el contexto antes de autenticar; si hace falta lectura previa, función `SECURITY DEFINER` que devuelva un solo id y no permita enumerar |
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

**Paso B2 de `PLAN.md`:** modelos, repositorios e invariantes. El dominio expresado en código,
no en el controlador.

Lo que pide el paso:

1. Modelos y repositorios de `app/Domains/Identity` y `app/Domains/People` con la estructura
   DOMAIN / APPLICATION / INFRASTRUCTURE / INTERFACES.
2. La máquina de estados de `relationships` con **clases de transición explícitas**, no
   booleanos: `PLANNED → ACTIVE → SUSPENDED → ACTIVE` y `ACTIVE → ENDED` terminal. El SPEC
   prohíbe el borrado como transición.
3. Los nueve invariantes del SPEC, cada uno con prueba unitaria propia.
4. Ninguna lógica de negocio en controladores.

Contexto que B1 deja resuelto o abierto para este paso:

- Los invariantes 1, 2, 3, 6 y 9 ya están garantizados en base de datos y con prueba. B2 los
  expresa en el dominio, pero no tiene que inventarlos.
- El invariante 5 —«una relación no se borra: se cierra»— hoy es solo convención: el rol de
  aplicación tiene `DELETE` sobre `relationships`. El SPEC dice que se garantiza con «política
  de repositorio + test», así que es trabajo de B2. Vale la pena considerar revocar también el
  `DELETE` en base de datos, igual que en `audit_events`.
- El registro de clasificación por columna (regla 5 de `data-classification.md`) es el sitio
  natural para B2, y B3 lo va a necesitar para exigir `purpose` en P3.
- El modelo `User` va en `App\Domains\Identity\Domain\User`: `config/auth.php` ya apunta ahí
  y hoy no resuelve, a propósito.

> **Nota de entorno para quien retome:** sin Docker, los servicios locales se levantan con
> `pg_ctlcluster 16 main start` y `redis-server --daemonize yes`. La base `platform` y los dos
> roles ya existen y sobreviven entre sesiones; `platform_test` se re-migra con
> `composer test:prepare`. Los gates: `composer lint`, `composer test`, `composer test:tenant`,
> `npm run e2e` (con `PLAYWRIGHT_CHROMIUM_PATH=/opt/pw-browsers/chromium` en este entorno).
