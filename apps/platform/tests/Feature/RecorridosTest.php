<?php

/**
 * Los dos recorridos críticos del Slice 0, de punta a punta y por HTTP.
 *
 * No son pruebas de componentes: entran por la ruta, pasan por el middleware de tenant,
 * por la Policy, por el `Authorizer` y por el dominio, y comprueban lo que queda en la
 * base y en la auditoría. Un recorrido probado por partes puede tener todas sus partes en
 * verde y no funcionar entero, que es exactamente lo que J1 y J5 no se pueden permitir.
 *
 * J1 · buscar por documento → crear persona → identidad → relación → asignación → planta
 * J5 · cerrar relación → motivo → impacto visible → el acceso cesa en la siguiente petición
 */

use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Organization;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\Position;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\People\Domain\Site;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->tenant = createTenant('colegio-recorridos');
    TenantContext::set($this->tenant->id);

    $organizacion = Organization::create(['name' => 'Colegio de pruebas']);

    $this->entidad = LegalEntity::create([
        'organization_id' => $organizacion->getKey(),
        'name' => 'Colegio de pruebas S.A.S.',
        'country_code' => 'CO',
    ]);

    $this->sede = Site::create([
        'legal_entity_id' => $this->entidad->getKey(),
        'name' => 'Sede principal',
        'is_work_center' => true,
    ]);

    $this->cargo = Position::create([
        'legal_entity_id' => $this->entidad->getKey(),
        'title' => 'Docente de primaria',
        'personnel_type' => 'docente_aula',
    ]);
});

/**
 * Vuelve a fijar el tenant después de una petición HTTP.
 *
 * `BindTenantToConnection::terminate()` lo limpia al terminar cada petición —obligatorio,
 * o el tenant sobreviviría a la petición en conexiones agrupadas—, así que sin esto las
 * aserciones de después corren sin contexto y la RLS no devuelve nada. Que haga falta es
 * la prueba de que esa limpieza funciona.
 */
function devuelveElContexto(object $test): void
{
    TenantContext::set($test->tenant->id);
}

/** Un usuario con un rol vigente y sin persona asociada: no le hace falta para administrar. */
function comoRol(object $test, string $rolKey): User
{
    $usuario = new User(['email' => "{$rolKey}@recorridos.test", 'status' => 'active']);
    $usuario->password = 'no-se-usa';
    $usuario->save();

    RoleAssignment::create([
        'user_id' => $usuario->getKey(),
        'role_id' => Role::firstOrCreate(['key' => $rolKey], ['name' => $rolKey])->getKey(),
        'scope_type' => 'tenant',
        'valid_from' => now()->subMonth()->toDateString(),
    ]);

    return $usuario;
}

/** @param  array<string, mixed>  $extra */
function altaValida(object $test, array $extra = []): array
{
    return [
        'document_type' => 'cedula',
        'document_number' => '90000501',
        'given_names' => 'Teresa',
        'family_names' => 'Mahecha Ibáñez',
        'birth_date' => '1989-07-14',
        'sex' => 'femenino',
        'education_level' => 'licenciado',
        'legal_entity_id' => (string) $test->entidad->getKey(),
        'site_id' => (string) $test->sede->getKey(),
        'position_id' => (string) $test->cargo->getKey(),
        'type' => 'empleado',
        'employment_type' => 'indefinido',
        'valid_from' => now()->subYear()->toDateString(),
        'teaching_level' => 'basica_primaria',
        'weekly_hours' => 40,
        ...$extra,
    ];
}

// ══ J1 ══════════════════════════════════════════════════════════════════════

