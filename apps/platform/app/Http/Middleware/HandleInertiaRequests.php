<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Datos compartidos con todas las páginas.
     *
     * Regla: nunca se comparte aquí un dato P3 ni P4. Lo compartido viaja en cada
     * respuesta de Inertia y quedaría fuera del control de propósito y auditoría que
     * exige `docs/architecture/data-classification.md`.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
            ],
        ];
    }
}
