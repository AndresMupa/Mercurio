<?php

declare(strict_types=1);

namespace App\Domains\People\Infrastructure;

use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\People\Domain\Transitions\EndRelationship;
use App\Domains\People\Domain\Transitions\RelationshipTransition;
use Illuminate\Support\Collection;

/**
 * El aislamiento por tenant no se escribe aquí: lo pone el global scope del modelo y,
 * debajo, la RLS. Un repositorio que filtrara por tenant a mano invitaría a que alguien
 * escribiera el siguiente sin acordarse.
 */
final class EloquentRelationshipRepository implements RelationshipRepository
{
    public function find(string $id): ?Relationship
    {
        return Relationship::find($id);
    }

    public function forPerson(string $personId): Collection
    {
        return Relationship::where('person_id', $personId)->orderByDesc('valid_from')->get();
    }

    public function currentForPerson(string $personId): Collection
    {
        return Relationship::where('person_id', $personId)->current()->get();
    }

    public function startPlanned(array $attributes): Relationship
    {
        // Nace planificada, no activa: activarla es una transición con precondiciones
        // —fecha alcanzada y persona con documento— y con su evento de auditoría.
        return Relationship::create([
            ...$attributes,
            'status' => RelationshipStatus::Planned,
        ]);
    }

    public function transition(Relationship $relationship, RelationshipTransition $transition, array $context = []): Relationship
    {
        return $transition->apply($relationship, $context);
    }

    public function close(Relationship $relationship, string $validTo, array $context = []): Relationship
    {
        return (new EndRelationship)->apply($relationship, [...$context, 'valid_to' => $validTo]);
    }
}
