<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Exceptions;

use App\Domains\People\Domain\RelationshipStatus;
use DomainException;

final class InvalidRelationshipTransition extends DomainException
{
    /** @param  list<RelationshipStatus>  $allowed */
    public static function from(RelationshipStatus $current, RelationshipStatus $target, array $allowed): self
    {
        $permitidos = implode(', ', array_map(fn (RelationshipStatus $s) => $s->value, $allowed));

        return new self(
            "No se puede pasar de «{$current->value}» a «{$target->value}». ".
            "Estados de origen permitidos: {$permitidos}."
        );
    }

    public static function terminal(RelationshipStatus $current): self
    {
        return new self(
            "«{$current->value}» es un estado terminal: la relación ya está cerrada y no admite más transiciones."
        );
    }
}
