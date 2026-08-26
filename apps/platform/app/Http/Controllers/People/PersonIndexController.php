<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Domains\People\Application\PersonDirectory;
use App\Domains\People\Domain\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El listado de la planta.
 *
 * La autorización pasa por la Policy, que llama al `Authorizer`: quien no tiene la celda
 * `people · listar` no llega aquí, y el intento queda auditado.
 */
final class PersonIndexController
{
    public function __construct(private readonly PersonDirectory $directorio) {}

    public function __invoke(Request $request): Response
    {
        $request->user()->can('viewAny', Person::class) ?: abort(403);

        $resultado = $this->directorio->listar([
            'busqueda' => $request->query('q'),
            'estado' => $request->query('estado'),
            'orden' => $request->query('orden'),
            'sentido' => $request->query('sentido'),
            'pagina' => (int) $request->query('pagina', 1),
        ]);

        return Inertia::render('Personas/Index', [
            ...$resultado,
            'recuento' => $this->directorio->recuento(),
            'filtros' => array_filter([
                'q' => $request->query('q'),
                'estado' => $request->query('estado'),
            ]),
            'puedeCrear' => $request->user()->can('create', Person::class),
        ]);
    }
}
