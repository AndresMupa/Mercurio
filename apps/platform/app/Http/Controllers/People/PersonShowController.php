<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Domains\People\Application\PersonFile;
use App\Domains\People\Application\SensitiveFieldReader;
use App\Domains\People\Domain\Person;
use App\Domains\Shared\Domain\AuditEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La ficha. Los tres campos P3 salen enmascarados; verlos entero pasa por el diálogo de
 * propósito, que es otra ruta y deja evento.
 */
final class PersonShowController
{
    public function __construct(
        private readonly PersonFile $ficha,
        private readonly SensitiveFieldReader $sensibles,
    ) {}

    public function __invoke(Request $request, Person $persona): Response
    {
        $request->user()->can('view', $persona) ?: abort(403);

        return Inertia::render('Personas/Ficha', [
            'persona' => $this->ficha->de($persona),
            // El rastro solo lo ve quien puede leer auditoría. A quien no, no se le
            // enseña una sección vacía: se le omite, que es distinto.
            'rastro' => $request->user()->can('viewAny', AuditEvent::class)
                ? $this->ficha->rastroDe($persona)
                : null,
            'propositos' => $this->sensibles->propositosPara('ficha'),
            'puedeEditar' => $request->user()->can('update', $persona),
            'puedeCerrar' => $persona->relationships->isNotEmpty()
                && $request->user()->can('close', $persona->relationships->first()),
        ]);
    }
}
