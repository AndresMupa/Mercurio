<?php

/**
 * Grupo tenant-isolation. Lo ejecuta el hook de pre-commit y bloquea el commit si falla.
 *
 * Estas pruebas no comprueban el camino feliz: intentan activamente romper el aislamiento.
 * Una prueba que pasa cuando debía fallar es un P0 SECURITY INCIDENT (AGENTS.md §16).
 */

use App\Domains\Shared\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('tenant-isolation');

beforeEach(function () {
    $this->tenantA = createTenant('colegio-demo-a');
    $this->tenantB = createTenant('colegio-demo-b');

    TenantContext::set($this->tenantA->id);
    $this->personA = createPerson($this->tenantA, 'Ana', 'Docente');

    TenantContext::set($this->tenantB->id);
    $this->personB = createPerson($this->tenantB, 'Beatriz', 'Rectora');

    TenantContext::set($this->tenantA->id);
});

it('el rol de la aplicación no puede saltarse la RLS', function () {
    TenantContext::assertRoleCannotBypassRls();
})->throwsNoExceptions();

it('no devuelve filas de otro tenant al listar', function () {
    $ids = DB::table('people')->pluck('id');

    expect($ids)->toContain($this->personA->id)
        ->and($ids)->not->toContain($this->personB->id);
});

it('no devuelve una fila de otro tenant consultada por id directo', function () {
    expect(DB::table('people')->find($this->personB->id))->toBeNull();
});

it('no permite escribir sobre un recurso de otro tenant', function () {
    $affected = DB::table('people')
        ->where('id', $this->personB->id)
        ->update(['family_names' => 'Modificado']);

    expect($affected)->toBe(0);
});

it('no permite insertar una fila con el tenant_id de otro', function () {
    expect(fn () => DB::table('people')->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantB->id,   // ← intento de suplantación
        'given_names' => 'Intruso',
        'family_names' => 'Cruzado',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('no filtra por relación indirecta: identidades, relaciones ni asignaciones', function () {
    foreach (['identities', 'relationships', 'assignments'] as $table) {
        $leak = DB::table($table)->where('tenant_id', $this->tenantB->id)->count();
        expect($leak)->toBe(0, "La tabla {$table} filtró filas del otro tenant");
    }
});

it('sigue aislando aunque el guard de aplicación esté desactivado', function () {
    // Se simula un fallo total de la capa de aplicación: la RLS es la que debe sostener.
    TenantContext::clear();

    expect(DB::table('people')->count())->toBe(0);
});

it('no permite modificar ni borrar eventos de auditoría', function () {
    $id = DB::table('audit_events')->insertGetId([
        'tenant_id' => $this->tenantA->id,
        'action' => 'person.read',
        'resource_type' => 'people',
        'resource_id' => $this->personA->id,
        'purpose' => 'reporte_c600',
        'data_classification' => 'P3',
        'result' => 'allowed',
        'occurred_at' => now(),
    ]);

    expect(fn () => DB::table('audit_events')->where('id', $id)->update(['action' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_events')->where('id', $id)->delete())
        ->toThrow(QueryException::class);
});

it('no deja el tenant fijado entre peticiones en conexiones agrupadas', function () {
    TenantContext::set($this->tenantA->id);
    TenantContext::clear();

    $value = DB::selectOne("SELECT current_setting('app.tenant_id', true) AS v")->v;

    expect($value)->toBeIn([null, '']);
});

it('rechaza almacenar la identidad de un menor de edad', function () {
    expect(fn () => createPerson($this->tenantA, 'Menor', 'Prueba', now()->subYears(12)))
        ->toThrow(QueryException::class);
})->group('adr-0003');

it('enrollment_snapshots no expone ninguna columna identificadora', function () {
    $columns = Schema::getColumnListing('enrollment_snapshots');

    $prohibidas = ['name', 'given_names', 'family_names', 'document_number',
        'birth_date', 'person_id', 'student_id', 'email'];

    expect(array_intersect($columns, $prohibidas))->toBeEmpty();
})->group('adr-0003');
