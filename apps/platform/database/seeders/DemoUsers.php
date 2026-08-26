<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Domain\Role;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Person;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas para poder entrar al tenant demo (añadido en B8).
 *
 * B6 dejó un colegio con 64 personas y ninguna forma de mirarlo desde el navegador, que
 * para un demo es como no tenerlo. Estas cuentas cierran ese hueco y son las que usa la
 * prueba de accesibilidad de punta a punta.
 *
 * **La contraseña es pública y está escrita aquí a propósito.** No es un descuido ni un
 * secreto mal guardado: es una credencial de demostración sobre datos inventados, y
 * esconderla en una variable de entorno solo conseguiría que pareciera un secreto de
 * verdad. Los datos reales del personal no entran hasta D1, y ese paso exige revisar
 * las credenciales antes.
 *
 * El segundo factor se reparte a propósito: el rol con techo P3 lo exige —CA-09— y por eso
 * lleva un secreto TOTP conocido, para que el flujo se pueda enseñar; el de techo P2 entra
 * sin él, que es el camino corto para una demostración.
 */
final class DemoUsers
{
    /** Contraseña de todas las cuentas de demostración. Ver la cabecera. */
    public const CONTRASENA = 'demo-mercurio-2026';

    /**
     * Secreto TOTP fijo del rol con techo P3, en base32.
     * Sirve para generar códigos válidos en una demostración y en el E2E.
     */
    public const SECRETO_MFA = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** @var list<array{0: string, 1: string, 2: bool}> correo, rol, exige segundo factor */
    private const CUENTAS = [
        ['rector@colegio-demo.test', 'rector', true],
        ['rrhh@colegio-demo.test', 'admin_rrhh', true],
        ['coordinacion@colegio-demo.test', 'coordinador', false],
        ['auditoria@colegio-demo.test', 'auditor', false],
    ];

    public function crear(): void
    {
        foreach (self::CUENTAS as [$correo, $rolKey, $conMfa]) {
            $usuario = new User([
                'email' => $correo,
                // Se ata a una persona real de la planta cuando existe: sin `person_id`
                // no hay relación laboral que comprobar, y CA-07 —cerrar la relación corta
                // el acceso— no se podría enseñar en el demo.
                'person_id' => $this->personaPara($rolKey)?->getKey(),
                'mfa_enabled' => $conMfa,
                'status' => 'active',
            ]);

            $usuario->password = Hash::make(self::CONTRASENA);

            if ($conMfa) {
                $usuario->mfa_secret = self::SECRETO_MFA;
            }

            $usuario->save();

            RoleAssignment::create([
                'user_id' => $usuario->getKey(),
                'role_id' => Role::firstOrCreate(['key' => $rolKey], ['name' => ucfirst($rolKey)])->getKey(),
                'scope_type' => $this->alcancePara($rolKey),
                'valid_from' => now()->subYear()->toDateString(),
            ]);
        }
    }

    /** El rector del demo es el Rector de verdad de la planta, no una cuenta suelta. */
    private function personaPara(string $rolKey): ?Person
    {
        return match ($rolKey) {
            'rector' => Person::whereHas(
                'relationships.assignments.position',
                fn ($q) => $q->where('title', 'Rector')
            )->first(),
            'admin_rrhh' => Person::whereHas(
                'relationships.assignments.position',
                fn ($q) => $q->where('title', 'Talento humano')
            )->first(),
            default => null,
        };
    }

    /** El alcance tiene que ser uno de los que admite el rol (`RoleKey::allowedScopeTypes`). */
    private function alcancePara(string $rolKey): string
    {
        return match ($rolKey) {
            'admin_rrhh', 'rector' => 'legal_entity',
            'coordinador' => 'site',
            default => 'tenant',
        };
    }
}
