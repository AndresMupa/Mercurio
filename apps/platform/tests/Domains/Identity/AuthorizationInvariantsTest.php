<?php

/**
 * Los siete invariantes de `docs/architecture/permissions.md` y los criterios de
 * aceptación CA-04, CA-05, CA-07 y CA-08 del SPEC.
 *
 * La matriz celda por celda vive en `PermissionMatrixTest`. Aquí se prueba lo que ninguna
 * celda expresa: lo que tiene que seguir siendo cierto sea cual sea la combinación.
 */

use App\Domains\Identity\Application\Authorizer;
use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\Authorization\Purpose;
use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Organization;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\Domain\DataClassification;
use App\Domains\Shared\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->tenantA = createTenant('colegio-auth-a');
    $this->tenantB = createTenant('colegio-auth-b');

    TenantContext::set($this->tenantA->id);
    $org = Organization::create(['name' => 'Colegio']);
    $this->legalEntity = LegalEntity::create(['organization_id' => $org->getKey(), 'name' => 'Colegio']);
    $this->person = Person::create([
        'given_names' => 'Ana', 'family_names' => 'Docente',
        'birth_date' => now()->subYears(40)->toDateString(),
    ]);

    $this->authorizer = app(Authorizer::class);
});

/** Usuario con rol, relación vigente opcional y vencimiento opcional. */
function actor(object $t, string $rolKey, array $opciones = []): User
{
    $user = new User([
        'email' => $rolKey.'-'.bin2hex(random_bytes(3)).'@ejemplo.test',
        'person_id' => ($opciones['propia'] ?? false) ? $t->person->getKey() : null,
        'status' => 'active',
    ]);
    $user->password = 'contrasena-de-prueba';
    $user->save();

    if (($opciones['propia'] ?? false) && ($opciones['conRelacion'] ?? true)) {
        Relationship::create([
            'person_id' => $t->person->getKey(),
            'legal_entity_id' => $t->legalEntity->getKey(),
            'type' => 'empleado',
            'valid_from' => now()->subMonth()->toDateString(),
            'status' => RelationshipStatus::Active,
        ]);
    }

    $role = Role::firstOrCreate(['key' => $rolKey], ['name' => $rolKey]);

    RoleAssignment::create([
        'user_id' => $user->getKey(),
        'role_id' => $role->getKey(),
        'scope_type' => $opciones['scope'] ?? 'tenant',
        'valid_from' => $opciones['desde'] ?? now()->subMonth()->toDateString(),
        'valid_to' => array_key_exists('hasta', $opciones)
            ? $opciones['hasta']
            : ($rolKey === 'soporte_plataforma' ? now()->addWeek()->toDateString() : null),
    ]);

    return $user;
}

function contexto(object $t, User $user, string $recurso, string $accion, array $extra = []): AuthorizationContext
{
    return new AuthorizationContext(
        user: $user,
        tenantId: $extra['tenantId'] ?? $t->tenantA->id,
        resource: $recurso,
        action: $accion,
        resourceTenantId: $extra['resourceTenantId'] ?? $t->tenantA->id,
        resourcePersonId: $extra['resourcePersonId'] ?? (string) $t->person->getKey(),
        purpose: $extra['purpose'] ?? null,
        dataClassification: $extra['clasificacion'] ?? DataClassification::P2,
    );
}

// ── Invariante 1 ────────────────────────────────────────────────────────────
it('invariante 1 · ninguna combinación de roles da acceso cross-tenant', function () {
    // Se prueba con el rol más poderoso que existe: si el owner no cruza, nadie cruza.
    $owner = actor($this, 'owner');

    $decision = $this->authorizer->authorize(contexto($this, $owner, 'people', 'listar', [
        'resourceTenantId' => $this->tenantB->id,
    ]));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.tenant');
})->group('tenant-isolation');

