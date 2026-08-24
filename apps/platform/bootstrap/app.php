<?php

use App\Domains\Shared\Application\AuditRecorder;
use App\Http\Middleware\BindTenantToConnection;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->web(append: [
            BindTenantToConnection::class,
            HandleInertiaRequests::class,
        ]);
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
