<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Relationship;
use App\Domains\Shared\Domain\DataClassification;

/**
 * Todo lo que hace falta para decidir, en un solo objeto (ARCHITECTURE.md).
 *
 * Que sea un objeto y no una lista de argumentos sueltos tiene una consecuencia práctica:
 * añadir una dimensión a la decisión —mañana, el consentimiento del titular— no obliga a
 * cambiar la firma de todas las Policies. Y que sea readonly evita que una capa lo
 * modifique a mitad de camino para «arreglar» una denegación.
 */
final readonly class AuthorizationContext
{
    public function __construct(
        public User $user,
        public string $tenantId,
        public string $resource,
        public string $action,
        public ?string $resourceTenantId = null,
        public ?string $resourceLegalEntityId = null,
        public ?string $resourceSiteId = null,
        public ?string $resourcePersonId = null,
        public ?Purpose $purpose = null,
        public ?string $dataClassification = null,
        public ?Relationship $relationship = null,
    ) {}

    /** El recurso trata de la propia persona del usuario que pregunta. */
    public function isAboutSelf(): bool
    {
        return $this->resourcePersonId !== null
            && $this->user->person_id !== null
            && $this->resourcePersonId === $this->user->person_id;
    }

    public function touchesSensitiveData(): bool
    {
        return in_array(
            $this->dataClassification,
            [DataClassification::P3, DataClassification::P4],
            strict: true,
        );
    }
}
