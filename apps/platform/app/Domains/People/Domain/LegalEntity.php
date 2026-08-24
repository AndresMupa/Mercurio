<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `risk_class` y el número de trabajadores deciden qué estándares mínimos de la
 * Resolución 0312 aplican. Esa decisión NO se escribe como `if` aquí: vive en
 * docs/legal/legal-matrix.md y se consume como dato (regla 3 de CLAUDE.md).
 */
final class LegalEntity extends PlatformModel
{
    protected $fillable = [
        'organization_id', 'name', 'country_code', 'tax_id',
        'ciiu_code', 'risk_class', 'tariff_regime',
    ];

    protected function casts(): array
    {
        return ['risk_class' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }
}
