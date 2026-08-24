<?php

declare(strict_types=1);

namespace App\Domains\People\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Organization extends PlatformModel
{
    protected $fillable = ['name'];

    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }
}
