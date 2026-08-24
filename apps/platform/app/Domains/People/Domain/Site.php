<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** El `dane_code` es la llave del C600 (Slice 1). */
final class Site extends PlatformModel
{
    protected $fillable = [
        'legal_entity_id', 'name', 'dane_code', 'address',
        'area_type', 'department_code', 'municipality_code', 'is_work_center',
    ];

    protected function casts(): array
    {
        return ['is_work_center' => 'boolean'];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function enrollmentSnapshots(): HasMany
    {
        return $this->hasMany(EnrollmentSnapshot::class);
    }
}
