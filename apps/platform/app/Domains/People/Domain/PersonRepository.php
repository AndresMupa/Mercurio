<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use Illuminate\Support\Collection;

interface PersonRepository
{
    public function find(string $id): ?Person;

    /** @return Collection<int, Person> personas con al menos una relación vigente hoy */
    public function withCurrentRelationship(): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Person;

    public function addIdentity(Person $person, string $documentType, string $documentNumber, string $countryCode = 'CO'): PersonIdentity;
}
