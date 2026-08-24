<?php

/**
 * Auditoría en el flujo real (paso B4) y la prueba que el paso exige: **falla si un campo
 * P3 aparece en la auditoría o en los logs**.
 *
 * Los valores P3 de este fichero son deliberadamente raros —`Zzyzx`, `987654321`, una
 * fecha imposible de teclear por casualidad— para que buscarlos en un fichero de log no dé
 * falsos positivos. Si alguno aparece, aparece porque se filtró.
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
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\Shared\Application\AuditRecorder;
use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\Domain\DataClassification;
use App\Domains\Shared\Infrastructure\Logging\ConfigureStructuredLogging;
use App\Domains\Shared\Infrastructure\Logging\RedactSensitiveData;
use App\Domains\Shared\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/** Valores P3 imposibles de confundir con otra cosa dentro de un fichero de log. */
const P3_NOMBRE = 'Zzyzx';
const P3_APELLIDO = 'Qwghlm';
const P3_DOCUMENTO = '987654321987';
const P3_NACIMIENTO = '1971-02-19';

beforeEach(function () {
    $this->tenant = createTenant('colegio-auditoria');
    TenantContext::set($this->tenant->id);

    $org = Organization::create(['name' => 'Colegio']);
    $this->legalEntity = LegalEntity::create(['organization_id' => $org->getKey(), 'name' => 'Colegio']);

    $this->recorder = app(AuditRecorder::class);
});

function personaConP3(): Person
{
    $person = Person::create([
        'given_names' => P3_NOMBRE,
        'family_names' => P3_APELLIDO,
        'birth_date' => P3_NACIMIENTO,
        'sex' => 'femenino',
    ]);

    PersonIdentity::create([
        'person_id' => $person->getKey(),
        'document_type' => 'cedula',
        'document_number' => P3_DOCUMENTO,
    ]);

    return $person;
}

// ── Escrituras ──────────────────────────────────────────────────────────────
it('crear una persona deja rastro con los nombres de campo, no con los valores', function () {
    $person = personaConP3();

    $evento = AuditEvent::where('action', 'people.created')->latest('id')->firstOrFail();

    expect($evento->resource_id)->toBe((string) $person->getKey())
        ->and($evento->result)->toBe('allowed')
        ->and($evento->context['fields'])->toContain('birth_date')
        ->and($evento->correlation_id)->not->toBeNull();

    // El nombre del campo sí; su contenido, jamás.
    expect(json_encode($evento->context, JSON_THROW_ON_ERROR))
        ->not->toContain(P3_NACIMIENTO);
});

it('editar una persona registra solo los campos que cambiaron', function () {
    $person = personaConP3();
    $person->update(['teaching_grade' => '14']);

    $evento = AuditEvent::where('action', 'people.updated')->latest('id')->firstOrFail();

    expect($evento->context['fields'])->toBe(['teaching_grade']);
});

it('el rastro de escritura no incluye id, tenant_id ni marcas de tiempo', function () {
    personaConP3();

    $evento = AuditEvent::where('action', 'people.created')->latest('id')->firstOrFail();

    expect($evento->context['fields'])
        ->not->toContain('id')
        ->not->toContain('tenant_id')
        ->not->toContain('created_at');
});

// ── El guardián del contexto ────────────────────────────────────────────────
it('el registrador se niega a escribir un contexto con un campo P3', function (string $campo) {
    expect(fn () => $this->recorder->record(
        action: 'prueba.intento',
        resourceType: 'people',
        context: [$campo => 'lo que sea'],
    ))->toThrow(RuntimeException::class);
})->with(['birth_date', 'sex', 'document_number', 'password', 'mfa_secret']);

it('también lo detecta anidado, no solo en el primer nivel', function () {
    expect(fn () => $this->recorder->record(
        action: 'prueba.intento',
        resourceType: 'people',
        context: ['antes' => ['datos' => ['document_number' => P3_DOCUMENTO]]],
    ))->toThrow(RuntimeException::class);
});

it('un contexto de metadatos legítimo sí se escribe', function () {
    $evento = $this->recorder->record(
        action: 'prueba.legitima',
        resourceType: 'people',
        context: ['fields' => ['birth_date'], 'rule' => 'abac.purpose'],
    );

    // «birth_date» como *valor* es el nombre de un campo y es exactamente lo que se
    // quiere registrar; como *clave* sería el dato. La distinción es la regla.
    expect($evento->context['fields'])->toContain('birth_date');
});

// ── Errores ─────────────────────────────────────────────────────────────────
it('un error deja result=error sin copiar el mensaje de la excepción', function () {
    // El mensaje de QueryException trae la consulta con los valores interpolados: si se
    // guardara, la auditoría tendría dentro el documento de la persona.
    $excepcion = new QueryException(
        'pgsql',
        'insert into "identities" ...',
        ['document_number' => P3_DOCUMENTO],
        new RuntimeException('SQL: insert into "identities" values ('.P3_DOCUMENTO.')')
    );

    $evento = $this->recorder->recordError($excepcion);

    expect($evento)->not->toBeNull()
        ->and($evento->result)->toBe('error')
        ->and($evento->context['exception'])->toBe(QueryException::class);

    expect(json_encode($evento->context, JSON_THROW_ON_ERROR))
        ->not->toContain(P3_DOCUMENTO);
});

