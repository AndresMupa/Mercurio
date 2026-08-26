<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Domains\Identity\Domain\Authorization\Purpose;
use App\Domains\People\Application\SensitiveFieldReader;
use App\Domains\People\Domain\Person;
use App\Domains\Shared\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La única ruta por la que sale un dato P3 completo, y siempre con propósito declarado.
 *
 * CA-04 y CA-05 hechos endpoint: si el propósito falta o no basta, se deniega y queda
 * `result = denied`; si se permite, queda `result = allowed` con propósito, actor,
 * clasificación y correlación. Las dos cosas las hace el `Authorizer` dentro del lector.
 */
final class SensitiveFieldController
{
    public function __construct(private readonly SensitiveFieldReader $lector) {}

    public function __invoke(Request $request, Person $persona): JsonResponse
    {
        $datos = $request->validate([
            'campo' => ['required', Rule::in(SensitiveFieldReader::camposConocidos())],
            'proposito' => ['required', Rule::enum(Purpose::class)],
        ]);

        $valor = $this->lector->leer(
            usuario: $request->user(),
            tenantId: (string) TenantContext::id(),
            persona: $persona,
            campo: $datos['campo'],
            proposito: Purpose::from($datos['proposito']),
        );

        if ($valor === null) {
            // Un solo mensaje para todas las denegaciones. El motivo real está en la
            // auditoría; decírselo aquí a quien prueba le enseña dónde está el borde.
            return response()->json([
                'mensaje' => 'No tienes permiso para ver este dato con ese propósito.',
            ], 403);
        }

        return response()->json(['valor' => $valor]);
    }
}
