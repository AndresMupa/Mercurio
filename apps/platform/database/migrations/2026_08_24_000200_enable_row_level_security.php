<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aislamiento multi-tenant en la base de datos.
 *
 * Segunda de las tres capas (AGENTS.md §16): guard de aplicación + RLS + pruebas.
 * FORCE es deliberado: sin él, el dueño de la tabla se salta la política y la garantía
 * dependería de con qué rol corre la aplicación.
 *
 * NULLIF no es adorno. current_setting('app.tenant_id', true) solo devuelve NULL en una
 * conexión que nunca fijó la variable; en cuanto una petición la fija, ni RESET ni
 * set_config(..., NULL, ...) la devuelven a NULL: la dejan en cadena vacía. Sin NULLIF,
 * ''::uuid lanza «invalid input syntax for type uuid» en toda consulta posterior de una
 * conexión agrupada ya usada. Eso no es fallar cerrado, es fallar a gritos: no filtra,
 * pero convierte cada petición sin tenant en un error 500.
 *
 * Con NULLIF la comparación queda `tenant_id = NULL`, que es NULL y no true, y la política
 * devuelve cero filas. Eso sí es fallar cerrado, y es lo que comprueba la prueba
 * «sigue aislando aunque el guard de aplicación esté desactivado».
 *
 * Requisito operativo: el rol de aplicación NO puede tener BYPASSRLS ni ser superusuario.
 * El arranque de la aplicación lo verifica y se niega a levantar si no se cumple.
 */
return new class extends Migration
{
    /**
     * Tabla => columna que la ata a su tenant.
     *
     * `tenants` es la excepción y por eso está aquí explícitamente: no tiene `tenant_id`
     * porque su propio `id` *es* el tenant. Quedó fuera de la lista original y el efecto
     * era que cualquier tenant podía enumerar a todos los demás —nombre, slug, plan,
     * región, estado del DPA—, es decir, la lista de clientes de la plataforma. No filtra
     * datos de personas, pero es una lectura cruzada entre tenants y CA-01 del SPEC no
     * admite ninguna: «no obtiene ninguna fila del tenant B».
     */
    private array $tables = [
        'organizations' => 'tenant_id',
        'legal_entities' => 'tenant_id',
        'sites' => 'tenant_id',
        'positions' => 'tenant_id',
        'people' => 'tenant_id',
        'identities' => 'tenant_id',
        'relationships' => 'tenant_id',
        'assignments' => 'tenant_id',
        'users' => 'tenant_id',
        'roles' => 'tenant_id',
        'role_assignments' => 'tenant_id',
        'enrollment_snapshots' => 'tenant_id',
        'audit_events' => 'tenant_id',
        'tenants' => 'id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $column) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING ({$column} = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
                WITH CHECK ({$column} = NULLIF(current_setting('app.tenant_id', true), '')::uuid)
            ");
        }

        // Invariante 3 del SPEC: people nunca contiene menores de edad.
        //
        // Trigger y no CHECK. Conviene ser exacto en el motivo, porque el que estaba
        // escrito aquí era falso: PostgreSQL 16 **sí acepta** un CHECK con CURRENT_DATE
        // —comprobado— así que no es que no se pueda. Las razones reales son dos:
        //
        //  1. La documentación de PostgreSQL desaconseja expresiones no inmutables en un
        //     CHECK: se reevalúan al restaurar un respaldo y el resultado depende de
        //     cuándo se restaure. Aquí el predicado solo se vuelve más cierto con el
        //     tiempo, pero apoyarse en eso es apoyarse en una casualidad del dominio.
        //  2. Un CHECK violado devuelve un error genérico de restricción. El trigger
        //     nombra ADR-0003 en el mensaje, y esa es la diferencia entre un incidente
        //     que se entiende en diez segundos y uno que hay que investigar.
        DB::statement("
            CREATE OR REPLACE FUNCTION assert_person_is_adult() RETURNS trigger AS \$\$
            BEGIN
                IF NEW.birth_date IS NOT NULL
                   AND NEW.birth_date > CURRENT_DATE - INTERVAL '18 years' THEN
                    RAISE EXCEPTION
                        'ADR-0003: la plataforma no almacena identidad de menores de edad';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement('
            CREATE TRIGGER people_adults_only
            BEFORE INSERT OR UPDATE ON people
            FOR EACH ROW EXECUTE FUNCTION assert_person_is_adult()
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS people_adults_only ON people');
        DB::statement('DROP FUNCTION IF EXISTS assert_person_is_adult()');

        foreach (array_keys($this->tables) as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
