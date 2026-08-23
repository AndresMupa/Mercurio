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

it('las trece tablas con datos de personas tienen RLS activa y forzada', function () {
    $tablas = ['organizations', 'legal_entities', 'sites', 'positions', 'people',
        'identities', 'relationships', 'assignments', 'users', 'roles',
        'role_assignments', 'enrollment_snapshots', 'audit_events'];

    $filas = DB::connection('pgsql_owner')->select(
        'SELECT relname, relrowsecurity, relforcerowsecurity
           FROM pg_class
          WHERE relname = ANY(?)',
        ['{'.implode(',', $tablas).'}']
    );

    expect($filas)->toHaveCount(count($tablas));

    foreach ($filas as $fila) {
        expect($fila->relrowsecurity)->toBeTrue("RLS inactiva en {$fila->relname}");
        expect($fila->relforcerowsecurity)->toBeTrue("RLS no forzada en {$fila->relname}");
    }
})->group('tenant-isolation');
