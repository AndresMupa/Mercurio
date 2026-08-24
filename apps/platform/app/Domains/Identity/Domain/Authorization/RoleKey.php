<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

use App\Domains\Shared\Domain\DataClassification;

/** Roles de `docs/architecture/permissions.md`, con su alcance y su techo. */
enum RoleKey: string
{
    case Owner = 'owner';
    case AdminRrhh = 'admin_rrhh';
    case ResponsableSst = 'responsable_sst';
    case Rector = 'rector';
    case Coordinador = 'coordinador';
    case JefeArea = 'jefe_area';
    case Trabajador = 'trabajador';
    case Auditor = 'auditor';
    case SoportePlataforma = 'soporte_plataforma';

    /** Techo de clasificación: por encima de esto el rol no lee nada, con o sin propósito. */
    public function classificationCeiling(): string
    {
        return match ($this) {
            self::Coordinador, self::JefeArea,
            self::Auditor, self::SoportePlataforma => DataClassification::P2,
            default => DataClassification::P3,
        };
    }

    /** @return list<string> valores de `role_assignments.scope_type` admitidos */
    public function allowedScopeTypes(): array
    {
        return match ($this) {
            self::Owner, self::Auditor, self::SoportePlataforma => ['tenant'],
            self::AdminRrhh, self::Rector => ['legal_entity'],
            self::ResponsableSst => ['legal_entity', 'site'],
            self::Coordinador, self::JefeArea => ['site'],
            self::Trabajador => ['self'],
        };
    }

    /**
     * `soporte_plataforma` es temporal por definición: su asignación tiene que traer
     * vencimiento explícito. Invariante 6 de la matriz: caduca solo, sin intervención.
     */
    public function requiresExplicitExpiry(): bool
    {
        return $this === self::SoportePlataforma;
    }
}
