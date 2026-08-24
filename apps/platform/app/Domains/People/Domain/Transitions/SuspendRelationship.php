<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Transitions;

use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use DomainException;

/** ACTIVE → SUSPENDED. El SPEC exige motivo. */
final class SuspendRelationship extends RelationshipTransition
{
    public function to(): RelationshipStatus
    {
        return RelationshipStatus::Suspended;
    }

    public function allowedFrom(): array
    {
        return [RelationshipStatus::Active];
    }

    public function auditAction(): string
    {
        return 'relationship.suspended';
    }

    protected function assertPreconditions(Relationship $relationship, array $context): void
    {
        if (trim((string) ($context['reason'] ?? '')) === '') {
            throw new DomainException(
                'Suspender una relación exige motivo: queda en la auditoría y es lo que '.
                'sostiene la decisión ante una reclamación.'
            );
        }
    }

    protected function auditContext(Relationship $relationship, array $context): array
    {
        return [
            ...parent::auditContext($relationship, $context),
            'reason' => $context['reason'],
        ];
    }
}
