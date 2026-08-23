-- ─────────────────────────────────────────────────────────────────────────────
-- Dos roles, una sola razón (PLAN.md A1 · SECURITY.md · ARCHITECTURE.md).
--
--   platform_owner  dueño del esquema. Crea las tablas y ejecuta las migraciones.
--   platform_app    runtime. Sirve peticiones y colas.
--
-- platform_app NO es superusuario, NO tiene BYPASSRLS y NO es dueño de ninguna tabla.
-- Las tres condiciones juntas son lo que hace que:
--   · ALTER TABLE ... FORCE ROW LEVEL SECURITY no lo pueda revertir la aplicación,
--   · el REVOKE UPDATE/DELETE sobre audit_events sea una garantía y no una convención.
--
-- Un rol de aplicación con BYPASSRLS convierte todo el aislamiento multi-tenant en
-- decoración. Por eso el arranque lo verifica y se niega a levantar si no se cumple.
--
-- El entrypoint de la imagen ya ejecuta este archivo conectado a POSTGRES_DB y como
-- superusuario; es el único momento del ciclo de vida en que ese rol interviene.
-- ─────────────────────────────────────────────────────────────────────────────

\set ON_ERROR_STOP on

\set db_name        `echo "$POSTGRES_DB"`
\set owner_role     `echo "${DB_OWNER_ROLE:-platform_owner}"`
\set owner_password `echo "${DB_OWNER_PASSWORD:?DB_OWNER_PASSWORD es obligatorio}"`
\set app_role       `echo "${DB_APP_ROLE:-platform_app}"`
\set app_password   `echo "${DB_APP_PASSWORD:?DB_APP_PASSWORD es obligatorio}"`

CREATE ROLE :"owner_role" LOGIN PASSWORD :'owner_password'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT;

CREATE ROLE :"app_role" LOGIN PASSWORD :'app_password'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT;

-- El dueño del esquema manda dentro de la base; el superusuario solo la arrancó.
ALTER DATABASE :"db_name" OWNER TO :"owner_role";

-- Nadie escribe en el esquema público salvo su dueño. En particular, la aplicación
-- no puede crear tablas: si pudiera, sería dueña de ellas y la RLS forzada no la ataría.
REVOKE ALL ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO :"owner_role";
GRANT USAGE ON SCHEMA public TO :"app_role";

-- Que nadie más pueda siquiera abrir la base.
REVOKE ALL ON DATABASE :"db_name" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db_name" TO :"owner_role", :"app_role";

-- La aplicación opera sobre las tablas del dominio, pero no las posee.
-- DELETE se concede de forma general y se revoca donde el dominio lo prohíbe:
-- la migración 000300 lo retira de audit_events junto con UPDATE y TRUNCATE.
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_role";

-- Sin privilegio de ejecución sobre funciones nuevas por defecto: se concede
-- explícitamente cuando un slice lo necesite.
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
