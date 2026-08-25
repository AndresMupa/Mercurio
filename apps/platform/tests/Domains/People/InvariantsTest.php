<?php

/**
 * Los nueve invariantes de `specs/identity/SPEC.md`, cada uno con su prueba propia.
 *
 * Varios ya estaban garantizados en base de datos desde B1. Aquí se prueban **desde el
 * dominio**, que es donde el resto del código los va a encontrar: de poco sirve que la
 * RLS aísle si el modelo permite escribir consultas que se la saltan sin querer.
 */

use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\Tenant;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\EnrollmentSnapshot;
use App\Domains\People\Domain\Exceptions\InvalidRelationshipTransition;
use App\Domains\People\Domain\Exceptions\RelationshipCannotBeDeleted;
use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Organization;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\People\Domain\Transitions\ActivateRelationship;
use App\Domains\People\Domain\Transitions\EndRelationship;
use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\Domain\DataClassification;
use App\Domains\Shared\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenantA = createTenant('colegio-inv-a');
    $this->tenantB = createTenant('colegio-inv-b');
    TenantContext::set($this->tenantA->id);
});

// ── Invariante 1 ────────────────────────────────────────────────────────────
it('invariante 1 · toda fila con datos de persona lleva el tenant del contexto', function () {
    $person = Person::create(['given_names' => 'Ana', 'family_names' => 'Docente']);

    expect($person->tenant_id)->toBe($this->tenantA->id);
});

it('invariante 1 · el tenant_id que venga de fuera se ignora, no se respeta', function () {
    // Aceptarlo sería dejar que el cuerpo de una petición decidiera a qué tenant escribe.
    $person = Person::create([
        'given_names' => 'Intruso',
        'family_names' => 'Cruzado',
        'tenant_id' => $this->tenantB->id,
    ]);

    expect($person->tenant_id)->toBe($this->tenantA->id);
})->group('tenant-isolation');

// ── Invariante 2 ────────────────────────────────────────────────────────────
it('invariante 2 · el global scope no deja ver personas de otro tenant', function () {
    TenantContext::set($this->tenantB->id);
    $ajena = Person::create(['given_names' => 'Beatriz', 'family_names' => 'Rectora']);

    TenantContext::set($this->tenantA->id);

    expect(Person::find($ajena->getKey()))->toBeNull()
        ->and(Person::count())->toBe(0);
})->group('tenant-isolation');

it('invariante 2 · sin tenant en el contexto no se ve nada, ni siquiera error', function () {
    Person::create(['given_names' => 'Ana', 'family_names' => 'Docente']);

    TenantContext::clear();

    // Cero filas, igual que hace la RLS. Las dos capas se comportan igual a propósito.
    expect(Person::count())->toBe(0);
})->group('tenant-isolation');

it('invariante 2 · quitar el global scope no basta: debajo sigue la RLS', function () {
    TenantContext::set($this->tenantB->id);
    Person::create(['given_names' => 'Beatriz', 'family_names' => 'Rectora']);

    TenantContext::set($this->tenantA->id);

    // Simulación de un fallo total de la capa de aplicación.
    expect(Person::withoutGlobalScopes()->count())->toBe(0);
})->group('tenant-isolation');

// ── Invariante 3 ────────────────────────────────────────────────────────────
it('invariante 3 · el dominio no puede crear una persona menor de edad', function () {
    expect(fn () => Person::create([
        'given_names' => 'Menor',
        'family_names' => 'Prueba',
        'birth_date' => now()->subYears(12)->toDateString(),
    ]))->toThrow(QueryException::class);
});

it('invariante 3 · tampoco puede convertir en menor a una persona existente', function () {
    $person = Person::create([
        'given_names' => 'Ana',
        'family_names' => 'Docente',
        'birth_date' => now()->subYears(40)->toDateString(),
    ]);

    expect(fn () => $person->update(['birth_date' => now()->subYears(10)->toDateString()]))
        ->toThrow(QueryException::class);
});

