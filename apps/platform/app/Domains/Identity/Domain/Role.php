<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Role extends PlatformModel
{
    protected $fillable = ['key', 'name'];

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