it('invariante 1 · el tenant se comprueba antes que el rol', function () {
    // Importa el orden: si el rol se mirara primero, un acierto de rol sobre un recurso
    // ajeno registraría «permitido» antes de que nadie mirara el tenant.
    $owner = actor($this, 'owner');

    $decision = $this->authorizer->authorize(contexto($this, $owner, 'people', 'crear', [
        'resourceTenantId' => $this->tenantB->id,
    ]));

    expect($decision->rule)->toBe('abac.tenant');
})->group('tenant-isolation');

// ── Invariante 2 ────────────────────────────────────────────────────────────
it('invariante 2 · ningún rol lee P3 sin propósito', function (string $rol) {
    $user = actor($this, $rol, ['propia' => $rol === 'trabajador']);

    $decision = $this->authorizer->authorize(contexto($this, $user, 'people.birth_date', 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => null,
    ]));

    expect($decision->allowed)->toBeFalse();
})->with(['owner', 'admin_rrhh', 'responsable_sst', 'trabajador'])->group('authorization');

// ── Invariante 3 ────────────────────────────────────────────────────────────
it('invariante 3 · el trabajador siempre lee su propio P3', function (string $recurso) {
    $trabajador = actor($this, 'trabajador', ['propia' => true, 'scope' => 'self']);

    $decision = $this->authorizer->authorize(contexto($this, $trabajador, $recurso, 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => Purpose::SolicitudDelTitular,
    ]));

    expect($decision->allowed)->toBeTrue($decision->reason);
})->with(['people.birth_date', 'people.sex', 'identities.document_number'])->group('authorization');

it('invariante 3 · pero no el P3 de otra persona', function () {
    $trabajador = actor($this, 'trabajador', ['propia' => true, 'scope' => 'self']);

    $otra = Person::create(['given_names' => 'Otro', 'family_names' => 'Empleado']);

    $decision = $this->authorizer->authorize(contexto($this, $trabajador, 'people.birth_date', 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => Purpose::SolicitudDelTitular,
        'resourcePersonId' => (string) $otra->getKey(),
    ]));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.self');
})->group('authorization');

// ── Invariante 4 ────────────────────────────────────────────────────────────
it('invariante 4 · nadie, ni el owner, modifica o borra auditoría', function (string $accion) {
    $owner = actor($this, 'owner');

    $decision = $this->authorizer->authorize(contexto($this, $owner, 'audit_events', $accion));

    expect($decision->allowed)->toBeFalse();
})->with(['modificar', 'borrar'])->group('tenant-isolation');

// ── Invariante 5 · CA-07 ────────────────────────────────────────────────────
it('invariante 5 · cerrada la relación, el acceso cesa en la siguiente petición', function () {
    $trabajador = actor($this, 'trabajador', ['propia' => true, 'scope' => 'self']);

    $antes = $this->authorizer->authorize(contexto($this, $trabajador, 'people', 'listar'));
    expect($antes->allowed)->toBeTrue($antes->reason);

    // Se cierra la relación. El rol sigue asignado y vigente, y la sesión sigue abierta.
    $relacion = Relationship::where('person_id', $this->person->getKey())->current()->firstOrFail();
    app(RelationshipRepository::class)->close($relacion, Carbon::today()->toDateString());

    // Siguiente petición: se resuelve de nuevo, sin caché ni sesión de por medio.
    $despues = $this->authorizer->authorize(contexto($this, $trabajador->fresh(), 'people', 'listar'));

    expect($despues->allowed)->toBeFalse()
        ->and($despues->rule)->toBe('abac.relacion');
})->group('authorization');

