<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Application\LoginAttempt;
use App\Domains\Identity\Application\LoginResult;
use App\Domains\Shared\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La superficie HTTP del acceso. Toda la decisión vive en `LoginAttempt` (paso B5).
 *
 * Este controlador no sabe por qué falló un intento y **no debe saberlo**: recibe un
 * `LoginResult` y lo traduce a una respuesta. El motivo real está en `audit_events`, que
 * sí puede leer quien tenga permiso. Contárselo a quien está fuera convertiría el
 * formulario en un directorio del personal del colegio.
 */
final class LoginController
{
    public function __construct(private readonly LoginAttempt $intento) {}

    public function show(): Response
    {
        return Inertia::render('Auth/Entrar', [
            // Se enseña a qué colegio se está entrando cuando el subdominio lo resuelve.
            // Cuando no resuelve **no se dice nada distinto**: responder «ese colegio no
            // existe» sería la enumeración que el resolver evita.
            'colegio' => TenantContext::idOrNull() === null ? null : session('colegio_nombre'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            // `email:rfc,strict` y no la regla `email` por defecto: CVE-2026-48019, una
            // inyección CRLF en la validación de correo de Laravel 11 que sigue sin parche
            // en esta rama. Deuda anotada desde B5 y pagada aquí, que es donde por fin se
            // valida un correo de verdad.
            'email' => ['required', 'string', 'email:rfc,strict', 'max:254'],
            'password' => ['required', 'string'],
            'codigo' => ['nullable', 'string', 'max:10'],
        ], [], ['email' => 'correo', 'password' => 'contraseña', 'codigo' => 'código']);

        $tenantId = TenantContext::idOrNull();

        if ($tenantId === null) {
            // Sin tenant resuelto no hay contra qué autenticar. Misma respuesta que unas
            // credenciales incorrectas, por lo mismo de siempre.
            return back()->withErrors(['email' => LoginResult::Rejected->publicMessage()]);
        }

        $resultado = $this->intento->attempt(
            tenantId: $tenantId,
            email: $datos['email'],
            password: $datos['password'],
            mfaCode: $datos['codigo'] ?? null,
            ip: (string) $request->ip(),
        );

        if ($resultado === LoginResult::Authenticated) {
            $usuario = $this->intento->authenticatedUser($datos['email']);

            Auth::login($usuario, remember: false);

            // Obligatorio tras autenticar: sin esto, el identificador de sesión que tenía
            // quien no había entrado sigue siendo válido después de entrar, que es la
            // fijación de sesión de manual.
            $request->session()->regenerate();

            return redirect()->intended('/mi-trabajo');
        }

        if ($resultado === LoginResult::MfaRequired) {
            return back()
                ->with('mfa', true)
                ->withInput($request->only('email'));
        }

        return back()
            ->withErrors(['email' => $resultado->publicMessage()])
            ->withInput($request->only('email'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/entrar');
    }
}
