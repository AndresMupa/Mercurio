<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Domains\People\Application\RelationshipClosure;
use App\Domains\People\Domain\Relationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Recorrido J5: cerrar una relación, con el impacto delante.
 *
 * El impacto se sirve por su propia ruta porque se **calcula** al abrir el diálogo, con
 * los números de esta persona concreta. Precalcularlo en el listado sería consultar 64
 * impactos para mostrar uno.
 */
final class RelationshipCloseController
{
    public function __construct(private readonly RelationshipClosure $cierre) {}

    public function impacto(Request $request, Relationship $relacion): JsonResponse
    {
        $request->user()->can('close', $relacion) ?: abort(403);

        return response()->json([
            ...$this->cierre->impactoDe($relacion),
            'motivos' => RelationshipClosure::motivos(),
        ]);
    }

    public function store(Request $request, Relationship $relacion): RedirectResponse
    {
        $request->user()->can('close', $relacion) ?: abort(403);

        $datos = $request->validate([
            'motivo' => ['required', Rule::in(array_keys(RelationshipClosure::motivos()))],
            'ultimo_dia' => ['required', 'date', 'after_or_equal:'.$relacion->valid_from->toDateString()],
        ], [], ['ultimo_dia' => 'último día']);

        $this->cierre->cerrar(
            relacion: $relacion,
            ultimoDia: $datos['ultimo_dia'],
            motivo: $datos['motivo'],
            responsable: $request->user(),
        );

        return redirect("/personas/{$relacion->person_id}")
            ->with('aviso', [
                'tono' => 'ok',
                // Se dice cuándo cesa el acceso, no «guardado»: es lo que la persona que
                // acaba de cerrar necesita saber para responder si alguien pregunta.
                'texto' => 'Relación cerrada. El acceso cesa en la primera petición posterior al '
                    .Carbon::parse($datos['ultimo_dia'])->format('d/m/Y').'.',
            ]);
    }
}
