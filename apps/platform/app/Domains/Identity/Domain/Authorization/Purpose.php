<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain\Authorization;

/**
 * Lista cerrada de propósitos (docs/architecture/permissions.md).
 *
 * Cerrada a propósito: un campo de texto libre acaba lleno de «consulta», «revisión» y
 * cadenas vacías, y entonces la auditoría no responde la única pregunta que importa
 * cuando llega una reclamación —para qué se leyó ese dato—. Un enum obliga a elegir.
 *
 * Añadir un valor aquí es una decisión con efecto jurídico: cada propósito es una base
 * de tratamiento distinta. No se amplía sin pasar por `docs/legal/legal-matrix.md`.
 */
enum Purpose: string
{
    case ReporteC600 = 'reporte_c600';
    case AutoevaluacionEvi = 'autoevaluacion_evi';
    case GestionLaboral = 'gestion_laboral';
    case GestionSst = 'gestion_sst';
    case SoportePlataforma = 'soporte_plataforma';
    case AuditoriaInterna = 'auditoria_interna';
    case SolicitudDelTitular = 'solicitud_del_titular';

    public static function tryFromNullable(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
