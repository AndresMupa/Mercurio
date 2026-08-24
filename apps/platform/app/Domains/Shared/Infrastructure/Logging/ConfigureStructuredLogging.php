<?php

declare(strict_types=1);

namespace App\Domains\Shared\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Convierte el canal en log estructurado: una línea JSON por suceso, con correlación y
 * tenant, y con los datos sensibles redactados antes de tocar el disco.
 *
 * El orden de los procesadores importa: Monolog los aplica en orden inverso al de
 * apilado, así que se apila primero el que añade contexto y después el que redacta. Si
 * fuera al revés, se añadiría contexto ya redactado y el redactor no vería lo que llega
 * de la excepción.
 */
final class ConfigureStructuredLogging
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new AddRequestContext);
        $logger->pushProcessor(new RedactSensitiveData);

        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if (method_exists($handler, 'setFormatter')) {
                $handler->setFormatter(new JsonFormatter);
            }
        }
    }
}
