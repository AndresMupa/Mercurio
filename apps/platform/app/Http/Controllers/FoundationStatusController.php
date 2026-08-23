<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Shared\FoundationCheck;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entregable del paso A1: el proyecto levanta y responde en el navegador mostrando
 * el estado real de la fundación, no un texto de bienvenida.
 *
 * Esta es la **única** superficie que informa de un fallo de la fundación en vez de morir
 * con él. La compuerta de arranque (PlatformServiceProvider) lanza en cuanto se establece
 * la conexión, así que cualquier otra ruta que toque la base devuelve error; aquí
 * FoundationCheck atrapa el fallo a propósito, porque un diagnóstico que se cae en lugar
 * de decir qué está roto no sirve para nada. No copies este patrón en pantallas de
 * producto: ahí el fallo tiene que propagarse.
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
