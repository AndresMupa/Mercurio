<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Transitions;

use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * → ENDED, terminal. El SPEC exige `valid_to` informado.
 *
 * Se admite también desde SUSPENDED, que el SPEC no contempla en su tabla: si no se
 * admitiera, una relación suspendida no podría cerrarse nunca y la única salida sería
 * reactivarla para poder terminarla, que es peor. Anotado en STATE.md.
 *
 * Este es el cierre del que habla el invariante 5: la relación no desaparece, queda con
 * fecha de fin y su historia intacta.
 */
final class EndRelationship extends RelationshipTransition
{
    public function to(): RelationshipStatus
    {
        return RelationshipStatus::Ended;
    }

    public function allowedFrom(): array
    {
        return [RelationshipStatus::Active, RelationshipStatus::Suspended];
    }

    public function auditAction(): string
    {
        return 'relationship.ended';
    }

    protected function assertPreconditions(Relationship $relationship, array $context): void
    {
        $validTo = $context['valid_to'] ?? $relationship->valid_to;

        if ($validTo === null) {
            throw new DomainException(
                'Cerrar una relación exige fecha de terminación: sin ella no se puede '.
                'calcular liquidación, vigencia de accesos ni obligaciones pendientes.'
            );
        }

        if (Carbon::parse($validTo)->toImmutable()->startOfDay() < $relationship->valid_from) {
            throw new DomainException(
                'La fecha de terminación es anterior a la de inicio de la relación.'
            );
        }
    }

    protected function applyEffects(Relationship $relationship, array $context): void
    {
        if (isset($context['valid_to'])) {
            $relationship->valid_to = Carbon::parse($context['valid_to'])->toImmutable()->startOfDay();
        }
    }

    protected function auditContext(Relationship $relationship, array $context): array
    {
        return [
            ...parent::auditContext($relationship, $context),
            'valid_to' => $relationship->valid_to?->toDateString(),
        ];
    }
}