// ── Invariante 4 ────────────────────────────────────────────────────────────
it('invariante 4 · una persona puede tener varias relaciones vigentes a la vez', function () {
    $person = personaConDocumento();
    $entidadUno = crearEntidadJuridica('Colegio');
    $entidadDos = crearEntidadJuridica('Fundación');

    foreach ([$entidadUno, $entidadDos] as $entidad) {
        $relacion = Relationship::create([
            'person_id' => $person->getKey(),
            'legal_entity_id' => $entidad->getKey(),
            'type' => 'empleado',
            'valid_from' => now()->subMonth()->toDateString(),
            'status' => RelationshipStatus::Planned,
        ]);
        (new ActivateRelationship)->apply($relacion);
    }

    expect($person->activeRelationships()->count())->toBe(2);
});

// ── Invariante 5 ────────────────────────────────────────────────────────────
it('invariante 5 · una relación no se borra', function () {
    $relacion = relacionActiva();

    expect(fn () => $relacion->delete())->toThrow(RelationshipCannotBeDeleted::class);
    expect(Relationship::find($relacion->getKey()))->not->toBeNull();
});

it('invariante 5 · se cierra con valid_to y conserva su historia', function () {
    $relacion = relacionActiva();
    $hoy = Carbon::today()->toDateString();

    $cerrada = app(RelationshipRepository::class)
        ->close($relacion, $hoy);

    expect($cerrada->status)->toBe(RelationshipStatus::Ended)
        ->and($cerrada->valid_to->toDateString())->toBe($hoy)
        ->and($cerrada->isCurrentOn())->toBeFalse()
        // La fila sigue ahí: es la historia laboral que hay que poder mostrar.
        ->and(Relationship::find($relacion->getKey()))->not->toBeNull();
});

it('invariante 5 · el contrato del repositorio no expone borrado', function () {
    $metodos = get_class_methods(RelationshipRepository::class);

    expect($metodos)->not->toContain('delete')
        ->and($metodos)->not->toContain('destroy')
        ->and($metodos)->toContain('close');
});

// ── Invariante 6 ────────────────────────────────────────────────────────────
it('invariante 6 · audit_events no admite UPDATE desde el dominio', function () {
    $evento = AuditEvent::create([
        'action' => 'person.read',
        'resource_type' => 'people',
        'result' => 'allowed',
        'occurred_at' => now(),
    ]);

    expect(fn () => $evento->update(['action' => 'manipulado']))->toThrow(RuntimeException::class);
})->group('tenant-isolation');

it('invariante 6 · y la base lo impide aunque el modelo no estuviera', function () {
    $evento = AuditEvent::create([
        'action' => 'person.read',
        'resource_type' => 'people',
        'result' => 'allowed',
        'occurred_at' => now(),
    ]);

    // Sin pasar por el modelo: privilegios de PostgreSQL, no convención de código.
    expect(fn () => DB::table('audit_events')->where('id', $evento->getKey())->update(['action' => 'x']))
        ->toThrow(QueryException::class);
})->group('tenant-isolation');

// ── Borrado de tenant, revocado por decisión humana el 25-ago-2026 ──────────
it('la aplicación no puede borrar un tenant, y por tanto no puede arrasar en cascada', function () {
    // Los doce FOREIGN KEY que apuntan a `tenants` son ON DELETE CASCADE. Mientras
    // `platform_app` tuvo DELETE sobre la tabla, un solo DELETE FROM tenants destruía
    // todo lo del tenant sin vuelta atrás. La decisión fue revocarlo: el ciclo de vida
    // se expresa con `tenants.status`, y suprimir de verdad será un procedimiento
    // explícito con el rol dueño, no un privilegio permanente de la aplicación.
    $tenant = createTenant('colegio-para-borrar');
    TenantContext::set($tenant->id);

    expect(fn () => DB::table('tenants')->where('id', $tenant->id)->delete())
        ->toThrow(QueryException::class);

    // Y sigue ahí: la RLS deja ver la propia fila, así que si el DELETE hubiera pasado
    // esto daría cero y la prueba distinguiría «lo impidió» de «no lo intentó».
    expect(DB::table('tenants')->where('id', $tenant->id)->count())->toBe(1);
})->group('tenant-isolation');

