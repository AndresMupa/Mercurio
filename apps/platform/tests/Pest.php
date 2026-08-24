<?php

use App\Domains\Shared\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ResetsPlatformDatabase;
use Tests\TestCase;

uses(TestCase::class, ResetsPlatformDatabase::class)->in('Feature');

/**
 * Helpers de prueba. Crean datos directamente por consulta para no depender del dominio
 * al probar el aislamiento: si las pruebas de RLS usaran los repositorios, estarían
 * comprobando el guard de aplicación en vez de la base de datos.
 */
function createTenant(string $slug): object
{
    $id = (string) Str::uuid();

    // `tenants` también está bajo RLS, con su propio `id` como llave del aislamiento.
    // Su WITH CHECK exige que el contexto ya apunte al tenant que se está creando: sin
    // esto el INSERT se rechaza. Aprovisionar un tenant es, por diseño, una operación
    // que declara a qué tenant pertenece antes de escribir.
    TenantContext::set($id);

    DB::table('tenants')->insert([
        'id' => $id,
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id, 'slug' => $slug];
}

function createPerson(object $tenant, string $given, string $family, $birthDate = null): object
{
    $id = (string) Str::uuid();

    DB::table('people')->insert([
        'id' => $id,
        'tenant_id' => $tenant->id,
        'given_names' => $given,
        'family_names' => $family,
        'birth_date' => $birthDate ?? now()->subYears(35)->toDateString(),
        'sex' => 'no_informado',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (object) ['id' => $id];
}

/**
 * Construye un grafo completo de un tenant: una fila en cada tabla con `tenant_id`.
 *
 * Existe porque una prueba de aislamiento sobre tablas vacías no prueba nada: `count() == 0`
 * sale verdadero tanto si la RLS filtra como si simplemente no hay datos. Para afirmar que
 * un tenant no ve al otro hay que asegurarse primero de que el otro tiene algo que ver.
 *
 * @return list<string> nombres de las tablas pobladas
 */
function createFullGraph(object $tenant): array
{
    TenantContext::set($tenant->id);

    $t = $tenant->id;
    $uuid = fn () => (string) Str::uuid();
    $now = now();

    $org = $uuid();
    $legalEntity = $uuid();
    $site = $uuid();
    $position = $uuid();
    $person = $uuid();
    $relationship = $uuid();
    $user = $uuid();
    $role = $uuid();

    DB::table('organizations')->insert([
        'id' => $org, 'tenant_id' => $t, 'name' => 'Org', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('legal_entities')->insert([
        'id' => $legalEntity, 'tenant_id' => $t, 'organization_id' => $org,
        'name' => 'Entidad', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('sites')->insert([
        'id' => $site, 'tenant_id' => $t, 'legal_entity_id' => $legalEntity,
        'name' => 'Sede', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('positions')->insert([
        'id' => $position, 'tenant_id' => $t, 'legal_entity_id' => $legalEntity,
        'title' => 'Cargo', 'personnel_type' => 'docente_aula',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('people')->insert([
        'id' => $person, 'tenant_id' => $t, 'given_names' => 'Nombre',
        'family_names' => 'Apellido', 'birth_date' => now()->subYears(40)->toDateString(),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('identities')->insert([
        'id' => $uuid(), 'tenant_id' => $t, 'person_id' => $person,
        'document_type' => 'cedula', 'document_number' => (string) random_int(10_000_000, 99_999_999),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('relationships')->insert([
        'id' => $relationship, 'tenant_id' => $t, 'person_id' => $person,
        'legal_entity_id' => $legalEntity, 'site_id' => $site, 'type' => 'empleado',
        'valid_from' => now()->toDateString(), 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('assignments')->insert([
        'id' => $uuid(), 'tenant_id' => $t, 'relationship_id' => $relationship,
        'position_id' => $position, 'valid_from' => now()->toDateString(),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('users')->insert([
        'id' => $user, 'tenant_id' => $t, 'person_id' => $person,
        'email' => $tenant->slug.'@ejemplo.test', 'password' => 'no-es-un-hash-real',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('roles')->insert([
        'id' => $role, 'tenant_id' => $t, 'key' => 'admin_rrhh', 'name' => 'Talento humano',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('role_assignments')->insert([
        'id' => $uuid(), 'tenant_id' => $t, 'user_id' => $user, 'role_id' => $role,
        'scope_type' => 'tenant', 'valid_from' => now()->toDateString(),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('enrollment_snapshots')->insert([
        'id' => $uuid(), 'tenant_id' => $t, 'site_id' => $site, 'school_year' => 2026,
        'level' => 'basica_primaria', 'shift' => 'completa', 'headcount' => 30,
        'captured_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('audit_events')->insert([
        'tenant_id' => $t, 'action' => 'person.created', 'resource_type' => 'people',
        'resource_id' => $person, 'result' => 'allowed', 'occurred_at' => $now,
    ]);

    return ['organizations', 'legal_entities', 'sites', 'positions', 'people', 'identities',
        'relationships', 'assignments', 'users', 'roles', 'role_assignments',
        'enrollment_snapshots', 'audit_events'];
}
