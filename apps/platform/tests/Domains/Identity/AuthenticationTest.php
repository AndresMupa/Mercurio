<?php

/**
 * Autenticación, MFA y resolución de tenant (paso B5). Criterios CA-07, CA-08 y CA-09.
 */

use App\Domains\Identity\Application\LoginAttempt;
use App\Domains\Identity\Application\LoginResult;
use App\Domains\Identity\Application\MfaRequirement;
use App\Domains\Identity\Application\TenantResolver;
use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\Tenant;
use App\Domains\Identity\Domain\Totp;
use App\Domains\Identity\Domain\User;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

const CONTRASENA = 'una-contrasena-larga-de-prueba';

beforeEach(function () {
    $this->tenant = createTenant('colegio-acceso');
    TenantContext::set($this->tenant->id);

    $this->login = app(LoginAttempt::class);
    $this->totp = app(Totp::class);
    RateLimiter::clear('*');
});

/** Usuario con rol, contraseña conocida y MFA opcional. */
function usuario(object $t, string $rolKey, array $opciones = []): User
{
    $user = new User([
        'email' => ($opciones['email'] ?? $rolKey.'-'.bin2hex(random_bytes(3))).'@ejemplo.test',
        'status' => $opciones['status'] ?? 'active',
        'mfa_enabled' => $opciones['mfa'] ?? false,
    ]);
    $user->password = CONTRASENA;

    if ($opciones['mfa'] ?? false) {
        $user->mfa_secret = $opciones['secreto'] ?? Totp::generateSecret();
    }

    $user->save();

    $role = Role::firstOrCreate(['key' => $rolKey], ['name' => $rolKey]);

    RoleAssignment::create([
        'user_id' => $user->getKey(),
        'role_id' => $role->getKey(),
        'scope_type' => 'tenant',
        'valid_from' => $opciones['desde'] ?? now()->subMonth()->toDateString(),
        'valid_to' => $opciones['hasta'] ?? null,
    ]);

    return $user;
}

// ── Resolución del tenant antes de autenticar ───────────────────────────────
it('resuelve el tenant por slug sin que haya sesión ni contexto', function () {
    TenantContext::clear();

    expect(app(TenantResolver::class)->bySlug('colegio-acceso'))->toBe($this->tenant->id);
})->group('tenant-isolation');

it('el resolver no sirve para enumerar la lista de clientes', function () {
    $otro = createTenant('colegio-vecino');
    TenantContext::clear();

    $resolver = app(TenantResolver::class);

    // Resuelve el que se le pide por nombre exacto…
    expect($resolver->bySlug('colegio-vecino'))->toBe($otro->id);

    // …y nada más: ni comodines, ni prefijos, ni la tabla entera.
    expect($resolver->bySlug('%'))->toBeNull()
        ->and($resolver->bySlug('colegio'))->toBeNull()
        ->and($resolver->bySlug('colegio%'))->toBeNull()
        ->and(DB::table('tenants')->count())->toBe(0);
})->group('tenant-isolation');

it('un tenant suspendido no permite entrar', function () {
    Tenant::whereKey($this->tenant->id)->update(['status' => 'suspended']);
    TenantContext::clear();

    expect(app(TenantResolver::class)->bySlug('colegio-acceso'))->toBeNull();
})->group('tenant-isolation');

it('saca el slug del subdominio o de la cabecera explícita', function () {
    $resolver = app(TenantResolver::class);

    expect($resolver->slugFromRequest(Request::create('https://colegio.plataforma.co/entrar')))->toBe('colegio')
        ->and($resolver->slugFromRequest(Request::create('https://plataforma.co/entrar')))->toBeNull();

    $conCabecera = Request::create('https://plataforma.co/entrar');
    $conCabecera->headers->set('X-Tenant', 'colegio-acceso');

    expect($resolver->slugFromRequest($conCabecera))->toBe('colegio-acceso');
});

// ── No enumeración de usuarios ──────────────────────────────────────────────
it('un correo inexistente y una contraseña incorrecta dan la misma respuesta', function () {
    $existente = usuario($this, 'coordinador');

    $inexistente = $this->login->attempt($this->tenant->id, 'nadie@ejemplo.test', CONTRASENA);
    $malaClave = $this->login->attempt($this->tenant->id, $existente->email, 'otra-cosa');

    expect($inexistente)->toBe(LoginResult::Rejected)
        ->and($malaClave)->toBe(LoginResult::Rejected)
        ->and($inexistente->publicMessage())->toBe($malaClave->publicMessage());
});

it('pero la auditoría sí distingue los dos casos', function () {
    $existente = usuario($this, 'coordinador');

    $this->login->attempt($this->tenant->id, 'nadie@ejemplo.test', CONTRASENA);
    $this->login->attempt($this->tenant->id, $existente->email, 'otra-cosa');

    $reglas = AuditEvent::where('action', 'auth.login.failed')->orderBy('id')->pluck('context');

    expect($reglas[0]['rule'])->toBe('usuario_inexistente')
        ->and($reglas[1]['rule'])->toBe('contrasena_incorrecta');
});