it('el rol de aplicación no conserva ni DELETE ni TRUNCATE sobre tenants', function () {
    // Se comprueba en el catálogo y no ejecutando un TRUNCATE porque un
    // `TRUNCATE tenants CASCADE` falla antes por otra tabla —`organizations`— y pasaría
    // igual sin la revocación. Verificado con una sonda: una prueba que pasa por el
    // motivo equivocado no prueba lo que dice probar.
    //
    // TRUNCATE importa aparte de DELETE: vacía la tabla sin disparar `ON DELETE CASCADE`
    // ni las políticas de RLS, así que dejarlo concedido sería la misma puerta con otro
    // nombre.
    $privilegios = DB::connection('pgsql_owner')
        ->table('information_schema.table_privileges')
        ->where('table_name', 'tenants')
        ->where('grantee', config('database.connections.pgsql.username'))
        ->pluck('privilege_type')
        ->all();

    expect($privilegios)->not->toContain('DELETE')
        ->and($privilegios)->not->toContain('TRUNCATE')
        // Y sigue pudiendo trabajar: revocar de más rompería el aprovisionamiento.
        ->and($privilegios)->toContain('SELECT')
        ->and($privilegios)->toContain('INSERT')
        ->and($privilegios)->toContain('UPDATE');
})->group('tenant-isolation');

// ── Invariante 7 ────────────────────────────────────────────────────────────
it('invariante 7 · el registro de clasificación sabe qué columnas son P3', function () {
    // El invariante completo —«toda lectura P3 declara purpose»— se cierra en B3 con el
    // AuthorizationContext. Lo que B2 aporta es su base: saber qué es P3 desde un único
    // sitio, y no de la memoria de quien escriba la Policy.
    expect(DataClassification::of('people', 'birth_date'))->toBe(DataClassification::P3)
        ->and(DataClassification::of('people', 'sex'))->toBe(DataClassification::P3)
        ->and(DataClassification::of('identities', 'document_number'))->toBe(DataClassification::P3)
        ->and(DataClassification::isSensitive('people', 'given_names'))->toBeFalse()
        ->and(DataClassification::ceilingOf('people'))->toBe(DataClassification::P3);
});

// ── Invariante 8 ────────────────────────────────────────────────────────────
it('invariante 8 · cerrada la relación, deja de otorgar acceso operativo de inmediato', function () {
    $relacion = relacionActiva();
    expect($relacion->status->grantsOperationalAccess())->toBeTrue();

    app(RelationshipRepository::class)
        ->close($relacion, Carbon::today()->toDateString());

    // Se relee como haría la siguiente petición: nada cacheado, nada en sesión.
    $releida = Relationship::find($relacion->getKey());

    expect($releida->status->grantsOperationalAccess())->toBeFalse()
        ->and($releida->isCurrentOn())->toBeFalse();
});

it('invariante 8 · un role_assignment vencido no otorga permisos (CA-08)', function () {
    // `password` no es asignable en masa a propósito: es P3 y se pone explícitamente.
    $user = new User(['email' => 'a@ejemplo.test', 'status' => 'active']);
    $user->password = 'contrasena-de-prueba';
    $user->save();
    $role = Role::create(['key' => 'admin_rrhh', 'name' => 'Talento humano']);

    $vencido = RoleAssignment::create([
        'user_id' => $user->getKey(), 'role_id' => $role->getKey(),
        'scope_type' => 'tenant',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => now()->subDay()->toDateString(),
    ]);

    expect($vencido->isEffectiveOn())->toBeFalse()
        ->and(RoleAssignment::effective()->count())->toBe(0);
});

// ── Invariante 9 ────────────────────────────────────────────────────────────
it('invariante 9 · enrollment_snapshots no admite ninguna columna identificadora', function () {
    $columnas = DB::connection('pgsql_owner')
        ->getSchemaBuilder()
        ->getColumnListing('enrollment_snapshots');

    $prohibidas = ['name', 'given_names', 'family_names', 'document_number',
        'document_type', 'birth_date', 'person_id', 'student_id', 'email', 'guardian_id'];

    expect(array_intersect($columnas, $prohibidas))->toBeEmpty();
})->group('tenant-isolation');

