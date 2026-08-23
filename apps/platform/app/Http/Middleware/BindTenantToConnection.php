<?php

namespace App\Http\Middleware;

use App\Domains\Shared\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primera capa del aislamiento. La segunda es la RLS; la tercera, las pruebas.
 * Si esta capa falla, la RLS todavía protege. Esa es la razón de que existan las tres.
 */
final class BindTenantToConnection
{
    public function handle(Request $request, Closure $next): Response
    {
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
    }
}
