<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga el tenant de demostración con datos sintéticos (paso B6).
 *
 * **Todo lo que crea es inventado.** Los datos reales del personal del colegio no entran
 * hasta el paso D1, y solo si el Slice 0 pasó su Definition of Done completo: esa
 * precondición es dura y está escrita en PLAN.md.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AnchorTenantSeeder::class);
    }
}
