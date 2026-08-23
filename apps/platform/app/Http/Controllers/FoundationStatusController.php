<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Shared\FoundationCheck;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entregable del paso A1: el proyecto levanta y responde en el navegador mostrando
 * el estado real de la fundación, no un texto de bienvenida.
 */
final class FoundationStatusController
{
    public function __invoke(FoundationCheck $checks): Response
    {
        return Inertia::render('Estado', [
            'slice' => 'Slice 0 · Foundation',
            'paso' => 'A1 · Proyecto e infraestructura',
            'comprobaciones' => $checks->all(),
        ]);
    }
}
