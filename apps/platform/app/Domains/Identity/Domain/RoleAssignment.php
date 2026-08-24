<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain;

use App\Domains\Shared\Domain\PlatformModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un rol con alcance y con vigencia. El rol nunca decide solo: es un input del
 * AuthorizationContext (B3) junto con alcance, relación, clasificación y propósito.
 */
final class RoleAssignment extends PlatformModel
{
    protected $fillable = ['user_id', 'role_id', 'scope_type', 'scope_id', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return ['valid_from' => 'immutable_date', 'valid_to' => 'immutable_date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** CA-08: un rol vencido no otorga permisos, aunque siga asignado. */
    public function isEffectiveOn(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::today();

        return $this->valid_from <= $date
            && ($this->valid_to === null || $this->valid_to >= $date);
    }

    public function scopeEffective(Builder $query, ?Carbon $date = null): Builder
    {
        $date = ($date ?? Carbon::today())->toDateString();

        return $query->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }
}
