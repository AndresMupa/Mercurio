<?php

namespace App\Http\Middleware;

use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primera capa del aislamiento. La segunda es la RLS; la tercera, las pruebas.
 * Si esta capa falla, la RLS todavía protege. Esa es la razón de que existan las tres.
 */
final class BindTenantToConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        // Un identificador por petición, propagado a toda la auditoría que ocurra
        // dentro. Se acepta el de una cabecera para poder seguir una operación que
        // atraviesa varios servicios.
        CorrelationId::set($request->header('X-Correlation-Id') ?: (string) Str::uuid());

        $tenantId = $request->user()?->tenant_id;

        if ($tenantId) {
            // Desde B5 el tenant ya viene resuelto de la petición por
            // ResolveTenantFromRequest. Si el usuario autenticado pertenece a otro, algo
            // va muy mal —sesión reutilizada entre subdominios, o algo peor— y lo seguro
            // es no servir la petición en vez de decidir cuál de los dos gana.
            $resuelto = TenantContext::idOrNull();

            abort_if(
                $resuelto !== null && $resuelto !== $tenantId,
                403,
                'La sesión no corresponde a este tenant.'
            );

            TenantContext::set($tenantId);
        }

        return $next($request);
    }

    /** Obligatorio: evita que el tenant sobreviva a la petición en conexiones agrupadas. */
    public function terminate(Request $request, Response $response): void
    {
        TenantContext::clear();
        CorrelationId::clear();
    }
}
