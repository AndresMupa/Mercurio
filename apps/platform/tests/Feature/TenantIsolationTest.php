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

it('no permite enumerar los demás tenants de la plataforma', function () {
    // La fila de un tenant es una fila de ese tenant: CA-01 no admite ninguna.
    // `tenants` no tiene columna tenant_id —su propio id es la llave— y por eso quedó
    // fuera de la lista original de RLS. El efecto era que cualquier cliente podía leer
    // la lista de clientes de la plataforma: nombre, slug, plan, región y estado del DPA.
    $slugs = DB::table('tenants')->pluck('slug');

    expect($slugs)->toContain($this->tenantA->slug)
        ->and($slugs)->not->toContain($this->tenantB->slug);

    expect(DB::table('tenants')->find($this->tenantB->id))->toBeNull();
});

it('no permite crear un tenant suplantando a otro', function () {
    expect(fn () => DB::table('tenants')->insert([
        'id' => (string) Str::uuid(),          // id distinto del contexto fijado
        'name' => 'Intruso',
        'slug' => 'intruso',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('no filtra por relación indirecta en ninguna tabla del núcleo', function () {
    // Antes solo miraba identities, relationships y assignments, y sobre tablas vacías:
    // count() == 0 salía verdadero tanto si la RLS filtraba como si no había nada que
    // filtrar. Ahora el tenant B tiene una fila en cada tabla y se comprueban todas.
    $tablas = createFullGraph($this->tenantB);

    // Blindaje contra la vacuidad: si el grafo no se pobló, las comprobaciones de abajo
    // pasarían sin comprobar nada. Se verifica desde el propio tenant B.
    foreach ($tablas as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(
            0, "El fixture no pobló «{$table}»: la prueba de aislamiento sería vacía"
        );
    }

    TenantContext::set($this->tenantA->id);

    foreach ($tablas as $table) {
        $delOtro = DB::table($table)->where('tenant_id', $this->tenantB->id)->count();
        expect($delOtro)->toBe(0, "«{$table}» filtró filas del otro tenant al filtrar por tenant_id");

        // Y sin filtro alguno: todo lo visible tiene que ser del tenant A. No se
        // comprueba «cero filas» porque el tenant A sí tiene las suyas; lo que no puede
        // haber es una sola fila ajena.
        $ajenas = DB::table($table)->where('tenant_id', '!=', $this->tenantA->id)->count();
        expect($ajenas)->toBe(0, "«{$table}» dejó ver filas ajenas al consultar sin filtro");
    }
});

it('una cola que corre fuera del contexto de la petición no ve ningún tenant', function () {
    // Riesgo P0 declarado en specs/identity/SPEC.md: los jobs corren fuera de la petición,
    // así que arrancan sin app.tenant_id. Se simula el arranque de un worker: conexión
    // nueva, sin contexto. Si viera datos, cualquier job sería una fuga cruzada.
    createFullGraph($this->tenantB);

    TenantContext::clear();
    DB::purge('pgsql');

    foreach (['people', 'identities', 'relationships', 'users', 'tenants'] as $table) {
        expect(DB::table($table)->count())->toBe(0, "Un worker sin tenant vio filas de «{$table}»");
    }
});

it('una cola solo ve el tenant que trae el job en su carga', function () {
    createFullGraph($this->tenantB);

    // El job fija el tenant de su payload antes de tocar la base (mitigación del SPEC).
    TenantContext::clear();
    DB::purge('pgsql');
    TenantContext::set($this->tenantB->id);

    // No se fija un número de filas —depende del fixture— sino la propiedad: todo lo
    // que el worker ve pertenece al tenant que venía en el job, y nada más.
    expect(DB::table('people')->where('tenant_id', '!=', $this->tenantB->id)->count())->toBe(0)
        ->and(DB::table('people')->count())->toBeGreaterThan(0)
        ->and(DB::table('tenants')->pluck('slug')->all())->toBe([$this->tenantB->slug]);
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
