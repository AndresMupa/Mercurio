<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Identity\Domain\Totp;
use App\Domains\Identity\Domain\User;
use App\Domains\Shared\Application\AuditRecorder;
use App\Domains\Shared\Domain\DataClassification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Un intento de acceso (paso B5).
 *
 * Todo el flujo devuelve **el mismo fallo** —`Result::Rechazado`— sin decir en qué punto
 * falló. Distinguir «ese correo no existe» de «la contraseña no coincide» convierte el
 * formulario de acceso en un directorio del personal del colegio: quien pruebe correos
 * sabrá cuáles pertenecen a alguien de la organización. Por eso el motivo real solo va a
 * `audit_events`, que sí puede leerlo quien tenga permiso.
 *
 * Esa uniformidad tiene que ser también **temporal**: si el caso «usuario inexistente»
 * volviera antes por no ejecutar el hash, el reloj delataría lo que el mensaje calla. Por
 * eso se verifica siempre contra un hash señuelo.
 */
final class LoginAttempt
{
    private static ?string $decoy = null;

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly MfaRequirement $mfa,
        private readonly AuditRecorder $recorder,
        private readonly Totp $totp,
    ) {}

    public function attempt(
        string $tenantId,
        string $email,
        string $password,
        ?string $mfaCode = null,
        string $ip = '0.0.0.0',
    ): LoginResult {
        $throttleKey = $this->throttleKey($tenantId, $email, $ip);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->audit($email, 'auth.login.throttled', null, [
                'rule' => 'rate_limit',
                'available_in' => RateLimiter::availableIn($throttleKey),
            ]);

            return LoginResult::Throttled;
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        $user = User::where('email', $email)->where('status', 'active')->first();

        // Se verifica siempre, exista el usuario o no: el coste en tiempo tiene que ser
        // el mismo, o el reloj revela lo que la respuesta calla.
        $passwordOk = Hash::check($password, $user?->password ?? $this->decoyHash());

        if ($user === null || ! $passwordOk) {
            $this->audit($email, 'auth.login.failed', $user, [
                'rule' => $user === null ? 'usuario_inexistente' : 'contrasena_incorrecta',
            ]);

            return LoginResult::Rejected;
        }

        // ── CA-09 · segundo factor obligatorio para techo P3 ────────────────
        if ($this->mfa->isRequiredFor($user)) {
            if (! $user->mfa_enabled || $user->mfa_secret === null) {
                // No se deja entrar «hasta que lo configure»: sería exactamente el
                // agujero que el segundo factor viene a tapar.
                $this->audit($email, 'auth.login.mfa_required', $user, ['rule' => 'mfa_no_configurado']);

                return LoginResult::MfaEnrollmentRequired;
            }

            if ($mfaCode === null) {
                $this->audit($email, 'auth.login.mfa_challenge', $user, ['rule' => 'mfa_pendiente']);

                return LoginResult::MfaRequired;
            }

            if (! $this->totp->verify($user->mfa_secret, $mfaCode)) {
                $this->audit($email, 'auth.login.failed', $user, ['rule' => 'mfa_incorrecto']);

                return LoginResult::Rejected;
            }
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit($email, 'auth.login.succeeded', $user, [
            'rule' => 'ok',
            'mfa' => $this->mfa->isRequiredFor($user) ? 'verificado' : 'no_exigido',
        ]);

        return LoginResult::Authenticated;
    }

    public function authenticatedUser(string $email): ?User
    {
        return User::where('email', $email)->where('status', 'active')->first();
    }

    /**
     * Hash señuelo contra el que verificar cuando el usuario no existe.
     *
     * Se genera con el driver configurado y no se escribe a mano: un literal inventado no
     * es bcrypt válido y `Hash::check` lanza, que fue exactamente lo que pasó al escribir
     * esto. Además, así sigue siendo válido si mañana se cambia a argon2.
     *
     * Se calcula una vez por proceso: el coste de comprobarlo es lo que tiene que
     * igualarse, no el de crearlo.
     */
    private function decoyHash(): string
    {
        return self::$decoy ??= Hash::make(bin2hex(random_bytes(32)));
    }

    private function throttleKey(string $tenantId, string $email, string $ip): string
    {
        // Por tenant, correo e IP a la vez: limitar solo por IP deja pasar el ataque
        // distribuido, y limitar solo por correo permite bloquear a alguien a propósito.
        return 'login:'.sha1($tenantId.'|'.mb_strtolower($email).'|'.$ip);
    }

    /**
     * El motivo real del fallo vive aquí y solo aquí.
     *
     * El correo no se guarda: es P2 y `audit_events.context` no es sitio para dato de
     * persona. Cuando hay usuario, `actor_user_id` ya dice quién; cuando no lo hay, lo que
     * importa es que alguien probó con un correo que no existe, no cuál.
     *
     * @param  array<string, mixed>  $context
     */
    private function audit(string $email, string $action, ?User $user, array $context): void
    {
        $this->recorder->record(
            action: $action,
            resourceType: 'users',
            resourceId: $user?->getKey() === null ? null : (string) $user->getKey(),
            result: $action === 'auth.login.succeeded'
                ? AuditRecorder::ALLOWED
                : AuditRecorder::DENIED,
            classification: DataClassification::P2,
            context: $context,
            actorUserId: $user?->getKey() === null ? null : (string) $user->getKey(),
        );
    }
}
