<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain;

use App\Domains\People\Domain\Person;
use App\Domains\Shared\Domain\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Acceso a la plataforma. La autenticación con MFA es el paso B5; aquí solo vive el
 * modelo y su relación con la persona y con los roles con alcance.
 */
final class User extends Authenticatable
{
    use BelongsToTenant;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['person_id', 'email', 'mfa_enabled', 'status'];

    /** `password` y `mfa_secret` son P3: nunca se serializan. */
    protected $hidden = ['password', 'mfa_secret', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
