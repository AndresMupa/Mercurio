<?php

declare(strict_types=1);

namespace App\Domains\Shared;

use Illuminate\Support\Str;

/**
 * Identificador de correlación de la petición (AGENTS.md §21).
 *
 * Existe porque la primera versión de las transiciones generaba un uuid nuevo en cada
 * `AuditEvent`. Eso da una columna llamada `correlation_id` que no correlaciona nada:
 * dos eventos de la misma petición salían con identificadores distintos, y el día que
 * haya que reconstruir qué pasó en un incidente, no se podría.
 *
 * Uno por petición y por trabajo de cola, propagado a todo lo que ocurra dentro.
 */
final class CorrelationId
{
    private static ?string $current = null;

    /** Lo fija el middleware al entrar la petición, o un job al arrancar. */
    public static function set(string $id): void
    {
        self::$current = $id;
    }

    /** El de la petición en curso; si no hay, se crea uno y se conserva. */
    public static function current(): string
    {
        return self::$current ??= (string) Str::uuid();
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
