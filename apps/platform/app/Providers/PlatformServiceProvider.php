<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\People\Domain\PersonRepository;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Infrastructure\EloquentPersonRepository;
use App\Domains\People\Infrastructure\EloquentRelationshipRepository;
use App\Domains\Shared\TenantContext;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Compuerta de arranque (PLAN.md, paso A2).
 *
 * Si el rol con el que corre la aplicación pudiera saltarse la RLS, el aislamiento entre
 * tenants sería ficción y ninguna prueba en verde significaría nada. La aplicación se
 * niega a operar en esas condiciones.
 *
 * Se engancha a la primera conexión de base de datos y no al arranque del framework, por
 * dos razones. La primera es que en el arranque todavía no hay conexión que interrogar, y
 * forzarla obligaría a conectar incluso en peticiones que no tocan la base. La segunda es
 * que el privilegio puede cambiar en caliente —un GRANT durante un incidente, una
 * restauración con otro rol— y comprobarlo al conectar cubre también la reconexión.
 *
 * El coste es una consulta a pg_roles por proceso, no por consulta.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    /** @var array<string, true> Conexiones ya verificadas en este proceso. */
    private static array $verificadas = [];

    /**
     * El dominio depende de la interfaz; la implementación con Eloquent se enchufa
     * aquí. Es lo que permite que una prueba unitaria de un invariante no necesite
     * base de datos si no la necesita de verdad.
     */
    public function register(): void
    {
        $this->app->bind(PersonRepository::class, EloquentPersonRepository::class);
        $this->app->bind(RelationshipRepository::class, EloquentRelationshipRepository::class);
    }

    public function boot(): void
    {
        Event::listen(function (ConnectionEstablished $evento): void {
            $this->verificar($evento->connectionName);
        });
    }

    public static function yaVerificada(string $conexion): bool
    {
        return isset(self::$verificadas[$conexion]);
    }

    /** Solo para pruebas: obliga a que la siguiente conexión vuelva a verificarse. */
    public static function olvidarVerificaciones(): void
    {
        self::$verificadas = [];
    }

    private function verificar(string $conexion): void
    {
        // Solo la conexión de runtime. La del dueño del esquema migra, no sirve peticiones,
        // y comprobarla obligaría a abrirla en cada arranque sin ninguna ganancia.
        if ($conexion !== config('database.default')) {
            return;
        }

        if (isset(self::$verificadas[$conexion])) {
            return;
        }

        // Se marca antes de comprobar: si la comprobación consultara la misma conexión,
        // no puede reentrar en este listener.
        self::$verificadas[$conexion] = true;

        TenantContext::assertRoleCannotBypassRls();
    }
}
