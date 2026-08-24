<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\People\Domain\Exceptions\RelationshipCannotBeDeleted;
use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El corazón del modelo: el vínculo entre una persona y una entidad jurídica.
 *
 * El estado no se cambia asignando `status` a mano. Se cambia aplicando una de las
 * clases de `Domain\Transitions`, que son las que conocen precondiciones, efectos y
 * evento de auditoría. Escribir `$relationship->status = ...` compila, pero se salta
 * la máquina; por eso las transiciones son el único camino documentado y el que usan
 * los repositorios.
 */
final class Relationship extends PlatformModel
{
    protected $fillable = [
        'person_id', 'legal_entity_id', 'site_id', 'type',
        'employment_type', 'valid_from', 'valid_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'status' => RelationshipStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Invariante 5 · SPEC, máquina de estados: «cualquiera → borrado: prohibido».
        // La política de repositorio no basta si el modelo sigue admitiendo delete():
        // cualquier código nuevo podría llamarlo sin pasar por el repositorio.
        self::deleting(function (Relationship $relationship): never {
            throw RelationshipCannotBeDeleted::cierrala((string) $relationship->getKey());
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Vigente es una cuestión de estado y de fechas a la vez: una relación `active`
     * cuyo `valid_to` ya pasó no está vigente aunque nadie haya corrido el cierre.
     */
    public function isCurrentOn(?Carbon $date = null): bool
    {
        $date = ($date ?? Carbon::today())->toImmutable()->startOfDay();

        return $this->status === RelationshipStatus::Active
            && $this->valid_from <= $date
            && ($this->valid_to === null || $this->valid_to >= $date);
    }

    public function scopeCurrent(Builder $query, ?Carbon $date = null): Builder
    {
        $date = ($date ?? Carbon::today())->toDateString();

        return $query->where('status', RelationshipStatus::Active->value)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }
}
