<?php

declare(strict_types=1);

namespace App\Domains\Shared\Infrastructure\Logging;

use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\TenantContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * §21 del harness: toda operación se correlaciona con un request/correlation ID.
 *
 * Sin esto, un incidente obliga a reconstruir qué líneas del log pertenecen a la misma
 * petición por proximidad temporal, que con varios trabajadores concurrentes no funciona.
 * El tenant va al lado por el mismo motivo: saber a qué cliente afectó algo es la primera
 * pregunta que se hace, y no debería exigir una consulta.
 */
final class AddRequestContext implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [
            ...$record->extra,
            'correlation_id' => CorrelationId::current(),
            // El identificador del tenant, no su nombre: el nombre es dato del cliente.
            'tenant_id' => TenantContext::idOrNull(),
        ]);
    }
}
