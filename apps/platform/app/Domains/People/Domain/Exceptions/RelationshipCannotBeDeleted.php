<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Exceptions;

use DomainException;

/**
 * Invariante 5 del SPEC: una relación no se borra, se cierra con `valid_to`.
 *
 * Borrarla destruiría la historia laboral de una persona, que es justo lo que hace falta
 * conservar para responder ante una inspección. El SPEC lo dice explícito en la máquina
 * de estados: «cualquiera → borrado: prohibido».
 */
final class RelationshipCannotBeDeleted extends DomainException
{
    public static function cierrala(string $id): self
    {
        return new self(
            "La relación {$id} no se puede borrar. Ciérrala con EndRelationship: ".
            'la historia laboral se conserva (invariante 5 del SPEC).'
        );
    }
}
