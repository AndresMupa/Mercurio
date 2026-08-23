<?php

declare(strict_types=1);

namespace App\Domains\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Comprobaciones de la fundación (PLAN.md, paso A1).
 *
 * Una sola fuente de verdad para las tres superficies que las consultan: la pantalla de
 * estado, el comando `platform:check-foundation` y las pruebas. Si vivieran duplicadas,
 * la pantalla podría decir que todo está bien mientras el comando dice lo contrario.
 *
 * Lo que se comprueba aquí no es cosmética: si el rol de la aplicación pudiera saltarse
 * la RLS o fuera dueño del esquema, el aislamiento multi-tenant y la auditoría
 * append-only dejarían de ser garantías y pasarían a ser convenciones.
 */
final class FoundationCheck
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const CRITICAL = 'critical';

    /** @return list<array{nombre: string, estado: string, detalle: string}> */
    public function all(): array
    {
        return [
            $this->runtimeConnection(),
            $this->appRoleCannotBypassRls(),
            $this->rolesAreSeparate(),
            $this->postgresVersion(),
            $this->redis(),
        ];
    }

    public function hasFailures(): bool
    {
        foreach ($this->all() as $check) {
            if ($check['estado'] === self::CRITICAL) {
                return true;
            }
        }

        return false;
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function runtimeConnection(): array
    {
        return $this->attempt('Conexión de runtime', function (): array {
            $row = DB::selectOne('SELECT current_user AS role, current_database() AS db');

            return [self::OK, "Conectado a «{$row->db}» como «{$row->role}»."];
        });
    }

    /**
     * El invariante del paso A1. Un rol con BYPASSRLS o superusuario ignora toda
     * política de RLS: el aislamiento entre tenants sería ficción.
     *
     * @return array{nombre: string, estado: string, detalle: string}
     */
    private function appRoleCannotBypassRls(): array
    {
        return $this->attempt('Rol de aplicación sin BYPASSRLS ni superusuario', function (): array {
            TenantContext::assertRoleCannotBypassRls();

            return [self::OK, 'El rol de runtime está sujeto a las políticas de RLS.'];
        });
    }

    /**
     * Si la aplicación fuera dueña de sus tablas, podría revocar sus propias
     * restricciones: `ALTER TABLE ... NO FORCE ROW LEVEL SECURITY` y el `REVOKE`
     * de la migración 000300 dejarían de significar nada.
     *
     * @return array{nombre: string, estado: string, detalle: string}
     */
    private function rolesAreSeparate(): array
    {
        return $this->attempt('Rol de aplicación distinto del dueño del esquema', function (): array {
            $app = (string) DB::selectOne('SELECT current_user AS role')->role;
            $owner = (string) DB::connection('pgsql_owner')->selectOne('SELECT current_user AS role')->role;

            if ($app === $owner) {
                return [self::CRITICAL, "Ambas conexiones usan «{$app}». Las migraciones y el runtime deben correr con roles distintos."];
            }

            $isMember = DB::selectOne(
                'SELECT pg_has_role(current_user, ?, ?) AS member',
                [$owner, 'USAGE']
            )->member;

            if ($isMember) {
                return [self::CRITICAL, "«{$app}» hereda los privilegios de «{$owner}»: la separación es nominal."];
            }

            return [self::OK, "Runtime «{$app}», esquema «{$owner}», sin herencia entre ellos."];
        });
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function postgresVersion(): array
    {
        return $this->attempt('PostgreSQL 16 o superior', function (): array {
            $version = (int) DB::selectOne('SHOW server_version_num')->server_version_num;
            $legible = sprintf('%d.%d', intdiv($version, 10000), $version % 10000);

            return $version >= 160000
                ? [self::OK, "Servidor {$legible}."]
                : [self::WARN, "Servidor {$legible}; ADR 0001 fija PostgreSQL 16."];
        });
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function redis(): array
    {
        return $this->attempt('Redis disponible', function (): array {
            Redis::connection()->ping();

            return [self::OK, 'Responde. Sostiene sesión, caché y colas.'];
        });
    }

    /**
     * @param  callable(): array{0: string, 1: string}  $probe
     * @return array{nombre: string, estado: string, detalle: string}
     */
    private function attempt(string $nombre, callable $probe): array
    {
        try {
            [$estado, $detalle] = $probe();
        } catch (Throwable $e) {
            // No se silencia: el motivo real viaja al detalle para que sea accionable.
            return ['nombre' => $nombre, 'estado' => self::CRITICAL, 'detalle' => $e->getMessage()];
        }

        return ['nombre' => $nombre, 'estado' => $estado, 'detalle' => $detalle];
    }
}
