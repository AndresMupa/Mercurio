<?php

declare(strict_types=1);

namespace App\Domains\People\Interfaces\Policies;

use App\Domains\Identity\Application\Authorizer;
use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\Authorization\Purpose;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Person;
use App\Domains\Shared\Domain\DataClassification;

/**
 * La Policy no decide: construye el contexto y pregunta al Authorizer.
 *
 * Es deliberado que aquí no haya ni un `if` sobre roles. Si la decisión se repartiera
 * entre Policies, cada una tendría su propia idea de qué significa «vigente» o «alcance»,
 * y la matriz dejaría de ser la fuente de verdad el día que alguien copie una y la
 * modifique un poco.
 */
final readonly class PersonPolicy
{
    public function __construct(private Authorizer $authorizer) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'people',
            action: 'listar',
            dataClassification: DataClassification::P2,
        ));
    }

    public function view(User $user, Person $person): bool
    {
        return $this->authorizer->allows($this->contextFor($user, $person, 'listar', DataClassification::P2));
    }

    public function create(User $user): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'people',
            action: 'crear',
            dataClassification: DataClassification::P2,
        ));
    }

    public function update(User $user, Person $person): bool
    {
        return $this->authorizer->allows($this->contextFor($user, $person, 'editar', DataClassification::P2));
    }

    /**
     * Lectura de un atributo concreto. El recurso de la matriz es la **columna**
     * (`people.birth_date`), no la tabla: la matriz distingue listar una persona de leer
     * su fecha de nacimiento, y la Policy tiene que distinguirlo igual.
     */
    public function readAttribute(User $user, Person $person, string $attribute, ?Purpose $purpose = null): bool
    {
        return $this->authorizer->allows(new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: "people.{$attribute}",
            action: 'leer',
            resourceTenantId: (string) $person->tenant_id,
            resourcePersonId: (string) $person->getKey(),
            purpose: $purpose,
            dataClassification: DataClassification::of('people', $attribute) ?? DataClassification::P3,
        ));
    }

    private function contextFor(User $user, Person $person, string $action, string $classification): AuthorizationContext
    {
        return new AuthorizationContext(
            user: $user,
            tenantId: (string) $user->tenant_id,
            resource: 'people',
            action: $action,
            resourceTenantId: (string) $person->tenant_id,
            resourcePersonId: (string) $person->getKey(),
            dataClassification: $classification,
        );
    }
}
