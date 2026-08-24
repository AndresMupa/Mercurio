<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Transitions;

use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * PLANNED → ACTIVE. Efecto declarado en el SPEC: habilita acceso y bandejas.
 *
 * Ese efecto no se ejecuta aquí. El acceso se resuelve por petición a partir del estado
 * (invariante 8), así que activar la relación *es* habilitar el acceso: no hay nada que
 * propagar, y no haberlo propagado a mano es justamente lo que evita que se quede a medias.
 */
final class ActivateRelationship extends RelationshipTransition
{
    public function to(): RelationshipStatus
    {
        return RelationshipStatus::Active;
    }

    public function allowedFrom(): array
    {
        return [RelationshipStatus::Planned];
    }

    public function auditAction(): string
    {
        return 'relationship.activated';
    }

    protected function assertPreconditions(Relationship $relationship, array $context): void
    {
        if ($relationship->valid_from > Carbon::today()) {
            throw new DomainException(
                "La relación empieza el {$relationship->valid_from->toDateString()}: ".
                'no se puede activar antes de esa fecha.'
            );
        }

        // El SPEC exige persona con identidad. Sin documento no hay a quién vincular
        // ante una inspección, y el C600 exige el documento para reportar.
        if ($relationship->person()->withoutIdentity()->exists()) {
            throw new DomainException(
                'La persona no tiene documento de identidad registrado: '.
                'una relación laboral no se activa sin él.'
            );
        }
    }
}
