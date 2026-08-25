<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La aplicación no puede borrar un tenant. Decisión humana del 25 de agosto de 2026.
 *
 * El hallazgo es de la auditoría de B1 y estuvo abierto a propósito hasta que alguien
 * decidiera: los doce `FOREIGN KEY` que apuntan a `tenants` son `ON DELETE CASCADE`, y
 * `platform_app` tenía `DELETE` sobre la tabla por los privilegios por defecto del script
 * de roles. Un solo `DELETE FROM tenants` desde código de producto destruía organización,
 * sedes, personas, identidades, relaciones, asignaciones, usuarios, roles y matrículas de
 * ese tenant, sin vuelta atrás.
 *
 * Se revoca porque nadie lo usa, porque el ciclo de vida ya se expresa con
 * `tenants.status`, y porque convertir una operación irreversible en **imposible desde la
 * aplicación** cuesta esta migración y evita el peor incidente concebible en el producto.
 *
 * Esto **no** cierra el derecho de supresión del titular. Si algún día hay que suprimir de
 * verdad, será un procedimiento explícito —respaldo previo, responsable identificado,
 * evento de auditoría— ejecutado con el rol dueño, no un privilegio permanente de la
 * aplicación web. La diferencia entre las dos cosas es justamente lo que se compra aquí.
 *
 * `TRUNCATE` va en la misma revocación: vacía la tabla sin disparar `ON DELETE CASCADE`
 * ni las políticas de RLS, así que dejarlo sería dejar la misma puerta con otro nombre.
 *
 * La auditoría sobrevive de todos modos: `audit_events` no tiene `FOREIGN KEY` a
 * `tenants`, que es lo que sostiene la retención de 5 años de `data-classification.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = $this->appRole();

        DB::statement("REVOKE DELETE, TRUNCATE ON tenants FROM {$role}");
    }

    public function down(): void
    {
        $role = $this->appRole();

        DB::statement("GRANT DELETE, TRUNCATE ON tenants TO {$role}");
    }

    /**
     * Mismo criterio que `2026_08_24_000300_lock_audit_events`: el rol sale de la conexión
     * de runtime —no de `env()`, que devuelve null con la configuración cacheada— y se
     * valida antes de interpolarlo, porque un identificador de PostgreSQL no admite
     * parámetro enlazado y esta sentencia corre con el dueño del esquema.
     */
    private function appRole(): string
    {
        $role = config('database.connections.pgsql.username');

        if (! is_string($role) || $role === '') {
            throw new RuntimeException(
                'La conexión «pgsql» no declara usuario. Revocar el borrado de tenants exige '.
                'un rol de aplicación distinto del dueño del esquema.'
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
