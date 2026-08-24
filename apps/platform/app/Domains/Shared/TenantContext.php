<?php

namespace App\Domains\Shared;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fija el tenant en la conexión de PostgreSQL para que la RLS lo vea.
 *
 * Se usa set_config(..., is_local = false) y se limpia explícitamente al terminar la
 * petición, porque con conexiones persistentes un SET LOCAL fuera de transacción no
 * cubriría todas las consultas. El riesgo de esta elección es la fuga entre peticiones
 * de un mismo trabajador: por eso clear() es obligatorio en el middleware de terminación
 * y hay una prueba dedicada a esa fuga.
 */
final class TenantContext
{
    private static ?string $tenantId = null;

    public static function set(string $tenantId): void
    {
        DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantId]);
        self::$tenantId = $tenantId;
    }

    public static function clear(): void
    {
        DB::statement("SELECT set_config('app.tenant_id', '', false)");
        self::$tenantId = null;
    }

    /**
     * El tenant actual, o null si no hay ninguno fijado.
     *
     * Existe para el global scope de los modelos, que no puede lanzar: una consulta sin
     * tenant tiene que devolver cero filas —igual que hace la RLS— y no reventar la
     * petición. Cuando el código de dominio necesita un tenant sí o sí, usa id().
     */
    public static function idOrNull(): ?string
    {
        return self::$tenantId;
    }

    public static function id(): string
    {
        return self::$tenantId ?? throw new RuntimeException(
            'No hay tenant fijado en el contexto. Toda operación sobre datos de personas '.
            'exige tenant resuelto. Ver AGENTS.md §16.'
        );
    }

    /**
     * Verificación de arranque: si el rol de la aplicación puede saltarse la RLS,
     * la garantía de aislamiento es ficción. La aplicación no debe levantar.
     */
    public static function assertRoleCannotBypassRls(): void
    {
        $row = DB::selectOne(
            'SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname = current_user'
        );

        if ($row && ($row->rolbypassrls || $row->rolsuper)) {
            throw new RuntimeException(
                'El rol de base de datos de la aplicación puede saltarse la RLS. '.
                'P0 SECURITY: corrige los privilegios antes de continuar.'
            );
        }
    }
}