// ── Invariante 6 ────────────────────────────────────────────────────────────
it('invariante 6 · soporte_plataforma caduca solo, sin que nadie intervenga', function () {
    $soporte = actor($this, 'soporte_plataforma', ['hasta' => now()->addDay()->toDateString()]);

    $vigente = $this->authorizer->authorize(contexto($this, $soporte, 'enrollment_snapshots', 'leer', [
        'clasificacion' => DataClassification::P1,
    ]));
    expect($vigente->allowed)->toBeTrue($vigente->reason);

    // Pasa el tiempo. Nadie revoca nada.
    Carbon::setTestNow(now()->addDays(3));

    $caducado = $this->authorizer->authorize(contexto($this, $soporte, 'enrollment_snapshots', 'leer', [
        'clasificacion' => DataClassification::P1,
    ]));

    Carbon::setTestNow();

    expect($caducado->allowed)->toBeFalse()
        ->and($caducado->rule)->toBe('abac.vigencia');
})->group('authorization');

it('invariante 6 · una asignación de soporte sin vencimiento no otorga nada', function () {
    // El acceso de soporte perpetuo es el que nadie recuerda revocar.
    $soporte = actor($this, 'soporte_plataforma', ['hasta' => null]);

    $decision = $this->authorizer->authorize(contexto($this, $soporte, 'enrollment_snapshots', 'leer', [
        'clasificacion' => DataClassification::P1,
    ]));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.soporte_sin_vencimiento');
})->group('authorization');

// ── Invariante 7 ────────────────────────────────────────────────────────────
it('invariante 7 · el auditor lee trazas', function () {
    $auditor = actor($this, 'auditor');

    $decision = $this->authorizer->authorize(contexto($this, $auditor, 'audit_events', 'leer'));

    expect($decision->allowed)->toBeTrue($decision->reason);
})->group('authorization');

it('invariante 7 · pero no lee P3 ni declarando propósito', function (string $recurso) {
    $auditor = actor($this, 'auditor');

    $decision = $this->authorizer->authorize(contexto($this, $auditor, $recurso, 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => Purpose::AuditoriaInterna,
    ]));

    expect($decision->allowed)->toBeFalse();
})->with(['people.birth_date', 'people.sex', 'identities.document_number'])->group('authorization');

// ── CA-04 ───────────────────────────────────────────────────────────────────
it('CA-04 · leer P3 sin propósito deniega y deja audit_events con result denied', function () {
    $admin = actor($this, 'admin_rrhh');

    $decision = $this->authorizer->authorize(contexto($this, $admin, 'people.birth_date', 'leer', [
        'clasificacion' => DataClassification::P3,
    ]));

    expect($decision->allowed)->toBeFalse();

    $evento = AuditEvent::where('action', 'people.birth_date.leer')->latest('id')->first();

    expect($evento)->not->toBeNull()
        ->and($evento->result)->toBe('denied')
        ->and($evento->data_classification)->toBe(DataClassification::P3)
        ->and($evento->actor_user_id)->toBe((string) $admin->getKey())
        ->and($evento->context['rule'])->toBe('abac.purpose');
})->group('authorization');

it('CA-04 · un propósito fuera de la lista cerrada no es un propósito', function () {
    expect(Purpose::tryFromNullable('lo_que_sea'))->toBeNull()
        ->and(Purpose::tryFromNullable('reporte_c600'))->toBe(Purpose::ReporteC600);
});

// ── CA-05 ───────────────────────────────────────────────────────────────────
it('CA-05 · toda lectura P3 autorizada deja su rastro completo', function () {
    CorrelationId::clear();
    $admin = actor($this, 'admin_rrhh');

    $decision = $this->authorizer->authorize(contexto($this, $admin, 'identities.document_number', 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => Purpose::ReporteC600,
    ]));

    expect($decision->allowed)->toBeTrue($decision->reason);

    $evento = AuditEvent::where('action', 'identities.document_number.leer')->latest('id')->first();

    expect($evento->result)->toBe('allowed')
        ->and($evento->purpose)->toBe('reporte_c600')
        ->and($evento->actor_user_id)->toBe((string) $admin->getKey())
        ->and($evento->correlation_id)->toBe(CorrelationId::current())
        ->and($evento->data_classification)->toBe(DataClassification::P3);
})->group('authorization');

