<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Shared\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Averigua a qué tenant pertenece una petición, antes de que haya sesión ni usuario.
 *
 * Es el único punto del sistema que consulta `tenants` sin contexto fijado, y lo hace a
 * través de `resolve_tenant_id_by_slug`, que devuelve un uuid y nada más. Ver la migración
 * 2026_08_25_000100 para por qué esa función existe y por qué está tallada así.
 */
final class TenantResolver
{
    /** Resuelve por slug exacto. Devuelve null si no existe o no está activo. */
    public function bySlug(string $slug): ?string
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $row = DB::selectOne('SELECT resolve_tenant_id_by_slug(?) AS id', [$slug]);

        return $row?->id;
    }

    /**
     * El slug de una petición: subdominio, o cabecera explícita cuando el despliegue no
     * usa subdominios —desarrollo local, pruebas, un cliente con dominio propio—.
     */
    public function slugFromRequest(Request $request): ?string
    {
        if ($cabecera = $request->header('X-Tenant')) {
            return $cabecera;
        }

        $host = $request->getHost();
        $partes = explode('.', $host);

        // `colegio.plataforma.co` → `colegio`. Un host sin subdominio no resuelve nada:
        // es preferible no entrar a entrar en el tenant equivocado.
        return count($partes) >= 3 ? $partes[0] : null;
    }

    /**
     * Resuelve y fija el contexto. Devuelve el id, o null si no se pudo resolver.
     *
     * No lanza: una petición a un tenant inexistente no es un error del servidor, y
     * responder distinto según exista o no sería justo la enumeración que se evita.
     */
    public function resolveAndBind(Request $request): ?string
    {
        $slug = $this->slugFromRequest($request);

        if ($slug === null) {
            return null;
        }

        $tenantId = $this->bySlug($slug);

        if ($tenantId !== null) {
            TenantContext::set($tenantId);
        }

        return $tenantId;
    }
}
