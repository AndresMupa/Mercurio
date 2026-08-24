<?php

declare(strict_types=1);

namespace App\Domains\People\Infrastructure;

use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\PersonRepository;
use Illuminate\Support\Collection;

final class EloquentPersonRepository implements PersonRepository
{
    public function find(string $id): ?Person
    {
        return Person::find($id);
    }

    public function withCurrentRelationship(): Collection
    {
        return Person::whereHas('relationships', fn ($q) => $q->current())
            ->orderBy('family_names')
            ->get();
    }

    public function create(array $attributes): Person
    {
        // El trigger de base de datos rechaza menores de edad (invariante 3). No se
        // duplica esa comprobación aquí: una regla escrita en dos sitios se corrige
        // en uno solo el día que cambie.
        return Person::create($attributes);
    }

    public function addIdentity(Person $person, string $documentType, string $documentNumber, string $countryCode = 'CO'): PersonIdentity
    {
        return PersonIdentity::create([
            'person_id' => $person->getKey(),
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'country_code' => $countryCode,
        ]);
    }
}
