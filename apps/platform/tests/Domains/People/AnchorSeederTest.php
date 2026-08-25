<?php

/**
 * El tenant demo tiene **la forma** del colegio ancla y **ningún dato real** (PLAN.md B6).
 *
 * Estas pruebas no comparan el seeder consigo mismo. Las cifras esperadas están escritas a
 * mano aquí, copiadas de `docs/anchor/colegio-finlandes.md`, precisamente para que editar la
 * plantilla del seeder no arrastre en silencio a la expectativa. Si alguien cambia el número
 * de docentes, esto rompe; que es lo que tiene que pasar.
 *
 * Por qué importa que las cifras cuadren al detalle: el Slice 1 deriva el C600 de este
 * tenant. Si el demo sumara 881 estudiantes, un total equivocado en el reporte no se podría
 * atribuir ni al motor ni a los datos.
 */

use App\Domains\Identity\Application\TenantResolver;
use App\Domains\People\Domain\EnrollmentSnapshot;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\Site;
use App\Domains\Shared\TenantContext;
use Database\Seeders\AnchorEnrollment;
use Database\Seeders\AnchorTenantSeeder;
use Database\Seeders\SyntheticPeople;
use Illuminate\Support\Facades\DB;

/** Siembra y deja el contexto en el tenant demo. Devuelve su id. */
function sembrarAncla(): string
{
    app(AnchorTenantSeeder::class)->run();

    $demo = app(TenantResolver::class)->bySlug('colegio-demo');

    expect($demo)->not->toBeNull('el seeder no dejó resoluble el tenant demo');

    TenantContext::set($demo);

    return $demo;
}

/** El vocabulario de nombres inventados, leído del propio generador. */
function vocabularioSintetico(): array
{
    $constantes = (new ReflectionClass(SyntheticPeople::class))->getConstants();

    return [
        'nombres' => array_merge($constantes['GIVEN_FEMALE'], $constantes['GIVEN_MALE']),
        'apellidos' => $constantes['FAMILY'],
    ];
}

// ── La matrícula como dato, comprobable sin tocar la base ────────────────────

it('la matrícula del ancla suma exactamente los 883 estudiantes del documento', function () {
    $total = array_sum(array_column(AnchorEnrollment::rows(), 'headcount'));

    expect($total)->toBe(AnchorEnrollment::TOTAL)
        ->and($total)->toBe(883);
});

it('la matrícula del ancla declara los 13 estudiantes en condición de discapacidad', function () {
    $conDiscapacidad = array_sum(array_column(
        array_filter(AnchorEnrollment::rows(), fn ($f) => $f['condition'] === 'discapacidad'),
        'headcount'
    ));

    expect($conDiscapacidad)->toBe(AnchorEnrollment::WITH_DISABILITY)
        ->and($conDiscapacidad)->toBe(13);
});

it('ninguna fila de matrícula viene con cero estudiantes', function () {
    // Una fila en cero no es un conteo: es una casilla del formulario que alguien dejó
    // puesta. En un agregado, además, estorba —hace parecer que hay un grupo donde no lo
    // hay— y el C600 la rechazaría.
    foreach (AnchorEnrollment::rows() as $fila) {
        expect($fila['headcount'])->toBeGreaterThan(0);
    }
});

// ── El generador: ficticio, determinista y sin colisiones ────────────────────

it('el generador de personas es determinista', function () {
    // Un demo que cambia de nombres en cada ejecución no se puede comentar con nadie
    // —«mira la fila de Ana» deja de significar algo— y vuelve frágil cualquier prueba.
    $primera = new SyntheticPeople;
    $segunda = new SyntheticPeople;

    for ($i = 0; $i < 20; $i++) {
        expect($primera->next('femenino', 40))->toBe($segunda->next('femenino', 40));
    }
});

it('el generador nunca repite un documento y no sale del rango reservado', function () {
    $generador = new SyntheticPeople;
    $documentos = [];

    for ($i = 0; $i < 200; $i++) {
        $documentos[] = (int) $generador->next('masculino', 30)['document_number'];
    }

    expect(array_unique($documentos))->toHaveCount(200)
        ->and(min($documentos))->toBeGreaterThanOrEqual(90_000_000)
        // El rango tiene que seguir siendo reconocible como inventado de un vistazo.
        ->and(max($documentos))->toBeLessThan(91_000_000);
});

