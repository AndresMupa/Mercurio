<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Transitions;

use App\Domains\People\Domain\RelationshipStatus;

/**
 * SUSPENDED → ACTIVE.
 *
 * Está en el diagrama del SPEC (`PLANNED → ACTIVE → SUSPENDED → ACTIVE`) pero **no en su
 * tabla de transiciones**, así que el SPEC no declara actor, precondiciones ni evento de
 * auditoría para ella. Aquí se asumen los mismos actores que el resto y un evento propio,
 * distinto de `relationship.activated`: reanudar no es lo mismo que activar por primera
 * vez, y la auditoría tiene que poder distinguirlos. Anotado en STATE.md para que el SPEC
 * lo confirme o lo corrija.
 */
final class ReactivateRelationship extends RelationshipTransition
{
    public function to(): RelationshipStatus
    {
        return RelationshipStatus::Active;
    }

    public function allowedFrom(): array
    {
        return [RelationshipStatus::Suspended];
    }

    public function auditAction(): string
    {
        return 'relationship.reactivated';
    }
}
