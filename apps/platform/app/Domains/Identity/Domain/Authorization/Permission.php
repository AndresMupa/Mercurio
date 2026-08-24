<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

/** Las cuatro celdas posibles de la matriz, con la misma leyenda del documento. */
enum Permission
{
    /** ✓ permitido */
    case Allow;

    /** ✓ᵖ permitido con `purpose` declarado */
    case AllowWithPurpose;

    /** ✓ˢ solo sobre sí mismo */
    case AllowSelf;

    /** — denegado */
    case Deny;

    public function requiresPurpose(): bool
    {
        return $this === self::AllowWithPurpose;
    }

    public function requiresSelf(): bool
    {
        return $this === self::AllowSelf;
    }
}
