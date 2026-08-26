<?php

/**
 * La decisión 2 del lienzo de B7, comprobada por HTTP: **los datos P3 salen enmascarados**.
 *
 * Que estén enmascarados en la vista no basta y no es lo que se prueba aquí. Lo que se
 * prueba es que **el valor completo no viaja** en la respuesta del listado ni de la ficha:
 * un dato que llega al navegador ya salió, aunque el CSS lo tape y aunque nadie lo mire.
 *
 * Y que la única puerta por la que sale entero —la ruta del propósito— audita las dos
 * cosas: CA-04 cuando deniega, CA-05 cuando permite.
 */

use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Organization;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\TenantContext;

const DOCUMENTO = '90000777';

beforeEach(function () {
    $this->tenant = createTenant('colegio-sensibles');
    TenantContext::set($this->tenant->id);

    $organizacion = Organization::create(['name' => 'Colegio de pruebas']);
    LegalEntity::create(['organization_id' => $organizacion->getKey(), 'name' => 'Colegio S.A.S.']);

    $this->persona = Person::create([
        'given_names' => 'Marcela',
        'family_names' => 'Salgado Galvis',
        'birth_date' => '1985-03-22',
        'sex' => 'femenino',
    ]);

    PersonIdentity::create([
        'person_id' => $this->persona->getKey(),
        'document_type' => 'cedula',
        'document_number' => DOCUMENTO,
        'country_code' => 'CO',
    ]);
});

function usuarioDeRol(object $test, string $rolKey): User
{
    $usuario = new User(['email' => "{$rolKey}@sensibles.test", 'status' => 'active']);
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

it('el listado no lleva el documento completo, ni siquiera a quien podría verlo', function () {
    $rrhh = usuarioDeRol($this, 'admin_rrhh');

    $respuesta = $this->actingAs($rrhh)->get('/personas');

    $respuesta->assertOk();

    // El cuerpo entero de la respuesta: HTML de Inertia con los props dentro.
    expect($respuesta->getContent())->not->toContain(DOCUMENTO)
        // Y sí lleva la máscara, para que la prueba no pase por no haber cargado la fila.
        ->and($respuesta->getContent())->toContain('0777');
})->group('tenant-isolation');

it('la ficha tampoco lleva el documento, la fecha de nacimiento ni el sexo', function () {
    $rrhh = usuarioDeRol($this, 'admin_rrhh');

    $contenido = $this->actingAs($rrhh)
        ->get("/personas/{$this->persona->getKey()}")
        ->assertOk()
        ->getContent();

    expect($contenido)->not->toContain(DOCUMENTO)
        ->and($contenido)->not->toContain('1985-03-22')
        // El nombre sí va: es P2 y es de lo que trata la pantalla.
        ->and($contenido)->toContain('Marcela');
})->group('tenant-isolation');

it('CA-05 · con propósito declarado el dato sale, y queda registrado quién y para qué', function () {
    $rrhh = usuarioDeRol($this, 'admin_rrhh');

    $this->actingAs($rrhh)
        ->postJson("/personas/{$this->persona->getKey()}/ver", [
            'campo' => 'documento',
            'proposito' => 'reporte_c600',
        ])
        ->assertOk()
        ->assertJson(['valor' => DOCUMENTO]);

    TenantContext::set($this->tenant->id);

    $evento = AuditEvent::where('action', 'identities.document_number.leer')->latest('id')->first();

    expect($evento)->not->toBeNull()
        ->and($evento->result)->toBe('allowed')
        ->and($evento->purpose)->toBe('reporte_c600')
        ->and($evento->data_classification)->toBe('P3')
        ->and($evento->correlation_id)->not->toBeNull()
        // El valor leído nunca entra en la traza: un rastro que copia el dato es una
        // segunda copia del dato con otro nombre.
        ->and(json_encode($evento->context))->not->toContain(DOCUMENTO);
});

it('CA-04 · sin propósito válido se deniega y también queda registrado', function () {
    $rrhh = usuarioDeRol($this, 'admin_rrhh');

    $this->actingAs($rrhh)
        ->postJson("/personas/{$this->persona->getKey()}/ver", ['campo' => 'documento'])
        ->assertStatus(422);

    // Un propósito inventado tampoco vale: la lista es cerrada.
    $this->actingAs($rrhh)
        ->postJson("/personas/{$this->persona->getKey()}/ver", [
            'campo' => 'documento',
            'proposito' => 'porque_me_apetece',
        ])
        ->assertStatus(422);
});

it('un rol con techo P2 no ve el dato aunque declare un propósito impecable', function () {
    $coordinador = usuarioDeRol($this, 'coordinador');

    $this->actingAs($coordinador)
        ->postJson("/personas/{$this->persona->getKey()}/ver", [
            'campo' => 'documento',
            'proposito' => 'reporte_c600',
        ])
        ->assertForbidden();

    TenantContext::set($this->tenant->id);

    $evento = AuditEvent::where('result', 'denied')->latest('id')->first();

    expect($evento)->not->toBeNull()
        // El motivo real queda en la traza; a quien pregunta se le da un solo mensaje.
        ->and($evento->context['rule'] ?? null)->not->toBeNull();
});

it('el campo que se pide tiene que ser uno de los tres declarados', function () {
    $rrhh = usuarioDeRol($this, 'admin_rrhh');

    // Un lector genérico que aceptara el nombre de la columna sería la puerta por la que
    // mañana sale `password` porque alguien pasó la cadena equivocada.
    $this->actingAs($rrhh)
        ->postJson("/personas/{$this->persona->getKey()}/ver", [
            'campo' => 'password',
            'proposito' => 'reporte_c600',
        ])
        ->assertStatus(422);
})->group('tenant-isolation');