it('CA-05 · el rastro no copia el dato que se leyó', function () {
    $admin = actor($this, 'admin_rrhh');

    $this->authorizer->authorize(contexto($this, $admin, 'people.birth_date', 'leer', [
        'clasificacion' => DataClassification::P3,
        'purpose' => Purpose::ReporteC600,
    ]));

    $contexto = json_encode(AuditEvent::pluck('context')->all(), JSON_THROW_ON_ERROR);

    expect($contexto)->not->toContain('Ana')
        ->and($contexto)->not->toContain('Docente')
        ->and($contexto)->not->toContain($this->person->birth_date->toDateString());
})->group('authorization');

// ── CA-08 ───────────────────────────────────────────────────────────────────
it('CA-08 · un role_assignment vencido no otorga permisos aunque siga asignado', function () {
    $admin = actor($this, 'admin_rrhh', [
        'desde' => now()->subYear()->toDateString(),
        'hasta' => now()->subDay()->toDateString(),
    ]);

    // La fila sigue ahí: no se borró, venció.
    expect(RoleAssignment::where('user_id', $admin->getKey())->count())->toBe(1);

    $decision = $this->authorizer->authorize(contexto($this, $admin, 'people', 'crear'));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.vigencia');
})->group('authorization');

it('CA-08 · un rol que aún no empieza tampoco otorga permisos', function () {
    $admin = actor($this, 'admin_rrhh', ['desde' => now()->addWeek()->toDateString()]);

    $decision = $this->authorizer->authorize(contexto($this, $admin, 'people', 'crear'));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.vigencia');
})->group('authorization');

// ── Falla cerrada ───────────────────────────────────────────────────────────
it('un recurso que la matriz no declara se deniega, no se permite', function () {
    $owner = actor($this, 'owner');

    $decision = $this->authorizer->authorize(contexto($this, $owner, 'clinical_records', 'leer'));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('matrix.undeclared');
})->group('authorization');

it('ningún rol de esta matriz alcanza P4 por herencia', function () {
    // Nota final de permissions.md: el Clinical Vault se otorga por asignación explícita,
    // nunca por ser owner. Se comprueba ya, antes de que el Slice 5 exista.
    $owner = actor($this, 'owner');

    $decision = $this->authorizer->authorize(contexto($this, $owner, 'people', 'listar', [
        'clasificacion' => DataClassification::P4,
    ]));

    expect($decision->allowed)->toBeFalse()
        ->and($decision->rule)->toBe('abac.techo');
})->group('authorization');

// ── La frontera del rector ──────────────────────────────────────────────────
//
// Decidido el 25-ago-2026, cerrando el hueco que B3 dejó visible y que bloqueaba B7:
// **liderazgo, no administración de RR. HH.** Estas dos pruebas fijan la frontera por
// los dos lados. La matriz celda a celda ya está en `PermissionMatrixTest`; lo que se
// comprueba aquí es que la línea siga donde se decidió y no se corra sin querer.

it('el rector ve a su gente y sus cifras', function (string $recurso, string $accion, ?Purpose $proposito) {
    $rector = actor($this, 'rector');

    $decision = $this->authorizer->authorize(
        contexto($this, $rector, $recurso, $accion, ['purpose' => $proposito])
    );

    expect($decision->allowed)->toBeTrue("el rector debería poder «{$accion}» sobre «{$recurso}»");
})->with([
    'listar personas' => ['people', 'listar', null],
    'leer la fecha de nacimiento con propósito' => ['people.birth_date', 'leer', Purpose::GestionLaboral],
    'listar relaciones' => ['relationships', 'listar', null],
    'consultar la matrícula' => ['enrollment_snapshots', 'leer', null],
    'generar el C600' => ['reports.c600', 'generar', null],
    'generar el EVI' => ['reports.evi', 'generar', null],
    'exportar reportes' => ['reports.c600', 'exportar', null],
    'leer la auditoría' => ['audit_events', 'leer', null],
])->group('authorization');

