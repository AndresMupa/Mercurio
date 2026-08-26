<?php

use App\Domains\Shared\Application\AuditRecorder;
use App\Http\Middleware\BindTenantToConnection;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;

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
        /*
         * El orden importa, y una parte de él costó un fallo real encontrado en B8.
         *
         * `SubstituteBindings` es el middleware que convierte `/personas/{persona}` en un
         * modelo. Va **al final a propósito**: en su sitio por defecto corre antes de que
         * el tenant esté fijado en la conexión, así que la consulta que resuelve el
         * modelo la hace la RLS sin contexto —no devuelve nada— y **toda ruta con un
         * identificador respondería 404**, a todo el mundo y siempre.
         *
         * Se descubrió porque la segunda petición de una prueba fallaba y la primera no:
         * la primera todavía arrastraba el contexto que había puesto el `beforeEach`.
         */
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                // Resolver el tenant va antes que todo lo demás: sin él la RLS no deja ni
                // buscar al usuario ni auditar un intento fallido de acceso.
                ResolveTenantFromRequest::class,
                BindTenantToConnection::class,
                SubstituteBindings::class,
                HandleInertiaRequests::class,
            ],
        );

        // Quien no ha entrado va al formulario, no a una ruta `login` que no existe.
        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/mi-trabajo');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * Todo error deja `result = error` en la auditoría (paso B4).
         *
         * Se envuelve en su propio try: si auditar el error fallara —la base caída, que
         * es justo cuando más errores hay— no puede tapar el error original ni provocar
         * un bucle. Se degrada a log, nunca a silencio (§25 del harness).
         */
        $exceptions->report(function (Throwable $e): void {
            try {
                app(AuditRecorder::class)->recordError($e);
            } catch (Throwable $fallo) {
                Log::error('No se pudo auditar un error.', [
                    'exception' => $e::class,
                    'audit_failure' => $fallo::class,
                ]);
            }
        });
    })->create();
