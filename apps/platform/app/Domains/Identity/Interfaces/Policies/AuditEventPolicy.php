<?php

declare(strict_types=1);

namespace App\Domains\Identity\Interfaces\Policies;

use App\Domains\Identity\Application\Authorizer;
use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\User;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\Domain\DataClassification;

/**
 * Invariante 4 de la matriz: nadie, incluido `owner`, modifica ni borra auditoría.
 *
 * Aquí se devuelve `false` sin consultar nada. No es redundancia con la matriz ni con los
 * privilegios de PostgreSQL: son tres capas del mismo invariante, y la de base de datos es
 * la única que no se puede desactivar editando código.
 */
final readonly class AuditEventPolicy
{
    public function __construct(private Authorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'audit_events',
            action: 'leer',
            dataClassification: DataClassification::P2,
        ));
    }

    public function update(User $user, AuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, AuditEvent $event): bool
    {
        return false;
    }
}
