<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\People\Domain\Transitions\RelationshipTransition;
use Illuminate\Support\Collection;

/**
 * Invariante 5 del SPEC hecho forma: **este contrato no tiene borrado**.
 *
 * No es un olvido ni algo que se añada más adelante. Una relación se cierra con
 * `close()`, que aplica la transición terminal y deja `valid_to`. La historia laboral de
 * una persona es lo que hay que poder mostrar ante una inspección; borrarla es perder
 * exactamente lo que el sistema existe para conservar.
 */
interface RelationshipRepository
{
    public function find(string $id): ?Relationship;

    /** @return Collection<int, Relationship> */
    public function forPerson(string $personId): Collection;

    /** @return Collection<int, Relationship> vigentes hoy: estado y fechas a la vez */
    public function currentForPerson(string $personId): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function startPlanned(array $attributes): Relationship;

    /** @param  array<string, mixed>  $context */
    public function transition(Relationship $relationship, RelationshipTransition $transition, array $context = []): Relationship;

    /** Cierre: la única forma de sacar una relación de circulación. */
    public function close(Relationship $relationship, string $validTo, array $context = []): Relationship;
}
