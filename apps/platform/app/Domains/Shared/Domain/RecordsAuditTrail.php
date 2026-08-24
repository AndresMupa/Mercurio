<?php

declare(strict_types=1);

namespace App\Domains\Shared\Domain;

use App\Domains\Shared\Application\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoría de escrituras: crear y editar dejan rastro (paso B4).
 *
 * **Solo nombres de campo, nunca valores.** `['fields' => ['birth_date']]` dice que la
 * fecha de nacimiento cambió; `['birth_date' => '1985-03-12']` sería copiar un dato P3
 * dentro de la auditoría, que es justo lo que prohíbe la regla 4 de
 * `data-classification.md`. `AuditRecorder` además lo comprueba y se niega a escribirlo.
 *
 * Ese detalle no es celo: `audit_events` se conserva cinco años y lo lee gente que no
 * necesita ver el dato, solo saber que alguien lo tocó. Copiar el valor dentro convertiría
 * la tabla de auditoría en una segunda copia sin control de propósito.
 */
trait RecordsAuditTrail
{
    public static function bootRecordsAuditTrail(): void
    {
        static::created(function (Model $model): void {
            $model->recordAuditTrail('created', array_keys($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            if (! static::auditsUpdates()) {
                return;
            }

            $model->recordAuditTrail('updated', array_keys($model->getDirty()));
        });
    }

    /**
     * Los modelos cuyo cambio de estado ya tiene un evento con significado de dominio
     * lo desactivan, para no registrar dos veces lo mismo con dos nombres distintos.
     */
    protected static function auditsUpdates(): bool
    {
        return true;
    }

    /** El nombre del recurso en la auditoría es el de la tabla, igual que en la matriz. */
    protected function auditResourceType(): string
    {
        return $this->getTable();
    }

    /** @param  list<string>  $fields */
    protected function recordAuditTrail(string $verb, array $fields): void
    {
        $resource = $this->auditResourceType();

        app(AuditRecorder::class)->record(
            action: "{$resource}.{$verb}",
            resourceType: $resource,
            resourceId: (string) $this->getKey(),
            result: AuditRecorder::ALLOWED,
            classification: DataClassification::ceilingOf($resource),
            context: ['fields' => array_values(array_diff($fields, ['id', 'tenant_id', 'created_at', 'updated_at']))],
        );
    }
}
