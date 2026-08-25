<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

/**
 * El desenlace de un intento de acceso.
 *
 * `Rejected` cubre a propósito tres casos distintos —correo inexistente, contraseña
 * incorrecta y segundo factor incorrecto—. La distinción existe, pero vive en
 * `audit_events`, no en la respuesta: contarla al que pregunta convierte el formulario en
 * un directorio del personal.
 */
enum LoginResult
{
    case Authenticated;
    case Rejected;
    case Throttled;

    /** Credenciales correctas; falta el código del segundo factor. */
    case MfaRequired;

    /** El rol exige segundo factor y este usuario no lo tiene configurado. */
    case MfaEnrollmentRequired;

    /** Lo único que se le puede decir a quien está fuera. */
    public function publicMessage(): string
    {
        return match ($this) {
            self::Authenticated => 'Acceso concedido.',
            self::MfaRequired => 'Introduce el código de tu aplicación de autenticación.',
            self::MfaEnrollmentRequired => 'Tu rol exige un segundo factor. Contacta con quien administra la plataforma para configurarlo.',
            self::Throttled => 'Demasiados intentos. Espera un momento antes de volver a probar.',
            self::Rejected => 'Los datos no son correctos.',
        };
    }
}
