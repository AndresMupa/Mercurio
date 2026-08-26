<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Identity\Domain\Authorization\RoleKey;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use Illuminate\Support\Carbon;

/**
 * Los roles que un usuario tiene **vigentes hoy**.
 *
 * Se extrajo de `MfaRequirement` en B8, cuando la navegación necesitó la misma consulta:
 * un servicio que se llama «requisito de MFA» no es sitio del que sacar roles para pintar
 * un menú, y duplicar la consulta habría permitido que las dos se desincronizaran —que es
 * exactamente cómo se acaba con un menú que enseña lo que el autorizador deniega—.
 *
 * CA-08 vive aquí: una asignación vencida no aparece, aunque el rol siga asignado.
 */
final class EffectiveRoles
{
    /** @return list<RoleKey> */
    public function of(User $user, ?Carbon $on = null): array
    {
        $roles = [];

        $assignments = RoleAssignment::with('role')
            ->where('user_id', $user->getKey())
            ->effective($on ?? Carbon::today())
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
