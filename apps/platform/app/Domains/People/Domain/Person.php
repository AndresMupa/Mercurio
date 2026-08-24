<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona adulta con relación con la organización. Nunca un estudiante (ADR 0003).
 *
 * `birth_date` y `sex` son P3: existen porque el Módulo III del C600 los exige, y esa
 * base legal se declara como `purpose` en cada lectura (B3). El modelo no los oculta
 * —el repositorio los necesita— pero tampoco los expone: quien decide es la Policy.
 */
final class Person extends PlatformModel
{
    protected $fillable = [
        'given_names', 'family_names', 'birth_date', 'sex',
        'education_level', 'teaching_statute', 'teaching_grade', 'status',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'immutable_date'];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PersonIdentity::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }

    /**
     * Invariante 4: una persona puede tener varias relaciones vigentes a la vez.
     * Un docente puede ser empleado del colegio y contratista de la fundación.
     */
    public function activeRelationships(): HasMany
    {
        return $this->relationships()->where('status', RelationshipStatus::Active->value);
    }

    public function scopeWithoutIdentity(Builder $query): Builder
    {
        return $query->whereDoesntHave('identities');
    }
}
