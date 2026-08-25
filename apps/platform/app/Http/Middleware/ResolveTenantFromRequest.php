<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant **antes** de autenticar (paso B5).
 *
 * Tiene que ir delante de todo lo demás porque sin tenant fijado la RLS no deja ni buscar
 * al usuario por correo ni escribir el evento de un intento fallido. Ese era el nudo que
 * B4 dejó anotado: un login que falla ocurre antes de saber a qué tenant pertenece.
 *
 * Si no se puede resolver, **no se lanza ni se responde distinto**: se sigue sin contexto,
 * y lo que necesite tenant fallará por su cuenta. Responder «ese colegio no existe» sería
 * la enumeración que se evita en el resolver.
 */
final class ResolveTenantFromRequest
{
    public function __construct(private readonly TenantResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->resolver->resolveAndBind($request);

        return $next($request);
    }
}
