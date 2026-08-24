<?php

declare(strict_types=1);

namespace App\Domains\Shared\Infrastructure\Logging;

use App\Domains\Shared\Domain\DataClassification;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Impide que un dato P3 llegue al log (regla 4 de `data-classification.md`).
 *
 * El vector real no es que alguien escriba `Log::info($persona->birth_date)`. Es la
 * excepción de base de datos: el mensaje de `QueryException` trae la consulta **con los
 * valores ya interpolados**, así que un `INSERT` fallido sobre `people` escribe el nombre,
 * el sexo y la fecha de nacimiento en el fichero de log. Y los logs viven años, se copian
 * a herramientas de observabilidad y los lee gente que no tiene propósito declarado para
 * ese dato.
 *
 * Se redacta la cola `SQL: …` entera en vez de intentar separar valores de estructura.
 * Se pierde comodidad al depurar —hay que mirar la traza para saber qué consulta era— y se
 * gana que el log no sea una segunda copia de la base de datos sin control de acceso. Con
 * cinco años de retención, ese cambio vale la pena.
 */
final class RedactSensitiveData implements ProcessorInterface
{
    public const REDACTED = '[redactado]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubMessage($record->message),
            context: $this->scrubContext($record->context),
        );
    }

    /**
     * La consulta con valores interpolados es una copia del dato. Se corta desde `SQL:`,
     * conservando el SQLSTATE y la conexión, que es lo que sirve para diagnosticar.
     */
    private function scrubMessage(string $message): string
    {
        $scrubbed = preg_replace('/\bSQL:\s.*$/su', 'SQL: '.self::REDACTED, $message);

        return $scrubbed ?? $message;
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    private function scrubContext(array $context): array
    {
        $scrubbed = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitiveColumnName($key)) {
                $scrubbed[$key] = self::REDACTED;

                continue;
            }

            $scrubbed[$key] = match (true) {
                is_array($value) => $this->scrubContext($value),
                is_string($value) => $this->scrubMessage($value),
                default => $value,
            };
        }

        return $scrubbed;
    }

    /** Misma pregunta que hace AuditRecorder: ¿alguna tabla clasifica P3/P4 esta columna? */
    private function isSensitiveColumnName(string $column): bool
    {
        foreach (DataClassification::declaredTables() as $table) {
            if (DataClassification::isSensitive($table, $column)) {
                return true;
            }
        }

        return false;
    }
}
