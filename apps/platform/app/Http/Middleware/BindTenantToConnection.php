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
