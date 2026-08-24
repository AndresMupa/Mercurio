<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** `personnel_type` sigue la taxonomía del C600 (core-entities.md). */
final class Position extends PlatformModel
{
    protected $fillable = ['legal_entity_id', 'title', 'personnel_type', 'risk_level'];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
