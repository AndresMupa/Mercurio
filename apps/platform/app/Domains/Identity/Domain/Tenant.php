<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain;

use App\Domains\Shared\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Raíz del aislamiento. No usa BelongsToTenant porque no tiene columna `tenant_id`:
 * su propio `id` es la llave, igual que en su política de RLS.
 *
 * Esa asimetría fue justamente el agujero que encontró B1 —la tabla se quedó fuera de
 * la lista de RLS y cualquier cliente podía enumerar a los demás—, así que aquí se
 * declara explícita en vez de resolverse por convención.
 */
final class Tenant extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'slug', 'plan', 'region', 'status', 'dpa_version', 'dpa_accepted_at',
    ];

    protected function casts(): array
    {
        return ['dpa_accepted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = TenantContext::idOrNull();

            $tenantId === null
                ? $query->whereRaw('1 = 0')
                : $query->where($query->getModel()->qualifyColumn('id'), $tenantId);
        });
    }
}