// ── CA-10 · la forma del ancla ───────────────────────────────────────────────

it('CA-10 · el tenant demo tiene las 64 personas con relación laboral del ancla', function () {
    sembrarAncla();

    expect(Person::count())->toBe(64);
});

it('CA-10 · la distribución por tipo de personal es la del documento del ancla', function () {
    sembrarAncla();

    $distribucion = DB::table('assignments')
        ->join('positions', 'positions.id', '=', 'assignments.position_id')
        ->selectRaw('positions.personnel_type, count(*) AS total')
        ->groupBy('positions.personnel_type')
        ->pluck('total', 'personnel_type')
        ->map(fn ($n) => (int) $n)
        ->all();

    // docs/anchor/colegio-finlandes.md: 47 docentes · 6 directivos · 2 de apoyo pedagógico
    // (orientadora y auxiliar de notas) · 4 administrativos · 5 de servicios generales.
    expect($distribucion)->toEqual([
        'docente_aula' => 47,
        'directivo_docente' => 6,
        'docente_orientador' => 1,
        'apoyo_en_aula' => 1,
        'administrativo' => 4,
        'servicios_generales' => 5,
    ])->and(array_sum($distribucion))->toBe(64);
});

it('CA-10 · los 47 docentes de aula se reparten por nivel como en el ancla', function () {
    sembrarAncla();

    $porNivel = DB::table('assignments')
        ->whereNotNull('teaching_level')
        ->selectRaw('teaching_level, count(*) AS total')
        ->groupBy('teaching_level')
        ->pluck('total', 'teaching_level')
        ->map(fn ($n) => (int) $n)
        ->all();

    expect($porNivel)->toEqual([
        'preescolar' => 4,
        'basica_primaria' => 18,
        'basica_secundaria' => 15,
        'media' => 10,
    ])->and(array_sum($porNivel))->toBe(47);
});

it('CA-10 · la sede reproduce la planta física y la ubicación del ancla', function () {
    sembrarAncla();

    $sede = Site::where('is_work_center', true)->firstOrFail();

    expect($sede->classroom_count)->toBe(29)
        ->and($sede->built_area_m2)->toBe(2476)
        ->and($sede->lot_area_m2)->toBe(4633)
        ->and($sede->area_type)->toBe('rural')
        // 12 dígitos: es la llave con la que el C600 identifica la sede (Slice 1).
        ->and($sede->dane_code)->toMatch('/^\d{12}$/');
});

it('CA-10 · la matrícula sembrada suma 883 estudiantes, 13 con discapacidad', function () {
    sembrarAncla();

    expect((int) EnrollmentSnapshot::sum('headcount'))->toBe(883)
        ->and((int) EnrollmentSnapshot::where('condition', 'discapacidad')->sum('headcount'))->toBe(13);
});

it('CA-10 · no hay un solo nombre ni documento real en el tenant demo', function () {
    sembrarAncla();

    ['nombres' => $nombres, 'apellidos' => $apellidos] = vocabularioSintetico();

    foreach (Person::all() as $persona) {
        expect(in_array($persona->given_names, $nombres, true))
            ->toBeTrue("«{$persona->given_names}» no sale del generador sintético");

        foreach (explode(' ', $persona->family_names) as $apellido) {
            expect(in_array($apellido, $apellidos, true))
                ->toBeTrue("«{$apellido}» no sale del generador sintético");
        }
    }

    $documentos = PersonIdentity::pluck('document_number')->map(fn ($d) => (int) $d);

    expect($documentos)->toHaveCount(64)
        ->and($documentos->unique())->toHaveCount(64)
        ->and($documentos->min())->toBeGreaterThanOrEqual(90_000_000)
        ->and($documentos->max())->toBeLessThan(91_000_000);
});

it('CA-10 · el tenant demo no contiene ninguna persona menor de edad', function () {
    // Regla 6 de CLAUDE.md. Hay un trigger que lo impide, pero el seeder es justo el sitio
    // donde alguien metería una edad de estudiante «para probar el módulo de menores».
    sembrarAncla();

    $mayoria = now()->subYears(18)->toDateString();

    expect(Person::whereDate('birth_date', '>', $mayoria)->count())->toBe(0);
});