it('invariante 9 · el modelo tampoco deja asignar un identificador', function () {
    $snapshot = new EnrollmentSnapshot;

    foreach (['person_id', 'student_id', 'given_names', 'document_number'] as $prohibida) {
        expect($snapshot->isFillable($prohibida))->toBeFalse(
            "«{$prohibida}» no puede ser asignable en enrollment_snapshots (ADR 0003)"
        );
    }
});

// ── Máquina de estados ──────────────────────────────────────────────────────
it('la máquina rechaza una transición ilegal en vez de dejar pasar el estado', function () {
    $relacion = relacionActiva();

    // ACTIVE no admite ActivateRelationship: solo PLANNED.
    expect(fn () => (new ActivateRelationship)->apply($relacion))
        ->toThrow(InvalidRelationshipTransition::class);
});

it('ended es terminal: de ahí no sale ninguna transición', function () {
    $relacion = relacionActiva();
    (new EndRelationship)->apply($relacion, ['valid_to' => Carbon::today()->toDateString()]);

    expect(fn () => (new ActivateRelationship)->apply($relacion))
        ->toThrow(InvalidRelationshipTransition::class);
});

it('cada transición deja su propio AuditEvent', function () {
    $relacion = relacionActiva();   // ya pasó por ActivateRelationship

    (new EndRelationship)->apply($relacion, ['valid_to' => Carbon::today()->toDateString()]);

    $acciones = AuditEvent::where('resource_id', (string) $relacion->getKey())
        ->orderBy('id')->pluck('action')->all();

    // `relationships.created` lo añade B4: crear es una escritura y deja rastro. Los
    // otros dos son los eventos con significado de dominio que ponen las transiciones.
    // Que convivan es deliberado: uno dice qué cambió, los otros qué significó el cambio,
    // y comparten correlation_id, así que se leen juntos.
    expect($acciones)->toBe([
        'relationships.created',
        'relationship.activated',
        'relationship.ended',
    ]);
});

it('la auditoría de una transición no guarda ningún dato P3', function () {
    $relacion = relacionActiva();
    (new EndRelationship)->apply($relacion, ['valid_to' => Carbon::today()->toDateString()]);

    $contexto = json_encode(AuditEvent::pluck('context')->all(), JSON_THROW_ON_ERROR);

    // Nombres y documentos son P3/P2: no se copian dentro del contexto de auditoría.
    foreach (['Ana', 'Docente', '1020304050'] as $prohibido) {
        expect($contexto)->not->toContain($prohibido);
    }
});

it('todos los eventos de una misma operación comparten correlation_id', function () {
    // Sin esto, `correlation_id` es una columna con un uuid distinto en cada fila: parece
    // que correlaciona y no correlaciona nada. Reconstruir un incidente depende de esto.
    CorrelationId::clear();
    $relacion = relacionActiva();
    (new EndRelationship)->apply($relacion, ['valid_to' => Carbon::today()->toDateString()]);

    $ids = AuditEvent::where('resource_id', (string) $relacion->getKey())
        ->pluck('correlation_id')->unique();

    expect($ids)->toHaveCount(1)->and($ids->first())->not->toBeNull();
});

// ── Ayudas ──────────────────────────────────────────────────────────────────
function crearEntidadJuridica(string $nombre): LegalEntity
{
    $org = Organization::create(['name' => $nombre]);

    return LegalEntity::create([
        'organization_id' => $org->getKey(),
        'name' => $nombre,
    ]);
}

function personaConDocumento(): Person
{
    $person = Person::create([
        'given_names' => 'Ana',
        'family_names' => 'Docente',
        'birth_date' => now()->subYears(40)->toDateString(),
    ]);

    PersonIdentity::create([
        'person_id' => $person->getKey(),
        'document_type' => 'cedula',
        'document_number' => '1020304050',
    ]);

    return $person;
}

function relacionActiva(): Relationship
{
    $person = personaConDocumento();
    $entidad = crearEntidadJuridica('Colegio');

    $relacion = Relationship::create([
        'person_id' => $person->getKey(),
        'legal_entity_id' => $entidad->getKey(),
        'type' => 'empleado',
        'valid_from' => now()->subMonth()->toDateString(),
        'status' => RelationshipStatus::Planned,
    ]);

    return (new ActivateRelationship)->apply($relacion);
}
