<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CA-04: leer un dato P3 sin propósito válido devuelve 403 y deja `audit_events` con
 * `result = denied`.
 *
 * 403 y no 422: no es que falte un campo del formulario, es que la operación no está
 * autorizada tal como viene. Devolver 422 invitaría a reintentarla con cualquier valor.
 */
final class PurposeRequired extends HttpException
{
    public static function forReading(string $resource): self
    {
        $validos = implode(', ', array_column(Purpose::cases(), 'value'));

        return new self(403, "Leer «{$resource}» exige declarar el propósito. Valores válidos: {$validos}.");
    }

    public static function invalid(string $value): self
    {
        $validos = implode(', ', array_column(Purpose::cases(), 'value'));

        return new self(403, "«{$value}» no es un propósito válido. Valores válidos: {$validos}.");
    }
}