it('J1 · el alta completa deja a la persona en la planta vigente', function () {
    $rrhh = comoRol($this, 'admin_rrhh');

    $this->actingAs($rrhh)
        ->post('/personas', altaValida($this))
        ->assertRedirect();

    devuelveElContexto($this);

    $persona = Person::where('family_names', 'Mahecha Ibáñez')->first();

    expect($persona)->not->toBeNull()
        // La identidad se crea en la misma transacción: sin ella no se puede reportar
        // en el C600, y un alta a medias obliga a alguien a limpiar a mano.
        ->and($persona->identities)->toHaveCount(1)
        ->and($persona->identities->first()->document_number)->toBe('90000501');

    $relacion = $persona->relationships->first();

    expect($relacion)->not->toBeNull()
        // Nace planeada y la máquina la activa: así queda `relationship.activated` en la
        // auditoría, que es el evento por el que un inspector sabe desde cuándo trabaja.
        ->and($relacion->status)->toBe(RelationshipStatus::Active)
        ->and($relacion->isCurrentOn(Carbon::today()))->toBeTrue()
        ->and($relacion->assignments)->toHaveCount(1)
        ->and($relacion->assignments->first()->position_id)->toBe($this->cargo->getKey());
});

it('J1 · el alta deja rastro en la auditoría', function () {
    $rrhh = comoRol($this, 'admin_rrhh');

    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $acciones = AuditEvent::pluck('action');

    expect($acciones)->toContain('people.created')
        ->and($acciones)->toContain('relationship.activated');
});

it('J1 · buscar por documento encuentra a quien ya existe', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));

    $this->actingAs($rrhh)
        ->postJson('/personas/buscar', ['documento' => '90000501'])
        ->assertOk()
        ->assertJson(['encontrada' => true, 'nombre' => 'Teresa Mahecha Ibáñez']);
});

it('J1 · error 1 · un documento ya registrado bloquea el alta', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));

    $this->actingAs($rrhh)
        ->post('/personas', altaValida($this, ['given_names' => 'Otra', 'family_names' => 'Persona']))
        ->assertSessionHasErrors('document_number');

    devuelveElContexto($this);

    // Y no se creó nada a medias: la transacción es todo o nada.
    expect(Person::where('family_names', 'Persona')->count())->toBe(0);
});

it('J1 · error 2 · una fecha de menor de edad bloquea, y el mensaje explica el porqué', function () {
    $rrhh = comoRol($this, 'admin_rrhh');

    $respuesta = $this->actingAs($rrhh)->post('/personas', altaValida($this, [
        'birth_date' => now()->subYears(15)->toDateString(),
    ]));

    $respuesta->assertSessionHasErrors('birth_date');

    // El texto importa tanto como el bloqueo: tiene que decir por qué y a dónde ir, no
    // dar un error técnico. Es lo que pide el recorrido J1.
    $mensaje = session('errors')->first('birth_date');

    expect($mensaje)->toContain('menores de edad')
        ->and($mensaje)->toContain('Matrícula');
});

it('J1 · error 3 · una relación solapada avisa pero no bloquea', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $persona = Person::where('family_names', 'Mahecha Ibáñez')->first();

    $revision = $this->actingAs($rrhh)->postJson('/personas/revisar', [
        'person_id' => (string) $persona->getKey(),
        'legal_entity_id' => (string) $this->entidad->getKey(),
        'valid_from' => now()->toDateString(),
    ])->json();

    // Avisa, con la fecha de corrección ya calculada…
    expect($revision['avisos'])->toHaveKey('valid_from')
        ->and($revision['avisos']['valid_from']['cerrarEl'])->toBe(now()->subDay()->toDateString())
        // …y no bloquea: un traslado dentro del mismo día es legítimo.
        ->and($revision['bloqueos'])->toBeEmpty();
});

it('J1 · error 4 · una sede de otra entidad jurídica bloquea', function () {
    $rrhh = comoRol($this, 'admin_rrhh');

    $otraOrganizacion = Organization::create(['name' => 'Otra fundación']);
    $otraEntidad = LegalEntity::create([
        'organization_id' => $otraOrganizacion->getKey(),
        'name' => 'Otra fundación S.A.S.',
    ]);
    $sedeAjena = Site::create(['legal_entity_id' => $otraEntidad->getKey(), 'name' => 'Sede ajena']);

    $this->actingAs($rrhh)
        ->post('/personas', altaValida($this, ['site_id' => (string) $sedeAjena->getKey()]))
        ->assertSessionHasErrors('site_id');
});

