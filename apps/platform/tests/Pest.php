<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Helpers de prueba. Crean datos directamente por consulta para no depender del dominio
 * al probar el aislamiento: si las pruebas de RLS usaran los repositorios, estarían
 * comprobando el guard de aplicación en vez de la base de datos.
 */
function createTenant(string $slug): object
{
    $id = (string) Str::uuid();

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