it('la auditoría de un intento fallido no guarda el correo probado', function () {
    $this->login->attempt($this->tenant->id, 'sospechoso@ejemplo.test', 'lo-que-sea');

    $todo = json_encode(AuditEvent::pluck('context')->all(), JSON_THROW_ON_ERROR);

    expect($todo)->not->toContain('sospechoso@ejemplo.test');
});

it('el intento fallido queda auditado, que es lo que B4 no podía hacer', function () {
    // Antes de B5 no había tenant resuelto en el momento del login, así que la RLS
    // rechazaba el INSERT y el suceso se perdía. Ahora se resuelve antes.
    $this->login->attempt($this->tenant->id, 'nadie@ejemplo.test', 'lo-que-sea');

    $evento = AuditEvent::where('action', 'auth.login.failed')->latest('id')->first();

    expect($evento)->not->toBeNull()
        ->and($evento->result)->toBe('denied')
        ->and($evento->correlation_id)->not->toBeNull();
});

// ── Límite de intentos ──────────────────────────────────────────────────────
it('corta tras varios intentos fallidos seguidos', function () {
    $user = usuario($this, 'coordinador');

    for ($i = 0; $i < 5; $i++) {
        expect($this->login->attempt($this->tenant->id, $user->email, 'incorrecta'))
            ->toBe(LoginResult::Rejected);
    }

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::Throttled);
});

it('el límite es por tenant, correo e IP a la vez', function () {
    $user = usuario($this, 'coordinador');

    for ($i = 0; $i < 5; $i++) {
        $this->login->attempt($this->tenant->id, $user->email, 'incorrecta', ip: '10.0.0.1');
    }

    // Limitar solo por IP dejaría pasar el ataque distribuido; solo por correo permitiría
    // bloquear a alguien a propósito desde fuera. Otra IP sigue teniendo sus intentos.
    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA, ip: '10.0.0.2'))
        ->toBe(LoginResult::Authenticated);
});

// ── CA-09 · MFA obligatorio para techo P3 ───────────────────────────────────
it('CA-09 · un rol con techo P3 no entra sin segundo factor', function (string $rol) {
    $secreto = Totp::generateSecret();
    $user = usuario($this, $rol, ['mfa' => true, 'secreto' => $secreto]);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::MfaRequired);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA, $this->totp->at($secreto)))
        ->toBe(LoginResult::Authenticated);
})->with(['owner', 'admin_rrhh', 'responsable_sst', 'trabajador']);

it('CA-09 · un rol con techo P2 entra sin segundo factor', function (string $rol) {
    $user = usuario($this, $rol);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::Authenticated);
})->with(['coordinador', 'jefe_area', 'auditor']);

it('CA-09 · un rol P3 sin MFA configurado no entra «mientras tanto»', function () {
    // Dejar pasar hasta que lo configure sería justo el agujero que el segundo factor tapa.
    $user = usuario($this, 'admin_rrhh', ['mfa' => false]);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::MfaEnrollmentRequired);
});

it('CA-09 · un código de segundo factor incorrecto no entra', function () {
    $user = usuario($this, 'admin_rrhh', ['mfa' => true]);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA, '000000'))
        ->toBe(LoginResult::Rejected);
});

it('CA-09 · quién necesita MFA se deduce del techo del rol, no de una lista aparte', function () {
    $requisito = app(MfaRequirement::class);

    expect($requisito->isRequiredFor(usuario($this, 'owner')))->toBeTrue()
        ->and($requisito->isRequiredFor(usuario($this, 'rector')))->toBeTrue()
        ->and($requisito->isRequiredFor(usuario($this, 'coordinador')))->toBeFalse();
});

// ── CA-08 · vigencia ────────────────────────────────────────────────────────
it('CA-08 · un rol P3 vencido deja de exigir MFA porque deja de otorgar nada', function () {
    $user = usuario($this, 'admin_rrhh', [
        'mfa' => false,
        'desde' => now()->subYear()->toDateString(),
        'hasta' => now()->subDay()->toDateString(),
    ]);

    // El rol sigue asignado, pero vencido: ni otorga permisos ni puede ser el motivo
    // por el que se exige un segundo factor.
    expect(app(MfaRequirement::class)->isRequiredFor($user))->toBeFalse();
});

// ── Sesión y estado ─────────────────────────────────────────────────────────
it('un usuario inactivo no entra aunque la contraseña sea correcta', function () {
    $user = usuario($this, 'coordinador', ['status' => 'suspended']);

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::Rejected);
});

it('un acceso correcto deja marca de última entrada y su evento', function () {
    $user = usuario($this, 'coordinador');

    expect($this->login->attempt($this->tenant->id, $user->email, CONTRASENA))
        ->toBe(LoginResult::Authenticated)
        ->and($user->fresh()->last_login_at)->not->toBeNull();

    $evento = AuditEvent::where('action', 'auth.login.succeeded')->latest('id')->firstOrFail();

    expect($evento->result)->toBe('allowed')
        ->and($evento->actor_user_id)->toBe((string) $user->getKey());
});

it('la contraseña nunca se serializa', function () {
    $user = usuario($this, 'coordinador');

    $serializado = json_encode($user->toArray(), JSON_THROW_ON_ERROR);

    expect($serializado)->not->toContain('password')
        ->and($serializado)->not->toContain('mfa_secret')
        ->and($serializado)->not->toContain(CONTRASENA);
});
