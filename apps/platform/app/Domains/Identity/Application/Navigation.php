<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Identity\Domain\Authorization\Permission;
use App\Domains\Identity\Domain\Authorization\PermissionMatrix;
use App\Domains\Identity\Domain\User;

/**
 * El menú de una persona: solo lo que su rol alcanza.
 *
 * Es la decisión de B7 hecha código. El rector **no ve** «Personas · nueva» en gris: no ve
 * la entrada. Un botón deshabilitado que nunca se va a poder pulsar es una promesa rota
 * cada vez que se mira, y además enseña el mapa completo de lo que existe a quien no
 * debería conocerlo.
 *
 * **Por qué pregunta a la matriz y no al `Authorizer`.** El autorizador audita cada
 * denegación (CA-04), que es correcto para un acceso real y desastroso para pintar un
 * menú: seis entradas × cada carga de página llenaría `audit_events` de ruido y enterraría
 * los intentos que sí importan. El menú es una pista, no una decisión de acceso; la
 * decisión sigue tomándose en el controlador, con auditoría, cuando alguien entra de
 * verdad. Y como las dos leen la misma matriz, no se pueden desincronizar.
 */
final class Navigation
{
    public function __construct(private readonly EffectiveRoles $roles) {}

    /**
     * Las entradas del rail, en orden fijo. `recurso` y `accion` son la celda de la matriz
     * que hace falta para que la entrada exista.
     *
     * @var list<array{ruta: string, etiqueta: string, icono: string, recurso: string, accion: string}>
     */
    private const ENTRADAS = [
        ['ruta' => '/mi-trabajo', 'etiqueta' => 'Mi trabajo', 'icono' => 'bandeja', 'recurso' => 'people', 'accion' => 'listar'],
        ['ruta' => '/personas', 'etiqueta' => 'Personas', 'icono' => 'personas', 'recurso' => 'people', 'accion' => 'listar'],
        ['ruta' => '/relaciones', 'etiqueta' => 'Relaciones', 'icono' => 'calendario', 'recurso' => 'relationships', 'accion' => 'listar'],
        ['ruta' => '/matricula', 'etiqueta' => 'Matrícula', 'icono' => 'barras', 'recurso' => 'enrollment_snapshots', 'accion' => 'leer'],
        ['ruta' => '/reportes', 'etiqueta' => 'Reportes', 'icono' => 'documento', 'recurso' => 'reports.c600', 'accion' => 'generar'],
        ['ruta' => '/auditoria', 'etiqueta' => 'Auditoría', 'icono' => 'escudo', 'recurso' => 'audit_events', 'accion' => 'leer'],
    ];

    /**
     * @param  array<string, int>  $pendientes  ruta => cuántos asuntos esperan
     * @return list<array<string, mixed>>
     */
    public function para(User $user, string $rutaActual, array $pendientes = [], array $urgentes = []): array
    {
        $roles = $this->roles->of($user);
        $entradas = [];

        foreach (self::ENTRADAS as $entrada) {
            $alcanza = false;

            foreach ($roles as $role) {
                if (PermissionMatrix::for($entrada['recurso'], $entrada['accion'], $role) !== Permission::Deny) {
                    $alcanza = true;
                    break;
                }
            }

            if (! $alcanza) {
                continue;
            }

            $entradas[] = [
                'ruta' => $entrada['ruta'],
                'etiqueta' => $entrada['etiqueta'],
                'icono' => $entrada['icono'],
                // Coincidencia por prefijo: `/personas/9f2c…` deja «Personas» marcada.
                'activa' => $rutaActual === $entrada['ruta'] || str_starts_with($rutaActual, $entrada['ruta'].'/'),
                'pendientes' => $pendientes[$entrada['ruta']] ?? null,
                'urgente' => in_array($entrada['ruta'], $urgentes, true),
            ];
        }

        return $entradas;
    }

    /**
     * Una línea que dice qué alcanza el rol, para el pie del rail.
     *
     * Se escribe una vez y sin regañar: la pantalla no repite en cada botón lo que esta
     * persona no puede hacer.
     */
    public function alcanceDe(User $user): ?string
    {
        $roles = array_map(fn ($r) => $r->value, $this->roles->of($user));

        return match (true) {
            in_array('rector', $roles, true) => 'Dirección: consulta y firma. Las altas y bajas las ejecuta talento humano.',
            in_array('auditor', $roles, true) => 'Auditoría: lectura de trazas y reportes, sin datos sensibles de personas.',
            in_array('coordinador', $roles, true) => 'Coordinación: tu sede, sin los datos sensibles de las personas.',
            in_array('trabajador', $roles, true) => 'Solo tus propios datos.',
            default => null,
        };
    }
}
