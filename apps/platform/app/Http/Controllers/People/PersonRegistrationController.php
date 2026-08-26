<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Domains\People\Application\PersonRegistration;
use App\Domains\People\Application\RegistrationCatalog;
use App\Domains\People\Domain\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recorrido J1, de punta a punta.
 *
 * La regla del recorrido es que nadie llegue al final y descubra que faltaba un dato, así
 * que hay dos rutas de comprobación **antes** de guardar: buscar por documento y revisar el
 * formulario entero. Las dos llaman al mismo servicio que valida al guardar, de modo que
 * no puede haber un error que solo aparezca al final.
 */
final class PersonRegistrationController
{
    public function __construct(
        private readonly PersonRegistration $registro,
        private readonly RegistrationCatalog $catalogo,
    ) {}

    public function show(Request $request): Response
    {
        $request->user()->can('create', Person::class) ?: abort(403);

        return Inertia::render('Personas/Nueva', [
            'catalogo' => $this->catalogo->paraFormulario(),
        ]);
    }

    /** Paso 1 de J1: ¿ya existe esta persona? */
    public function buscar(Request $request): JsonResponse
    {
        $request->user()->can('create', Person::class) ?: abort(403);

        $datos = $request->validate([
            'documento' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($this->registro->buscarPorDocumento($datos['documento']));
    }

    /** Validación contextual: se llama al salir de cada campo, no al final. */
    public function revisar(Request $request): JsonResponse
    {
        $request->user()->can('create', Person::class) ?: abort(403);

        return response()->json($this->registro->revisar($request->all()));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->can('create', Person::class) ?: abort(403);

        $datos = $request->validate([
            'given_names' => ['required', 'string', 'max:120'],
            'family_names' => ['required', 'string', 'max:120'],
            'document_type' => ['required', 'string', 'max:32'],
            'document_number' => ['required', 'string', 'max:32'],
            'birth_date' => ['required', 'date', 'before:today'],
            'sex' => ['nullable', 'string', 'max:20'],
            'education_level' => ['nullable', 'string', 'max:40'],
            'teaching_statute' => ['nullable', 'string', 'max:20'],
            'teaching_grade' => ['nullable', 'string', 'max:4'],
            'legal_entity_id' => ['required', 'uuid'],
            'site_id' => ['required', 'uuid'],
            'position_id' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:32'],
            'employment_type' => ['required', 'string', 'max:32'],
            'valid_from' => ['required', 'date'],
            'teaching_level' => ['nullable', 'string', 'max:32'],
            'weekly_hours' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        // Los bloqueos se vuelven a comprobar en el servidor aunque el formulario ya los
        // haya mostrado: el cliente puede haberse saltado la comprobación, y lo que
        // decide es esto.
        $revision = $this->registro->revisar($datos);

        if ($revision['bloqueos'] !== []) {
            return back()->withErrors($revision['bloqueos'])->withInput();
        }

        $persona = $this->registro->registrar($datos);

        return redirect("/personas/{$persona->getKey()}")
            ->with('aviso', [
                'tono' => 'ok',
                'texto' => "{$persona->given_names} {$persona->family_names} ya aparece en la planta vigente.",
            ]);
    }
}
