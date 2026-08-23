<?php

use App\Http\Middleware\BindTenantToConnection;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * BindTenantToConnection es la primera de las tres capas de aislamiento
         * (ARCHITECTURE.md): fija app.tenant_id en la conexión para que la RLS lo vea,
         * y lo limpia al terminar la petición. Se registra desde el primer día para que
         * ninguna ruta nazca fuera de la capa de aplicación del aislamiento.
         */
        $middleware->web(append: [
            BindTenantToConnection::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
