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
 * current_setting('app.tenant_id', true) devuelve NULL si no está fijado, y
 * `tenant_id = NULL` es NULL, no true. La política falla cerrada por diseño.
 *
 * Requisito operativo: el rol de aplicación NO puede tener BYPASSRLS ni ser superusuario.
 * El arranque de la aplicación lo verifica y se niega a levantar si no se cumple.
 */
return new class extends Migration
{
    private array $tables = [
        'organizations', 'legal_entities', 'sites', 'positions', 'people', 'identities',
        'relationships', 'assignments', 'users', 'roles', 'role_assignments',
        'enrollment_snapshots', 'audit_events',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            ");
        }

        // Invariante 3 del SPEC: people nunca contiene menores de edad.
        // Se implementa como trigger y no como CHECK porque CURRENT_DATE no es inmutable
        // y un CHECK con función no inmutable rompe restauraciones de respaldo.
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

        DB::statement("
            CREATE TRIGGER people_adults_only
            BEFORE INSERT OR UPDATE ON people
            FOR EACH ROW EXECUTE FUNCTION assert_person_is_adult()
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS people_adults_only ON people');
        DB::statement('DROP FUNCTION IF EXISTS assert_person_is_adult()');

        foreach ($this->tables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
