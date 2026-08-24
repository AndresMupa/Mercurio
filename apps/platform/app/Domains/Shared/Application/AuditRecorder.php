<?php

declare(strict_types=1);

namespace App\Domains\Shared\Application;

use App\Domains\Identity\Domain\Authorization\Purpose;
use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\Domain\AuditEvent;
use App\Domains\Shared\Domain\DataClassification;
use App\Domains\Shared\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Punto único de escritura de la auditoría (regla 4 de `CLAUDE.md`, CA-05).
 *
 * Antes de B4 había dos sitios escribiendo `AuditEvent::create()` con su propia idea de
 * qué campos rellenar. Dos sitios se convierten en cinco, y entonces cada evento trae lo
 * que su autor recordó poner: la traza deja de ser comparable justo cuando hace falta
 * compararla, que es durante un incidente.
 *
 * Lo que este objeto garantiza y una llamada suelta no garantizaba:
 *
 *  - `correlation_id` siempre presente y el de la petición en curso.
 *  - `actor_user_id` resuelto del usuario autenticado si nadie lo pasa.
 *  - `data_classification` siempre informado.
 *  - **`context` sin un solo campo P3 o P4**, comprobado antes de escribir y no por
 *    confianza en quien llama.
 */
final class AuditRecorder
{
    public const ALLOWED = 'allowed';

    public const DENIED = 'denied';

    public const ERROR = 'error';

    /**
     * @param  array<string, mixed>  $context  metadatos del suceso, nunca contenido de persona
     */
    public function record(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        string $result = self::ALLOWED,
        string $classification = DataClassification::P1,
        ?Purpose $purpose = null,
        array $context = [],
        ?string $actorUserId = null,
        ?string $actorLabel = null,
        string $source = 'web',
    ): AuditEvent {
        $this->assertContextCarriesNoSensitiveField($context, $action);

        return AuditEvent::create([
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'purpose' => $purpose?->value,
            'data_classification' => $classification,
            'correlation_id' => CorrelationId::current(),
            'actor_user_id' => $actorUserId ?? $this->currentActorId(),
            'actor_label' => $actorLabel,
            'result' => $result,
            'source' => $source,
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Registra un error (paso B4, `result = error`).
     *
     * **No se guarda el mensaje de la excepción.** Parece información útil y es una fuga:
     * el mensaje de `QueryException` trae la consulta con los valores ya interpolados, de
     * modo que un `INSERT` fallido sobre `people` metería el nombre, el sexo y la fecha de
     * nacimiento dentro de `audit_events`, que se conserva cinco años y lo lee quien
     * revisa trazas, sin propósito declarado para ese dato. Se guarda la clase, el
     * fichero y la línea, que es lo que sirve para encontrar el fallo.
     *
     * Si no hay tenant resuelto no se puede escribir —la RLS lo rechaza, y con razón— así
     * que se deja constancia en el log en vez de perder el suceso en silencio.
     */
    public function recordError(\Throwable $e, string $resourceType = 'system', ?string $action = null): ?AuditEvent
    {
        if (TenantContext::idOrNull() === null) {
            Log::warning('Error sin tenant resuelto: no se pudo auditar.', [
                'exception' => $e::class,
                'correlation_id' => CorrelationId::current(),
            ]);

            return null;
        }

        return $this->record(
            action: $action ?? 'system.error',
            resourceType: $resourceType,
            result: self::ERROR,
            classification: DataClassification::P1,
            context: [
                'exception' => $e::class,
                'origin' => basename($e->getFile()).':'.$e->getLine(),
            ],
        );
    }

    /**
     * Rechaza escribir si el contexto trae una clave clasificada P3 o P4.
     *
     * Es el error real que se comete: no inventarse un campo raro, sino volcar el modelo
     * entero —`$person->toArray()`— o añadir «para tener más detalle» el documento de la
     * persona. La regla 4 de `data-classification.md` prohíbe exactamente eso, y hasta
     * aquí solo existía como frase en un documento.
     *
     * Se comprueba por **nombre de clave**, en toda la profundidad del array. No se filtra
     * en silencio: se lanza, porque un evento de auditoría que se escribe a medias sin que
     * nadie se entere es peor que uno que falla ruidosamente.
     *
     * @param  array<array-key, mixed>  $context
     */
    private function assertContextCarriesNoSensitiveField(array $context, string $action): void
    {
        foreach ($this->flattenKeys($context) as $key) {
            if ($this->isSensitiveColumnName($key)) {
                throw new RuntimeException(
                    "El contexto de auditoría de «{$action}» incluye «{$key}», que está ".
                    'clasificado P3 o P4. La auditoría registra qué pasó, no el dato: '.
                    'guarda el nombre del campo, nunca su contenido. Ver data-classification.md.'
                );
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return list<string>
     */
    private function flattenKeys(array $context): array
    {
        $keys = [];

        foreach ($context as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flattenKeys($value)];
            }
        }

        return $keys;
    }

    /** ¿Existe alguna tabla donde esta columna sea P3 o P4? */
    private function isSensitiveColumnName(string $column): bool
    {
        foreach (DataClassification::declaredTables() as $table) {
            if (DataClassification::isSensitive($table, $column)) {
                return true;
            }
        }

        return false;
    }

    private function currentActorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}
