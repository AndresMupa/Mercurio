<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La auditoría es append-only. Invariante de base de datos, no convención de código.
 *
 * Define DB_APP_ROLE en el entorno con el rol con el que corre la aplicación
 * (distinto del dueño del esquema, que ejecuta las migraciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = env('DB_APP_ROLE');

        if (! $role) {
            // Falla ruidosamente: sin rol de aplicación separado no hay garantía real.
            throw new RuntimeException(
                'DB_APP_ROLE no está definido. La auditoría append-only exige un rol de '.
                'aplicación distinto del dueño del esquema. Ver SECURITY.md.'
            );
        }

        DB::statement("GRANT SELECT, INSERT ON audit_events TO {$role}");
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON audit_events FROM {$role}");
        DB::statement("REVOKE ALL ON SEQUENCE audit_events_id_seq FROM {$role}");
        DB::statement("GRANT USAGE ON SEQUENCE audit_events_id_seq TO {$role}");
    }

    public function down(): void
    {
        if ($role = env('DB_APP_ROLE')) {
            DB::statement("GRANT UPDATE, DELETE ON audit_events TO {$role}");
        }
    }
};