it('el rector no administra: ni personal, ni catálogo, ni la plataforma', function (string $recurso, string $accion) {
    // `relationships · cerrar` está aquí a propósito y no es un descuido: terminar un
    // vínculo laboral tiene efecto jurídico y la regla 5 de CLAUDE.md exige responsable
    // identificado. El rector lo decide como directivo; RR. HH. lo ejecuta, y así la
    // auditoría distingue las dos cosas en vez de fundirlas en un solo evento.
    $rector = actor($this, 'rector');

    $decision = $this->authorizer->authorize(contexto($this, $rector, $recurso, $accion));

    expect($decision->allowed)->toBeFalse("el rector no debería poder «{$accion}» sobre «{$recurso}»")
        ->and($decision->rule)->toBe('matrix.deny');
})->with([
    'crear personas' => ['people', 'crear'],
    'editar personas' => ['people', 'editar'],
    'crear relaciones' => ['relationships', 'crear'],
    'cerrar relaciones' => ['relationships', 'cerrar'],
    'gestionar asignaciones' => ['assignments', 'gestionar'],
    'gestionar cargos' => ['positions', 'gestionar'],
    'gestionar sedes' => ['sites', 'gestionar'],
    'cargar matrícula' => ['enrollment_snapshots', 'cargar'],
    'gestionar usuarios' => ['users', 'gestionar'],
    'gestionar roles' => ['role_assignments', 'gestionar'],
    'gestionar el DPA' => ['dpa', 'gestionar'],
    'modificar la auditoría' => ['audit_events', 'modificar'],
])->group('authorization');

// ── Hallazgos del threat model de B3 ────────────────────────────────────────
it('omitir la clasificación no rebaja la sensibilidad del recurso', function () {
    // Hallazgo del threat model: la clasificación llegaba desde el llamador, así que
    // olvidarla saltaba la comprobación del techo del rol. Un `auditor` con techo P2
    // leía P3 con solo no decir que era P3. Ahora la decide la matriz.
    $auditor = actor($this, 'auditor');

    $sinDeclarar = $this->authorizer->authorize(contexto($this, $auditor, 'people.birth_date', 'leer', [
        'clasificacion' => null,
        'purpose' => Purpose::AuditoriaInterna,
    ]));

    expect($sinDeclarar->allowed)->toBeFalse();
})->group('authorization');

it('un llamador no puede rebajar la clasificación declarando una menor', function () {
    $auditor = actor($this, 'auditor');

    $rebajada = $this->authorizer->authorize(contexto($this, $auditor, 'people.birth_date', 'leer', [
        'clasificacion' => DataClassification::P1,   // intento de rebaja
        'purpose' => Purpose::AuditoriaInterna,
    ]));

    // No se comprueba *qué* regla lo detuvo: la matriz ya deniega a `auditor` sobre este
    // recurso antes de llegar al techo, y que lo pare la primera puerta es correcto. Lo
    // que se afirma es que la rebaja no compra nada, y que la traza sigue diciendo P3.
    expect($rebajada->allowed)->toBeFalse();

    $evento = AuditEvent::where('action', 'people.birth_date.leer')->latest('id')->first();
    expect($evento->data_classification)->toBe(DataClassification::P3);
})->group('authorization');

it('una lectura P3 se audita aunque el llamador no declare la clasificación', function () {
    $admin = actor($this, 'admin_rrhh');

    $this->authorizer->authorize(contexto($this, $admin, 'people.sex', 'leer', [
        'clasificacion' => null,
        'purpose' => Purpose::ReporteC600,
    ]));

    $evento = AuditEvent::where('action', 'people.sex.leer')->latest('id')->first();

    expect($evento)->not->toBeNull()
        ->and($evento->data_classification)->toBe(DataClassification::P3);
})->group('authorization');
