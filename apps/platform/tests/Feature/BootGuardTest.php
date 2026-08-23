<?php

/**
 * La compuerta de arranque del paso A2: la aplicación no opera si el rol de runtime
 * puede saltarse la RLS.
 *
 * Estas pruebas comprueban el *cableado*, que es lo que se rompe en la práctica. Que la
 * comprobación en sí distingue un rol con BYPASSRLS de uno sin él lo cubre
 * `TenantContext::assertRoleCannotBypassRls()`, probada en FoundationTest.
 */

use App\Providers\PlatformServiceProvider;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    PlatformServiceProvider::olvidarVerificaciones();
});

it('verifica el rol al establecer la conexión de runtime', function () {
    PlatformServiceProvider::olvidarVerificaciones();
    DB::purge('pgsql');

    expect(PlatformServiceProvider::yaVerificada('pgsql'))->toBeFalse();

    DB::connection('pgsql')->select('SELECT 1');

    expect(PlatformServiceProvider::yaVerificada('pgsql'))->toBeTrue();
})->group('tenant-isolation');

it('no vuelve a interrogar a pg_roles en cada consulta', function () {
    PlatformServiceProvider::olvidarVerificaciones();
    DB::purge('pgsql');
    DB::connection('pgsql')->select('SELECT 1');

    DB::enableQueryLog();
    DB::connection('pgsql')->select('SELECT 1');
    DB::connection('pgsql')->select('SELECT 1');
    $consultas = DB::getQueryLog();
    DB::disableQueryLog();

    $aPgRoles = array_filter($consultas, fn (array $q) => str_contains($q['query'], 'pg_roles'));

    expect($aPgRoles)->toBeEmpty();
});

it('no obliga a abrir la conexión del dueño del esquema', function () {
    PlatformServiceProvider::olvidarVerificaciones();
    DB::purge('pgsql');
    DB::connection('pgsql')->select('SELECT 1');

    // Verificar la conexión de migraciones habría exigido abrirla aquí, sin ganancia.
    expect(PlatformServiceProvider::yaVerificada('pgsql_owner'))->toBeFalse();
});
