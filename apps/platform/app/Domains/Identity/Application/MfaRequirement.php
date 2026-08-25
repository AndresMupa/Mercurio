<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Identity\Domain\Authorization\RoleKey;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\Shared\Domain\DataClassification;
use Illuminate\Support\Carbon;

/**
 * CA-09: el login exige MFA para todo rol con techo P3.
 *
 * Quién necesita segundo factor no se decide con una lista de roles escrita aparte —que
 * envejece mal— sino preguntando al propio `RoleKey` por su techo de clasificación. Cuando
 * mañana se añada un rol con techo P3, quedará cubierto sin que nadie tenga que acordarse.
 *
 * Solo cuentan las asignaciones **vigentes hoy**: un rol vencido no otorga permisos
 * (CA-08), así que tampoco puede ser el motivo por el que se exige un segundo factor.
 */
final class MfaRequirement
{
    public function isRequiredFor(User $user): bool
    {
        foreach ($this->effectiveRoleKeys($user) as $role) {
            if ($role->classificationCeiling() === DataClassification::P3) {
                return true;
            }
        }

        return false;
    }

    /** @return list<RoleKey> */
    public function effectiveRoleKeys(User $user): array
    {
        $roles = [];

        $assignments = RoleAssignment::with('role')
            ->where('user_id', $user->getKey())
            ->effective(Carbon::today())
            ->get();

        foreach ($assignments as $assignment) {
            $role = RoleKey::tryFrom((string) $assignment->role?->key);

            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