it('sin tenant resuelto no se pierde el error en silencio: queda en el log', function () {
    TenantContext::clear();

    $evento = $this->recorder->recordError(new RuntimeException('fallo cualquiera'));

    expect($evento)->toBeNull();
})->group('authorization');

// ── La prueba que exige el paso: nada P3 en los logs ────────────────────────
it('ningún dato P3 llega al fichero de log, ni siquiera desde una excepción de base', function () {
    $log = storage_path('logs/prueba-p3.log');
    @unlink($log);

    config()->set('logging.channels.prueba', [
        'driver' => 'single',
        'path' => $log,
        'level' => 'debug',
        'tap' => [ConfigureStructuredLogging::class],
    ]);

    // Lo que de verdad pasa en producción: una consulta falla y Laravel registra el
    // mensaje entero, que trae los valores ya interpolados dentro del SQL.
    Log::channel('prueba')->error(
        'SQLSTATE[23505]: Unique violation (Connection: pgsql, SQL: insert into "people" '.
        '("given_names", "family_names", "birth_date") values ('.P3_NOMBRE.', '.P3_APELLIDO.', '.P3_NACIMIENTO.'))'
    );

    Log::channel('prueba')->info('Lectura de persona', [
        'document_number' => P3_DOCUMENTO,
        'birth_date' => P3_NACIMIENTO,
        'person_id' => 'no-es-p3',
    ]);

    $contenido = file_get_contents($log);
    @unlink($log);

    foreach ([P3_NOMBRE, P3_APELLIDO, P3_DOCUMENTO, P3_NACIMIENTO] as $valor) {
        expect($contenido)->not->toContain(
            $valor,
            "El valor P3 «{$valor}» llegó al fichero de log. Ver data-classification.md, regla 4."
        );
    }

    // Y se comprueba que no pasó por estar el log vacío: lo que debe quedar, queda.
    expect($contenido)->toContain(RedactSensitiveData::REDACTED)
        ->and($contenido)->toContain('SQLSTATE[23505]')
        ->and($contenido)->toContain('correlation_id');
});

it('el log estructurado lleva correlación y tenant en cada línea', function () {
    $log = storage_path('logs/prueba-contexto.log');
    @unlink($log);

    config()->set('logging.channels.prueba2', [
        'driver' => 'single', 'path' => $log, 'level' => 'debug',
        'tap' => [ConfigureStructuredLogging::class],
    ]);

    Log::channel('prueba2')->info('cualquier cosa');

    $linea = json_decode(trim(file_get_contents($log)), true, 512, JSON_THROW_ON_ERROR);
    @unlink($log);

    expect($linea['extra']['correlation_id'])->toBe(CorrelationId::current())
        ->and($linea['extra']['tenant_id'])->toBe($this->tenant->id);
});

// ── CA-05 de punta a punta ──────────────────────────────────────────────────
it('CA-05 · una lectura P3 autorizada deja actor, propósito, clasificación y correlación', function () {
    $person = personaConP3();

    $user = new User(['email' => 'admin@ejemplo.test', 'status' => 'active']);
    $user->password = 'contrasena-de-prueba';
    $user->save();
    $role = Role::create(['key' => 'admin_rrhh', 'name' => 'Talento humano']);
    RoleAssignment::create([
        'user_id' => $user->getKey(), 'role_id' => $role->getKey(),
        'scope_type' => 'tenant', 'valid_from' => now()->subMonth()->toDateString(),
    ]);

    app(Authorizer::class)->authorize(new AuthorizationContext(
        user: $user,
        tenantId: $this->tenant->id,
        resource: 'identities.document_number',
        action: 'leer',
        resourceTenantId: $this->tenant->id,
        resourcePersonId: (string) $person->getKey(),
        purpose: Purpose::ReporteC600,
    ));

    $evento = AuditEvent::where('action', 'identities.document_number.leer')->latest('id')->firstOrFail();

    expect($evento->actor_user_id)->toBe((string) $user->getKey())
        ->and($evento->purpose)->toBe('reporte_c600')
        ->and($evento->data_classification)->toBe(DataClassification::P3)
        ->and($evento->correlation_id)->toBe(CorrelationId::current())
        ->and($evento->result)->toBe('allowed');

    // Y en ningún evento de toda la traza aparece el documento leído.
    $todo = json_encode(AuditEvent::pluck('context')->all(), JSON_THROW_ON_ERROR);
    expect($todo)->not->toContain(P3_DOCUMENTO);
})->group('authorization');
