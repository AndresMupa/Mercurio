<?php

/**
 * Reglas de arquitectura que hasta ahora solo estaban escritas en prosa.
 *
 * `ARCHITECTURE.md` dice «la lógica de negocio no vive en componentes Vue ni en
 * controladores HTTP», y `data-classification.md` dice «un campo sin clasificación
 * asignada rompe el build». Las dos eran frases que nadie comprobaba. Aquí se comprueban.
 */

use App\Domains\Shared\Domain\DataClassification;
use Illuminate\Support\Facades\DB;

it('el registro de clasificación cubre todas las tablas del esquema', function () {
    // Esto es la regla 5 de data-classification.md hecha mecanismo. Una tabla nueva sin
    // clasificar rompe aquí, que es antes de que alguien guarde datos en ella.
    $enEsquema = collect(DB::connection('pgsql_owner')->select("
        SELECT c.relname
          FROM pg_class c
          JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname <> 'migrations'
    "))->pluck('relname')->sort()->values();

    $sinClasificar = $enEsquema->diff(DataClassification::declaredTables())->values();

    expect($sinClasificar->all())->toBeEmpty(
        'Tablas sin clasificación declarada: '.$sinClasificar->implode(', ')
    );
});

it('toda columna de una tabla con datos de personas tiene nivel declarado', function () {
    $conPersonas = ['people', 'identities', 'relationships', 'assignments', 'users', 'audit_events'];
    $huerfanas = [];

    foreach ($conPersonas as $tabla) {
        $columnas = DB::connection('pgsql_owner')->getSchemaBuilder()->getColumnListing($tabla);

        foreach ($columnas as $columna) {
            if (DataClassification::of($tabla, $columna) === null) {
                $huerfanas[] = "{$tabla}.{$columna}";
            }
        }
    }

    expect($huerfanas)->toBeEmpty('Columnas sin clasificar: '.implode(', ', $huerfanas));
});

it('los controladores no hablan con la base de datos', function () {
    // «La lógica de negocio no vive en controladores» es difícil de comprobar en abstracto.
    // Lo que sí se puede comprobar es su síntoma más fiable: un controlador que consulta
    // la base directamente se ha saltado el dominio y el repositorio.
    expect('App\Http\Controllers')
        ->not->toUse([
            'Illuminate\Support\Facades\DB',
            'Illuminate\Database\Eloquent\Builder',
        ]);
})->group('arch');

it('el dominio no depende de la capa HTTP', function () {
    // Si el dominio importara del HTTP, dejaría de poder usarse desde una cola, un
    // comando o un import por lotes, que es justo lo que va a hacer falta en D1.
    expect('App\Domains')->not->toUse('App\Http');
})->group('arch');

it('los modelos del núcleo no viven fuera de su dominio', function () {
    // `app/Models` es la convención por defecto de Laravel y aquí no se usa: el modelo
    // pertenece a su bounded context (ARCHITECTURE.md).
    expect(is_dir(app_path('Models')))->toBeFalse(
        'app/Models volvió a existir: los modelos van en app/Domains/<Contexto>/Domain'
    );
});
