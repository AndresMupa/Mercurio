<?php

declare(strict_types=1);

namespace App\Domains\People\Interfaces\Policies;

use App\Domains\Identity\Application\Authorizer;
use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Relationship;
use App\Domains\Shared\Domain\DataClassification;

final readonly class RelationshipPolicy
{
    public function __construct(private Authorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'relationships',
            action: 'listar',
            dataClassification: DataClassification::P2,
        ));
    }

    public function create(User $user): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'relationships',
            action: 'crear',
            dataClassification: DataClassification::P2,
        ));
    }

    public function close(User $user, Relationship $relationship): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'relationships',
            action: 'cerrar',
            resourceTenantId: (string) $relationship->tenant_id,
            resourceLegalEntityId: (string) $relationship->legal_entity_id,
            resourceSiteId: $relationship->site_id === null ? null : (string) $relationship->site_id,
            resourcePersonId: (string) $relationship->person_id,
            dataClassification: DataClassification::P2,
            relationship: $relationship,
        ));
    }

    /** El SPEC lo prohíbe como transición; la Policy lo dice también, por si acaso. */
    public function delete(User $user, Relationship $relationship): bool
    {
        return false;
    }
}
