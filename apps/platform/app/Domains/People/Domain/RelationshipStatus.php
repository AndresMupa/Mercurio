<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

/**
 * Estados de una relación laboral. Enum y no booleanos: `is_active` + `is_suspended`
 * admite cuatro combinaciones de las que dos no significan nada, y nada impide
 * escribirlas. Un enum solo admite los estados que existen.
 */
enum RelationshipStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    /** `ended` es terminal: de ahí no se sale (SPEC, máquina de estados). */
    public function isTerminal(): bool
    {
        return $this === self::Ended;
    }

    /** Solo una relación vigente aparece en bandejas operativas. */
    public function grantsOperationalAccess(): bool
    {
        return $this === self::Active;
    }
}
