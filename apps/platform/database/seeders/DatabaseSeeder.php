<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Vacío a propósito.
 *
 * El tenant de demostración con datos sintéticos es el paso B6 de PLAN.md, y los datos
 * reales del personal no entran hasta el D1, con el Slice 0 en verde. Un seeder que
 * "solo para probar" cree personas rompería la regla de datos del PLAN.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