// ── CA-11 · la matrícula no identifica a nadie ───────────────────────────────

it('CA-11 · cada valor de la matrícula del demo sale de un vocabulario agregado cerrado', function () {
    // La prueba de B1 mira el esquema: que no exista una columna identificadora. Esta mira
    // los datos: que en las columnas que sí existen no se haya colado texto libre, que es
    // por donde entraría un nombre —«Juan, 3.º B»— sin necesidad de cambiar el esquema.
    sembrarAncla();

    $permitido = [
        'level' => ['preescolar', 'basica_primaria', 'basica_secundaria', 'media'],
        'shift' => ['completa', 'manana', 'tarde', 'nocturna', 'fin_de_semana'],
        'sex' => ['femenino', 'masculino', 'no_informado'],
        'condition' => ['ninguna', 'discapacidad'],
        'educational_model' => ['tradicional'],
        'source' => ['sintetico'],
        'grade' => ['prejardin', 'jardin', 'transicion',
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'],
    ];

    foreach (EnrollmentSnapshot::all() as $fila) {
        foreach ($permitido as $columna => $valores) {
            expect(in_array($fila->{$columna}, $valores, true))
                ->toBeTrue("«{$fila->{$columna}}» no es un valor agregado válido de {$columna}");
        }

        // Un rango, nunca una edad exacta: la edad exacta más el grado y la sede señala a
        // un estudiante concreto en un curso de treinta.
        expect($fila->age_range)->toMatch('/^\d{1,2}-\d{1,2}$/');
    }
});

it('CA-11 · ninguna fila de matrícula arrastra un dato de una persona del demo', function () {
    sembrarAncla();

    $personales = Person::all()
        ->flatMap(fn ($p) => array_merge([$p->given_names], explode(' ', $p->family_names)))
        ->merge(PersonIdentity::pluck('document_number'))
        ->unique();

    // Solo las columnas de contenido: las llaves subrogadas son uuid y meterlas aquí haría
    // la prueba dependiente del azar de la generación.
    $contenido = EnrollmentSnapshot::all()
        ->map(fn ($f) => implode(' ', [
            $f->school_year, $f->level, $f->grade, $f->shift,
            $f->sex, $f->age_range, $f->condition, $f->educational_model,
            $f->headcount, $f->source,
        ]))
        ->implode(' ');

    foreach ($personales as $dato) {
        expect($contenido)->not->toContain((string) $dato);
    }
})->group('tenant-isolation');

// ── El vecino, que es para lo que existe ─────────────────────────────────────

it('el tenant vecino no ve ni una persona ni una fila de matrícula del demo', function () {
    sembrarAncla();

    $vecino = app(TenantResolver::class)->bySlug('colegio-vecino');
    TenantContext::set($vecino);

    // Tres personas propias: si aquí saliera 67, el aislamiento no estaría filtrando nada.
    expect(Person::count())->toBe(3)
        ->and(EnrollmentSnapshot::count())->toBe(0);

    // El vecino tiene sede propia; lo que no puede es alcanzar la del demo. Se comprueba
    // por la planta física, que es lo único que distingue una sede de la otra sin uuid.
    expect(Site::count())->toBe(1)
        ->and(Site::where('dane_code', '325430001368')->count())->toBe(0)
        ->and(Site::where('classroom_count', 29)->count())->toBe(0);
})->group('tenant-isolation');

it('sembrar dos veces se niega en vez de duplicar el demo', function () {
    sembrarAncla();

    // Volver a sembrar encima exigiría borrar el tenant anterior, y borrar un tenant es la
    // operación irreversible que sigue esperando decisión humana en STATE.md.
    app(AnchorTenantSeeder::class)->run();

    TenantContext::set(app(TenantResolver::class)->bySlug('colegio-demo'));

    expect(Person::count())->toBe(64)
        ->and((int) EnrollmentSnapshot::sum('headcount'))->toBe(883)
        // El dueño ve todos los tenants por la política de solo lectura de B5.
        ->and(DB::connection('pgsql_owner')->table('tenants')->count())->toBe(2);
});
