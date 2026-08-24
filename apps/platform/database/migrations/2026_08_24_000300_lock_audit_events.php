<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La auditoría es append-only. Invariante de base de datos, no convención de código.
 * Invariante 6 del SPEC · CA-06.
 *
 * El rol se toma de la conexión de runtime y no de `env('DB_APP_ROLE')` directamente,
 * por dos razones:
 *
 *  - `env()` devuelve null cuando la configuración está cacheada (`config:cache`), así
 *    que un despliegue normal podría llegar aquí sin rol y —antes— sin blindar la tabla.
 *  - Lo que hay que blindar es el rol con el que la aplicación *se conecta de verdad*.
 *    Leerlo de la misma configuración que abre la conexión hace imposible que ambos
 *    valores se desincronicen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = $this->appRole();

        DB::statement("GRANT SELECT, INSERT ON audit_events TO {$role}");
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON audit_events FROM {$role}");

        // USAGE basta para nextval; sin SELECT sobre la secuencia.
        DB::statement("REVOKE ALL ON SEQUENCE audit_events_id_seq FROM {$role}");
        DB::statement("GRANT USAGE ON SEQUENCE audit_events_id_seq TO {$role}");
    }

    public function down(): void
    {
        $role = $this->appRole();

        DB::statement("GRANT UPDATE, DELETE ON audit_events TO {$role}");
    }

    /**
     * Devuelve el rol de la aplicación ya validado como identificador.
     *
     * Un identificador de PostgreSQL no se puede pasar como parámetro enlazado, así que
     * hay que interpolarlo. Interpolar sin validar el contenido de una variable de
     * configuración dentro de una sentencia que corre con el dueño del esquema —el rol
     * de mayor privilegio del sistema— es una inyección esperando a que alguien edite
     * un `.env`. La lista blanca es lo que cierra esa puerta.
     */
    private function appRole(): string
    {
        $role = config('database.connections.pgsql.username');

        if (! is_string($role) || $role === '') {
            // Falla ruidosamente: sin rol de aplicación separado no hay garantía real.
            throw new RuntimeException(
                'La conexión «pgsql» no declara usuario. La auditoría append-only exige un '.
                'rol de aplicación distinto del dueño del esquema. Ver SECURITY.md.'
            );
        }

        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $role)) {
            throw new RuntimeException(
                "«{$role}» no es un identificador de PostgreSQL sin comillas válido. ".
                'Revisa DB_APP_ROLE antes de continuar.'
            );
        }

        if (! DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role])) {
            throw new RuntimeException(
                "El rol «{$role}» no existe en la base de datos. Se crea en ".
                'docker/postgres/initdb/010-roles.sql.'
            );
        }

        return $role;
    }
};
