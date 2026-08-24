<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use InvalidArgumentException;

/**
 * Registro de clasificación de datos por columna.
 *
 * Existe para que la regla 5 de `docs/architecture/data-classification.md` —«un campo sin
 * clasificación asignada rompe el build»— deje de ser una frase y pase a ser algo que se
 * comprueba. Hasta B2 era solo una frase: el documento la enunciaba y nada la verificaba.
 *
 * La forma imita al documento a propósito. Las tablas puramente organizativas se clasifican
 * enteras («todos → P1» allí); las que llevan datos de personas se enumeran columna a
 * columna, porque es donde una columna nueva sin clasificar tiene consecuencias.
 *
 * Quien lo consume es B3: `purpose` obligatorio para P3 es una decisión que necesita saber
 * qué es P3, y saberlo de un sitio y no de la memoria de quien escribe la Policy.
 */
final class DataClassification
{
    public const P0 = 'P0';

    public const P1 = 'P1';

    public const P2 = 'P2';

    public const P3 = 'P3';

    public const P4 = 'P4';

    /** Estructurales en toda tabla: identificador y marcas de tiempo. */
    private const STRUCTURAL = ['id', 'tenant_id', 'created_at', 'updated_at'];

    /** Tablas clasificadas enteras, como hace el documento con «todos». */
    private const WHOLE_TABLE = [
        'tenants' => self::P1,
        'organizations' => self::P1,
        'legal_entities' => self::P1,   // `tax_id` es público en Colombia
        'sites' => self::P1,            // `dane_code` es público
        'positions' => self::P1,
        'roles' => self::P1,
        'role_assignments' => self::P1,
        'enrollment_snapshots' => self::P1, // agregado sin PII (ADR 0003)
    ];

    /** Tablas con datos de personas: columna a columna. */
    private const BY_COLUMN = [
        'people' => [
            'given_names' => self::P2,
            'family_names' => self::P2,
            'birth_date' => self::P3,
            'sex' => self::P3,
            'education_level' => self::P2,
            'teaching_statute' => self::P2,
            'teaching_grade' => self::P2,
            'status' => self::P1,
        ],
        'identities' => [
            'person_id' => self::P2,
            'document_type' => self::P2,
            'document_number' => self::P3,
            'country_code' => self::P1,
        ],
        'relationships' => [
            'person_id' => self::P2,
            'legal_entity_id' => self::P1,
            'site_id' => self::P1,
            'type' => self::P2,
            'employment_type' => self::P2,
            'valid_from' => self::P2,
            'valid_to' => self::P2,
            'status' => self::P2,
        ],
        'assignments' => [
            'relationship_id' => self::P2,
            'position_id' => self::P1,
            'teaching_level' => self::P2,
            'weekly_hours' => self::P2,
            'valid_from' => self::P2,
            'valid_to' => self::P2,
        ],
        'users' => [
            'person_id' => self::P2,
            'email' => self::P2,
            'password' => self::P3,
            'mfa_enabled' => self::P1,
            'mfa_secret' => self::P3,
            'last_login_at' => self::P2,
            'status' => self::P1,
            'remember_token' => self::P3,
        ],
        'audit_events' => [
            'actor_user_id' => self::P2,
            'actor_label' => self::P2,
            'action' => self::P1,
            'resource_type' => self::P1,
            'resource_id' => self::P1,
            'purpose' => self::P1,
            'data_classification' => self::P1,
            'correlation_id' => self::P1,
            'source' => self::P1,
            'result' => self::P1,
            'context' => self::P2,   // prohibido copiar contenido P3/P4 dentro
            'occurred_at' => self::P1,
        ],
    ];

    /** @return list<string> */
    public static function declaredTables(): array
    {
        return [...array_keys(self::WHOLE_TABLE), ...array_keys(self::BY_COLUMN)];
    }

    /** Nivel de una columna, o null si nadie la ha clasificado. */
    public static function of(string $table, string $column): ?string
    {
        if (in_array($column, self::STRUCTURAL, strict: true)) {
            return self::P1;
        }

        if (isset(self::WHOLE_TABLE[$table])) {
            return self::WHOLE_TABLE[$table];
        }

        return self::BY_COLUMN[$table][$column] ?? null;
    }

    /** El nivel más alto presente en una tabla: su techo de sensibilidad. */
    public static function ceilingOf(string $table): string
    {
        if (isset(self::WHOLE_TABLE[$table])) {
            return self::WHOLE_TABLE[$table];
        }

        if (! isset(self::BY_COLUMN[$table])) {
            throw new InvalidArgumentException("La tabla «{$table}» no está clasificada.");
        }

        return max(self::BY_COLUMN[$table]);
    }

    public static function isSensitive(string $table, string $column): bool
    {
        return in_array(self::of($table, $column), [self::P3, self::P4], strict: true);
    }
}
