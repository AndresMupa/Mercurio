<?php

/**
 * Una prueba por cada celda de la matriz de `docs/architecture/permissions.md`,
 * denegaciones incluidas.
 *
 * **La tabla de abajo está transcrita del documento a mano y a propósito.** Si se
 * generara desde `PermissionMatrix`, se compararía la implementación consigo misma y
 * pasaría siempre. Escrita aparte, una discrepancia entre el documento y el código sale
 * aquí, que es justo lo que hace falta que salga.
 *
 * Cada caso ejercita el `Authorizer` de verdad: usuario real, asignación de rol real,
 * base de datos real y las seis condiciones ABAC. No se prueba la tabla: se prueba la
 * decisión.
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
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\Shared\Domain\DataClassification;
use App\Domains\Shared\TenantContext;

/*
 * Leyenda del documento:
 *   'si'    ✓  permitido
 *   'p'     ✓ᵖ permitido con propósito declarado
 *   's'     ✓ˢ solo sobre sí mismo
 *   'no'    —  denegado
 */
const ROLES = ['owner', 'admin_rrhh', 'responsable_sst', 'rector', 'coordinador',
    'jefe_area', 'trabajador', 'auditor', 'soporte_plataforma'];

/** @return array<string, array{0:string,1:string,2:string,3:array<string,string>}> */
function celdasDeLaMatriz(): array
{
    $fila = fn (array $v) => array_combine(ROLES, $v);

    return [
        // recurso, acción, clasificación, [rol => esperado]
        'people · listar' => ['people', 'listar', DataClassification::P2,
            $fila(['si', 'si', 'si', 'si', 'si', 'si', 's', 'si', 'no'])],

        'people.birth_date · leer' => ['people.birth_date', 'leer', DataClassification::P3,
            $fila(['p', 'p', 'p', 'p', 'no', 'no', 's', 'no', 'no'])],

        'people.sex · leer' => ['people.sex', 'leer', DataClassification::P3,
            $fila(['p', 'p', 'p', 'p', 'no', 'no', 's', 'no', 'no'])],

        'identities.document_number · leer' => ['identities.document_number', 'leer', DataClassification::P3,
            $fila(['p', 'p', 'p', 'p', 'no', 'no', 's', 'no', 'no'])],

        'people · crear' => ['people', 'crear', DataClassification::P2,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'people · editar' => ['people', 'editar', DataClassification::P2,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'relationships · crear' => ['relationships', 'crear', DataClassification::P2,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'relationships · cerrar' => ['relationships', 'cerrar', DataClassification::P2,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'relationships · listar' => ['relationships', 'listar', DataClassification::P2,
            $fila(['si', 'si', 'si', 'si', 'si', 'si', 's', 'si', 'no'])],

        'assignments · gestionar' => ['assignments', 'gestionar', DataClassification::P2,
            $fila(['si', 'si', 'no', 'no', 'si', 'no', 'no', 'no', 'no'])],

        'positions · gestionar' => ['positions', 'gestionar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'sites · gestionar' => ['sites', 'gestionar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'legal_entities · gestionar' => ['legal_entities', 'gestionar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'enrollment_snapshots · leer' => ['enrollment_snapshots', 'leer', DataClassification::P1,
            $fila(['si', 'si', 'si', 'si', 'si', 'no', 'no', 'si', 'si'])],

        'enrollment_snapshots · cargar' => ['enrollment_snapshots', 'cargar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'no', 'si', 'no', 'no', 'no', 'no'])],

        'enrollment_snapshots · editar' => ['enrollment_snapshots', 'editar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'no', 'si', 'no', 'no', 'no', 'no'])],

        'reports.c600 · generar' => ['reports.c600', 'generar', DataClassification::P1,
            $fila(['si', 'si', 'no', 'si', 'si', 'no', 'no', 'no', 'no'])],

        'reports.evi · generar' => ['reports.evi', 'generar', DataClassification::P1,
            $fila(['si', 'si', 'si', 'si', 'no', 'no', 'no', 'no', 'no'])],

        'reports.c600 · exportar' => ['reports.c600', 'exportar', DataClassification::P1,
            $fila(['si', 'si', 'si', 'si', 'no', 'no', 'no', 'si', 'no'])],

        'reports.evi · exportar' => ['reports.evi', 'exportar', DataClassification::P1,
            $fila(['si', 'si', 'si', 'si', 'no', 'no', 'no', 'si', 'no'])],

        'users · gestionar' => ['users', 'gestionar', DataClassification::P2,
            $fila(['si', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'role_assignments · gestionar' => ['role_assignments', 'gestionar', DataClassification::P1,
            $fila(['si', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'audit_events · leer' => ['audit_events', 'leer', DataClassification::P2,
            $fila(['si', 'no', 'no', 'si', 'no', 'no', 'no', 'si', 'no'])],

        'audit_events · modificar' => ['audit_events', 'modificar', DataClassification::P2,
            $fila(['no', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'audit_events · borrar' => ['audit_events', 'borrar', DataClassification::P2,
            $fila(['no', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'tenant.settings · gestionar' => ['tenant.settings', 'gestionar', DataClassification::P1,
            $fila(['si', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],

        'dpa · gestionar' => ['dpa', 'gestionar', DataClassification::P1,
            $fila(['si', 'no', 'no', 'no', 'no', 'no', 'no', 'no', 'no'])],
    ];
}

/** @return list<array{0:string,1:string,2:string,3:string,4:string}> */
function todasLasCeldas(): array
{
    $casos = [];

    foreach (celdasDeLaMatriz() as $etiqueta => [$recurso, $accion, $clasificacion, $porRol]) {
        foreach ($porRol as $rol => $esperado) {
            $casos["{$etiqueta} · {$rol}"] = [$recurso, $accion, $clasificacion, $rol, $esperado];
        }
    }

    return $casos;
}

/** Deja a la persona del test con una relación laboral vigente. */
function relacionVigenteDe(object $test): Relationship
{
    $relacion = Relationship::create([
        'person_id' => $test->person->getKey(),
        'legal_entity_id' => $test->legalEntity->getKey(),
        'type' => 'empleado',
        'valid_from' => now()->subMonth()->toDateString(),
        'status' => RelationshipStatus::Active,
    ]);

    return $relacion;
}

beforeEach(function () {
    $this->tenant = createTenant('colegio-matriz');
    TenantContext::set($this->tenant->id);

    $org = Organization::create(['name' => 'Colegio']);
    $this->legalEntity = LegalEntity::create(['organization_id' => $org->getKey(), 'name' => 'Colegio']);

    $this->person = Person::create([
        'given_names' => 'Ana',
        'family_names' => 'Docente',
        'birth_date' => now()->subYears(40)->toDateString(),
    ]);

    $this->authorizer = app(Authorizer::class);
});

/** Crea un usuario con el rol pedido y el alcance que la matriz le asigna. */
function usuarioCon(string $rolKey, object $test, bool $propiaPersona = false, ?string $validTo = null): User
{
    $user = new User([
        'email' => $rolKey.'@ejemplo.test',
        'person_id' => $propiaPersona ? $test->person->getKey() : null,
        'status' => 'active',
    ]);
    $user->password = 'contrasena-de-prueba';
    $user->save();

    // CA-07: quien tiene persona asociada necesita relación laboral vigente para que
    // el acceso exista siquiera. Sin esto, las celdas ✓ˢ se denegarían por falta de
    // relación y no por lo que la matriz dice.
    if ($propiaPersona) {
        relacionVigenteDe($test);
    }

    $role = Role::firstOrCreate(['key' => $rolKey], ['name' => $rolKey]);

    // `soporte_plataforma` exige vencimiento explícito (ABAC 6); el resto no.
    $vence = $validTo ?? ($rolKey === 'soporte_plataforma' ? now()->addWeek()->toDateString() : null);

    RoleAssignment::create([
        'user_id' => $user->getKey(),
        'role_id' => $role->getKey(),
        'scope_type' => $rolKey === 'trabajador' ? 'self' : 'tenant',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => $vence,
    ]);

    return $user;
}

it('celda de la matriz', function (string $recurso, string $accion, string $clasificacion, string $rol, string $esperado) {
    // ✓ˢ se prueba sobre el propio titular; el resto, sobre una persona ajena.
    $sobreSiMismo = $esperado === 's';
    $user = usuarioCon($rol, $this, propiaPersona: $sobreSiMismo);

    // ✓ᵖ y ✓ˢ sobre P3 exigen propósito: se declara uno válido para probar el camino
    // permitido. Que sin él se deniegue lo comprueban las pruebas de CA-04.
    $necesitaProposito = in_array($esperado, ['p', 's'], true)
        && $clasificacion === DataClassification::P3;

    $decision = $this->authorizer->authorize(new AuthorizationContext(
        user: $user,
        tenantId: $this->tenant->id,
        resource: $recurso,
        action: $accion,
        resourceTenantId: $this->tenant->id,
        // Todo recurso que trate de una persona declara de quién: es lo que hace
        // comprobable la celda ✓ˢ, también cuando la acción es listar.
        resourcePersonId: in_array($recurso, ['people', 'relationships'], true)
            || str_starts_with($recurso, 'people.')
            || str_starts_with($recurso, 'identities.')
                ? (string) $this->person->getKey()
                : null,
        purpose: $necesitaProposito ? Purpose::GestionLaboral : null,
        dataClassification: $clasificacion,
    ));

    $deberiaPermitir = $esperado !== 'no';

    expect($decision->allowed)->toBe(
        $deberiaPermitir,
        sprintf(
            'La matriz dice «%s» para %s·%s con rol %s, y el Authorizer respondió %s. Motivo: %s',
            $esperado, $recurso, $accion, $rol,
            $decision->allowed ? 'permitido' : 'denegado',
            $decision->reason,
        )
    );
})->with(todasLasCeldas())->group('authorization');
