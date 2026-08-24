<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

use App\Domains\Shared\Domain\DataClassification;

/**
 * La matriz de `docs/architecture/permissions.md` como **dato**, no como código.
 *
 * Ni un solo `if` sobre nombres de rol en el resto del sistema: el rol es un input de la
 * decisión, nunca la decisión. Cambiar quién puede hacer qué se hace editando esta tabla,
 * que se lee al lado del documento y se compara con él de un vistazo.
 *
 * Falla cerrada: lo que no está declarado, se deniega. Un recurso nuevo no nace accesible
 * por descuido, y un rol sin columna —hoy `rector`— no obtiene nada hasta que alguien
 * decida qué le corresponde.
 */
final class PermissionMatrix
{
    /**
     * recurso => acción => [rol => permiso]
     *
     * El orden de las filas sigue al del documento a propósito, para poder cotejarlas.
     *
     * @var array<string, array<string, array<string, Permission>>>
     */
    private const MATRIX = [
        'people' => [
            'listar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
                'coordinador' => Permission::Allow,
                'jefe_area' => Permission::Allow,
                'trabajador' => Permission::AllowSelf,
                'auditor' => Permission::Allow,
                'soporte_plataforma' => Permission::Deny,
            ],
            'crear' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
            'editar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
        ],

        'people.birth_date' => [
            'leer' => [
                'owner' => Permission::AllowWithPurpose,
                'admin_rrhh' => Permission::AllowWithPurpose,
                'responsable_sst' => Permission::AllowWithPurpose,
                'trabajador' => Permission::AllowSelf,
            ],
        ],

        'people.sex' => [
            'leer' => [
                'owner' => Permission::AllowWithPurpose,
                'admin_rrhh' => Permission::AllowWithPurpose,
                'responsable_sst' => Permission::AllowWithPurpose,
                'trabajador' => Permission::AllowSelf,
            ],
        ],

        'identities.document_number' => [
            'leer' => [
                'owner' => Permission::AllowWithPurpose,
                'admin_rrhh' => Permission::AllowWithPurpose,
                'responsable_sst' => Permission::AllowWithPurpose,
                'trabajador' => Permission::AllowSelf,
            ],
        ],

        'relationships' => [
            'crear' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
            'cerrar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
            'listar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
                'coordinador' => Permission::Allow,
                'jefe_area' => Permission::Allow,
                'trabajador' => Permission::AllowSelf,
                'auditor' => Permission::Allow,
            ],
        ],

        'assignments' => [
            'gestionar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'coordinador' => Permission::Allow,
            ],
        ],

        'positions' => [
            'gestionar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
        ],

        'sites' => [
            'gestionar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
        ],

        'legal_entities' => [
            'gestionar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
            ],
        ],

        'enrollment_snapshots' => [
            'leer' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
                'coordinador' => Permission::Allow,
                'auditor' => Permission::Allow,
                'soporte_plataforma' => Permission::Allow,
            ],
            'cargar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'coordinador' => Permission::Allow,
            ],
            'editar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'coordinador' => Permission::Allow,
            ],
        ],

        'reports.c600' => [
            'generar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'coordinador' => Permission::Allow,
            ],
            'exportar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
                'auditor' => Permission::Allow,
            ],
        ],

        'reports.evi' => [
            'generar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
            ],
            'exportar' => [
                'owner' => Permission::Allow,
                'admin_rrhh' => Permission::Allow,
                'responsable_sst' => Permission::Allow,
                'auditor' => Permission::Allow,
            ],
        ],

        'users' => [
            'gestionar' => ['owner' => Permission::Allow],
        ],

        'role_assignments' => [
            'gestionar' => ['owner' => Permission::Allow],
        ],

        'audit_events' => [
            'leer' => [
                'owner' => Permission::Allow,
                'auditor' => Permission::Allow,
            ],
            // Fila de solo denegaciones. Está escrita en vez de omitida porque decir
            // «nadie, ni el owner» explícitamente es distinto de no haberlo pensado.
            'modificar' => [],
            'borrar' => [],
        ],

        'tenant.settings' => [
            'gestionar' => ['owner' => Permission::Allow],
        ],

        'dpa' => [
            'gestionar' => ['owner' => Permission::Allow],
        ],
    ];

    /**
     * Clasificación de cada recurso, tal como la etiqueta el documento en su columna
     * «Recurso»: `people` (P2), `people.birth_date` (P3), `enrollment_snapshots` (P1).
     *
     * Vive aquí y no en el llamador porque el threat model de B3 encontró que, si la
     * clasificación llegaba desde fuera, olvidarla **saltaba la comprobación del techo
     * del rol**: un `auditor` con techo P2 leía un recurso P3 si nadie decía que lo era.
     * Un parámetro olvidado no puede debilitar una decisión de autorización.
     *
     * @var array<string, string>
     */
    private const CLASSIFICATION = [
        'people' => DataClassification::P2,
        'people.birth_date' => DataClassification::P3,
        'people.sex' => DataClassification::P3,
        'identities.document_number' => DataClassification::P3,
        'relationships' => DataClassification::P2,
        'assignments' => DataClassification::P2,
        'positions' => DataClassification::P1,
        'sites' => DataClassification::P1,
        'legal_entities' => DataClassification::P1,
        'enrollment_snapshots' => DataClassification::P1,
        'reports.c600' => DataClassification::P1,
        'reports.evi' => DataClassification::P1,
        'users' => DataClassification::P2,
        'role_assignments' => DataClassification::P1,
        'audit_events' => DataClassification::P2,
        'tenant.settings' => DataClassification::P1,
        'dpa' => DataClassification::P1,
    ];

    /**
     * Clasificación efectiva: la mayor entre la que declara la matriz y la que aporte el
     * llamador. Se toma el máximo, nunca el valor recibido, para que nadie pueda rebajar
     * la sensibilidad de un recurso pasando un nivel más bajo o ninguno.
     */
    public static function classificationOf(string $resource, ?string $supplied = null): string
    {
        $declared = self::CLASSIFICATION[$resource] ?? DataClassification::P3;

        return $supplied === null ? $declared : max($declared, $supplied);
    }

    public static function for(string $resource, string $action, RoleKey $role): Permission
    {
        return self::MATRIX[$resource][$action][$role->value] ?? Permission::Deny;
    }

    public static function knows(string $resource, string $action): bool
    {
        return isset(self::MATRIX[$resource][$action]);
    }

    /** @return list<string> */
    public static function resources(): array
    {
        return array_keys(self::MATRIX);
    }

    /** @return list<string> */
    public static function actionsFor(string $resource): array
    {
        return array_keys(self::MATRIX[$resource] ?? []);
    }
}
