<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Registro append-only. La inmutabilidad no la sostiene esta clase: la sostienen los
 * privilegios de PostgreSQL, que le retiran UPDATE y DELETE al rol de la aplicación
 * (migración 000300, CA-06). Lo de aquí es que el error salte en PHP con un mensaje
 * que se entiende, en vez de como un fallo de privilegios a tres capas de distancia.
 *
 * B4 amplía el registro a toda operación relevante —lecturas P3, denegaciones, errores—.
 * B2 escribe ya los cambios de estado porque la regla 4 de CLAUDE.md no admite que una
 * operación relevante ocurra sin auditoría, y una transición de relación lo es.
 */
final class AuditEvent extends Model
{
    // El tenant se impone desde el contexto, igual que en el resto del núcleo: un
    // evento de auditoría no puede declarar a qué tenant pertenece.
    use BelongsToTenant;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'audit_events';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'actor_label', 'action', 'resource_type',
        'resource_id', 'purpose', 'data_classification', 'correlation_id',
        'source', 'result', 'context', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new RuntimeException(
                'audit_events es append-only: no se actualiza. Ver CA-06 del SPEC.'
            );
        });

        self::deleting(function (): never {
            throw new RuntimeException(
                'audit_events es append-only: no se borra. Ver CA-06 del SPEC.'
            );
        });
    }
}
