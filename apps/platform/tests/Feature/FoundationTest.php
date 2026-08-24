<?php

/**
 * Entregable del paso A1 (PLAN.md): el proyecto levanta y la separación de roles de
 * base de datos es real, no una convención de configuración.
 *
 * Estas pruebas existen porque la garantía que sostiene todo lo demás —RLS forzada y
 * auditoría append-only— depende de que el rol de runtime no pueda deshacerla. Si esa
 * condición se pierde en un cambio de infraestructura, tiene que romperse aquí.
 */

use App\Domains\Shared\FoundationCheck;
use App\Domains\Shared\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('responde la pantalla de estado de la fundación', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Estado')
                ->where('paso', 'A1 · Proyecto e infraestructura')
                ->has('comprobaciones', 5)
                ->has('comprobaciones.0.nombre')
                ->has('comprobaciones.0.estado')
        );
});

it('expone el punto de salud del framework', function () {
    $this->get('/up')->assertOk();
});

it('no reporta ninguna falla en la fundación', function (FoundationCheck $checks) {
    $fallas = array_filter(
        $checks->all(),
        fn (array $c) => $c['estado'] === FoundationCheck::CRITICAL
    );

    expect($fallas)->toBeEmpty();
})->with([fn () => app(FoundationCheck::class)]);

it('el rol de runtime no puede saltarse la RLS', function () {
    TenantContext::assertRoleCannotBypassRls();
})->throwsNoExceptions();

it('el rol de runtime no es el dueño del esquema', function () {
    $runtime = DB::selectOne('SELECT current_user AS role')->role;
    $owner = DB::connection('pgsql_owner')->selectOne('SELECT current_user AS role')->role;

    expect($runtime)->not->toBe($owner);

    // No basta con que sean distintos: si uno heredara del otro, la separación
    // sería nominal y el rol de aplicación tendría los privilegios del dueño.
    $hereda = DB::selectOne(
        'SELECT pg_has_role(current_user, ?, ?) AS member',
        [$owner, 'USAGE']
    )->member;

    expect($hereda)->toBeFalse();
});

it('el rol de runtime no puede crear tablas', function () {
    // Si pudiera, sería dueño de lo que crea, y sobre lo propio se puede hacer
    // ALTER TABLE ... NO FORCE ROW LEVEL SECURITY. El aislamiento dejaría de ser garantía.
    expect(fn () => DB::statement('CREATE TABLE intento_de_tabla (id int)'))
        ->toThrow(QueryException::class);
});

it('el rol de runtime no puede desactivar la RLS de una tabla existente', function () {
    expect(fn () => DB::statement('ALTER TABLE people NO FORCE ROW LEVEL SECURITY'))
        ->toThrow(QueryException::class);

    expect(fn () => DB::statement('ALTER TABLE people DISABLE ROW LEVEL SECURITY'))
        ->toThrow(QueryException::class);
})->group('tenant-isolation');

it('toda tabla del esquema tiene RLS activa y forzada', function () {
    // Deliberadamente NO se enumeran las tablas esperadas. La lista escrita a mano fue
    // justamente el fallo: `tenants` quedó fuera y nadie lo notó, porque una prueba que
    // comprueba las tablas que alguien recordó anotar no comprueba las que se olvidan.
    // Esta pregunta al catálogo, así que una tabla nueva sin RLS rompe aquí sola.
    $sinRls = DB::connection('pgsql_owner')->select("
        SELECT c.relname,
               c.relrowsecurity      AS activa,
               c.relforcerowsecurity AS forzada
          FROM pg_class c
          JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public'
           AND c.relkind = 'r'
           AND c.relname <> 'migrations'   -- de Laravel, sin datos de tenant
           AND (NOT c.relrowsecurity OR NOT c.relforcerowsecurity)
    ");

    $nombres = array_map(fn (object $t) => $t->relname, $sinRls);

    expect($nombres)->toBeEmpty(
        'Tablas sin RLS activa y forzada: '.implode(', ', $nombres)
    );
})->group('tenant-isolation');

it('toda tabla del esquema tiene su política de aislamiento', function () {
    $sinPolitica = DB::connection('pgsql_owner')->select("
        SELECT c.relname
          FROM pg_class c
          JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public'
           AND c.relkind = 'r'
           AND c.relname <> 'migrations'
           AND NOT EXISTS (
               SELECT 1 FROM pg_policies p
                WHERE p.schemaname = 'public'
                  AND p.tablename = c.relname
                  AND p.policyname = 'tenant_isolation'
           )
    ");

    $nombres = array_map(fn (object $t) => $t->relname, $sinPolitica);

    // RLS activa sin política no filtra: deniega todo. Sería fallar cerrado, pero
    // rompería la aplicación en silencio en vez de aislar.
    expect($nombres)->toBeEmpty(
        'Tablas con RLS pero sin política tenant_isolation: '.implode(', ', $nombres)
    );
})->group('tenant-isolation');
