<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resolución del tenant **antes** de autenticar (paso B5).
 *
 * El problema que resuelve: desde B1, `tenants` está bajo RLS y su política compara contra
 * `app.tenant_id`. Perfecto para todo lo que ocurre dentro de una sesión, e imposible para
 * el login, que tiene que averiguar a qué tenant pertenece quien está entrando *antes* de
 * poder fijar ese valor. Lo mismo bloqueaba auditar un intento de acceso fallido: sin
 * tenant resuelto, la RLS rechaza el `INSERT` en `audit_events`.
 *
 * La salida no es relajar la política. Es esta función, que es la única grieta y está
 * tallada para que no sirva para nada más:
 *
 *  - Devuelve **un uuid**, nunca una fila. Ni nombre, ni plan, ni región, ni estado del DPA.
 *  - Exige coincidencia exacta de `slug`. No admite patrones ni devuelve conjuntos, así que
 *    no se puede recorrer la lista de clientes con ella.
 *  - Solo resuelve tenants activos: uno suspendido no se puede usar para entrar.
 *  - `SET search_path = public` es obligatorio en una función `SECURITY DEFINER`. Sin eso,
 *    quien controle su `search_path` puede anteponer un esquema con una tabla `tenants`
 *    falsa y hacer que la función —que corre con los privilegios del dueño— lea la suya.
 *
 * **`SECURITY DEFINER` por sí solo no bastaba, y merece explicación.** La primera versión
 * de esta migración devolvía NULL incluso para un tenant activo. El motivo es que
 * `FORCE ROW LEVEL SECURITY` —puesto en B1 a propósito— aplica la política **también al
 * dueño de la tabla**, que es justamente bajo quien corre una función `SECURITY DEFINER`.
 * Sin una política que lo contemple, el dueño tampoco ve nada.
 *
 * Se añade entonces una política de solo lectura para el dueño del esquema. Las políticas
 * permisivas se combinan con OR, así que el rol de aplicación sigue viendo únicamente su
 * propia fila por `tenant_isolation`, y **no gana ninguna capacidad de enumerar**: solo el
 * dueño ve el conjunto, y el dueño no sirve peticiones. Lo comprueba una prueba.
 *
 * Se descartó la alternativa de una política activada por una variable de sesión
 * (`app.tenant_lookup = 'on'`) porque el rol de aplicación puede fijar esa variable él
 * mismo con `set_config`: sería una puerta con la llave puesta al lado.
 *
 * Lo que sí permite y hay que aceptar: comprobar si un slug existe. Es inevitable en
 * cualquier plataforma multiempresa donde el tenant viaja en el subdominio, porque el
 * subdominio ya es público.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION resolve_tenant_id_by_slug(p_slug text)
            RETURNS uuid
            LANGUAGE sql
            SECURITY DEFINER
            STABLE
            SET search_path = public
            AS $$
                SELECT id FROM tenants WHERE slug = p_slug AND status = 'active'
            $$
        SQL);

        // Lectura de `tenants` para el dueño del esquema, que es bajo quien corre la
        // función. Sin esto, FORCE RLS la deja sin ver nada, ni siquiera su propia tabla.
        $owner = $this->role('pgsql_owner');
        DB::statement("
            CREATE POLICY tenant_schema_owner_lookup ON tenants
            FOR SELECT TO {$owner}
            USING (true)
        ");

        $app = $this->role('pgsql');

        // Nadie por defecto; solo el rol de la aplicación, y explícitamente.
        DB::statement('REVOKE ALL ON FUNCTION resolve_tenant_id_by_slug(text) FROM PUBLIC');
        DB::statement("GRANT EXECUTE ON FUNCTION resolve_tenant_id_by_slug(text) TO {$app}");
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS resolve_tenant_id_by_slug(text)');
        DB::statement('DROP POLICY IF EXISTS tenant_schema_owner_lookup ON tenants');
    }

    /** Mismo criterio que la migración 000300: identificador validado antes de interpolar. */
    private function role(string $connection): string
    {
        $role = config("database.connections.{$connection}.username");

        if (! is_string($role) || ! preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $role)) {
            throw new RuntimeException(
                "La conexión «{$connection}» no declara un rol válido. Ver SECURITY.md."
            );
        }

        return $role;
    }
};
