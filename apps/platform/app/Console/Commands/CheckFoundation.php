<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Shared\FoundationCheck;
use Illuminate\Console\Command;

/**
 * Verifica la fundación desde la línea de comandos. Sale con código distinto de cero
 * si algo crítico falla, para que pueda usarse como compuerta en despliegue y en CI.
 */
final class CheckFoundation extends Command
{
    protected $signature = 'platform:check-foundation';

    protected $description = 'Verifica la separación de roles de base de datos, la RLS y los servicios de la fundación.';

    public function handle(FoundationCheck $checks): int
    {
        $fallo = false;

        foreach ($checks->all() as $check) {
            $marca = match ($check['estado']) {
                FoundationCheck::OK => '<fg=green>  OK  </>',
                FoundationCheck::WARN => '<fg=yellow> AVISO</>',
                default => '<fg=red> FALLA</>',
            };

            $fallo = $fallo || $check['estado'] === FoundationCheck::CRITICAL;

            $this->line("{$marca}  {$check['nombre']}");
            $this->line("        {$check['detalle']}");
        }

        if ($fallo) {
            $this->newLine();
            $this->error('La fundación no cumple sus invariantes. No despliegues sobre esto.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
