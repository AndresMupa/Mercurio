<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

/**
 * El resultado de una decisión, con su motivo.
 *
 * El motivo no es decoración: va a `audit_events` cuando la respuesta es denegar, y es lo
 * que permite responder «por qué no me dejó» sin reproducir el escenario. Nunca contiene
 * datos P3: describe la regla que falló, no el dato que se quería leer.
 */
final readonly class AuthorizationDecision
{
    private function __construct(
        public bool $allowed,
        public string $reason,
        public ?string $rule = null,
    ) {}

    public static function allow(string $reason = 'permitido por la matriz'): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason, string $rule): self
    {
        return new self(false, $reason, $rule);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