it('J1 · el rector no puede dar de alta: la decisión de B7 llega hasta la ruta', function () {
    $rector = comoRol($this, 'rector');

    $this->actingAs($rector)->get('/personas/nueva')->assertForbidden();
    $this->actingAs($rector)->post('/personas', altaValida($this))->assertForbidden();

    devuelveElContexto($this);

    expect(Person::count())->toBe(0);
});

// ══ J5 ══════════════════════════════════════════════════════════════════════

it('J5 · el impacto se calcula con las cifras de esa persona, no con una frase genérica', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $relacion = Relationship::first();

    $impacto = $this->actingAs($rrhh)
        ->getJson("/relaciones/{$relacion->getKey()}/impacto")
        ->assertOk()
        ->json();

    expect($impacto['persona'])->toBe('Teresa Mahecha Ibáñez')
        ->and($impacto['pierde'])->not->toBeEmpty()
        // La columna de lo que NO se pierde importa tanto como la otra: es lo que quita
        // el miedo a cerrar una relación que debe cerrarse.
        ->and(implode(' ', $impacto['conserva']))->toContain('se cierra, no se borra')
        ->and($impacto['motivos'])->toHaveKey('renuncia_voluntaria');
});

it('J5 · cerrar deja la relación cerrada, con fecha y sin borrar nada', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $relacion = Relationship::first();
    $ultimoDia = now()->toDateString();

    $this->actingAs($rrhh)
        ->post("/relaciones/{$relacion->getKey()}/cierre", [
            'motivo' => 'renuncia_voluntaria',
            'ultimo_dia' => $ultimoDia,
        ])
        ->assertRedirect("/personas/{$relacion->person_id}");

    devuelveElContexto($this);
    $relacion->refresh();

    expect($relacion->status)->toBe(RelationshipStatus::Ended)
        ->and($relacion->valid_to->toDateString())->toBe($ultimoDia)
        // Invariante 5: la fila sigue ahí. Es lo que hay que poder enseñar en una
        // inspección, y borrarla sería perder justo lo que el sistema conserva.
        ->and(Relationship::withoutGlobalScopes()->find($relacion->getKey()))->not->toBeNull()
        ->and(Person::find($relacion->person_id))->not->toBeNull()
        ->and(PersonIdentity::where('person_id', $relacion->person_id)->count())->toBe(1);
});

it('J5 · el cierre queda auditado con el responsable y el motivo', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $relacion = Relationship::first();

    $this->actingAs($rrhh)->post("/relaciones/{$relacion->getKey()}/cierre", [
        'motivo' => 'terminacion_contrato',
        'ultimo_dia' => now()->toDateString(),
    ]);

    devuelveElContexto($this);

    $evento = AuditEvent::where('action', 'relationship.ended')->latest('id')->first();

    expect($evento)->not->toBeNull()
        // Regla 5 de CLAUDE.md: una decisión de terminación siempre tiene detrás una
        // persona identificada, y queda escrita.
        ->and($evento->actor_label)->toBe($rrhh->email)
        ->and($evento->context['motivo'] ?? null)->toBe('terminacion_contrato');
});

it('J5 · un motivo fuera de la lista cerrada se rechaza', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $relacion = Relationship::first();

    $this->actingAs($rrhh)
        ->post("/relaciones/{$relacion->getKey()}/cierre", [
            'motivo' => 'porque_si',
            'ultimo_dia' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('motivo');

    devuelveElContexto($this);

    expect($relacion->fresh()->status)->toBe(RelationshipStatus::Active);
});

it('J5 · el rector no puede cerrar una relación', function () {
    $rrhh = comoRol($this, 'admin_rrhh');
    $this->actingAs($rrhh)->post('/personas', altaValida($this));
    devuelveElContexto($this);

    $relacion = Relationship::first();
    $rector = comoRol($this, 'rector');

    $this->actingAs($rector)
        ->post("/relaciones/{$relacion->getKey()}/cierre", [
            'motivo' => 'renuncia_voluntaria',
            'ultimo_dia' => now()->toDateString(),
        ])
        ->assertForbidden();

    devuelveElContexto($this);

    expect($relacion->fresh()->status)->toBe(RelationshipStatus::Active);
});
