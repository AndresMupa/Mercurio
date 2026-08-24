<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use App\Domains\Shared\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Primera capa del aislamiento en el modelo (ARCHITECTURE.md): global scope por tenant
 * y relleno automático de `tenant_id` al crear.
 *
 * No es la garantía —esa es la RLS, que sigue ahí aunque este scope falle o alguien lo
 * quite con withoutGlobalScopes()—. Es lo que hace que las consultas del día a día
 * salgan bien escritas sin que nadie tenga que acordarse, y lo que convierte un olvido
 * en cero filas en vez de en un error de PostgreSQL.
 *
 * Sin tenant fijado el scope devuelve cero filas en lugar de lanzar. Es exactamente lo
 * que hace la RLS en la misma situación, y las dos capas tienen que comportarse igual:
 * si una lanzara y la otra filtrara, el comportamiento dependería de cuál actuara
 * primero. El código de dominio que necesita un tenant sí o sí llama a TenantContext::id().
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = TenantContext::idOrNull();
            $columna = $query->getModel()->getTenantColumn();

            if ($tenantId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->getModel()->qualifyColumn($columna), $tenantId);
        });

        static::creating(function (Model $model): void {
            $columna = $model->getTenantColumn();

            // Nunca se acepta un tenant_id venido de fuera: se impone el del contexto.
            // Aceptarlo sería dejar que el cuerpo de una petición decida a qué tenant
            // pertenece lo que escribe.
            $model->setAttribute($columna, TenantContext::id());
        });
    }

    /** `tenants` se aísla por su propio id; el resto, por `tenant_id`. */
    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }
}
