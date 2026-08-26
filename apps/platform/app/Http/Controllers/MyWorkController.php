<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\People\Application\WorkInbox;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** La bandeja. Los asuntos los deriva `WorkInbox` del estado real, no de una tabla de tareas. */
final class MyWorkController
{
    public function __construct(private readonly WorkInbox $bandeja) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('MiTrabajo', [
            'asuntos' => $this->bandeja->para($request->user()),
            // El «hoy» viaja desde el servidor para que los plazos no dependan del reloj
            // del navegador, que puede estar en otra zona horaria o directamente mal.
            'hoy' => now()->toDateString(),
        ]);
    }
}
