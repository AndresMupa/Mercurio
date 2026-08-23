-- Base de pruebas, con exactamente los mismos dueños y privilegios que la de trabajo.
--
-- Tiene que ser una base real de PostgreSQL y no sqlite en memoria: sin RLS, las
-- pruebas de aislamiento pasarían siempre sin comprobar nada. Y tiene que tener la
-- misma separación de roles, porque parte de lo que se prueba es justamente esa.

\set ON_ERROR_STOP on

\set owner_role `echo "${DB_OWNER_ROLE:-platform_owner}"`
\set app_role   `echo "${DB_APP_ROLE:-platform_app}"`
\set test_db    `echo "${DB_TEST_DATABASE:-platform_test}"`

CREATE DATABASE :"test_db" OWNER :"owner_role";

REVOKE ALL ON DATABASE :"test_db" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"test_db" TO :"owner_role", :"app_role";

\connect :"test_db"

REVOKE ALL ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO :"owner_role";
GRANT USAGE ON SCHEMA public TO :"app_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
