<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planta física de la sede (paso B6).
 *
 * Lo destapó CA-10 al pedir que el tenant demo tenga «29 aulas»: no había dónde ponerlas.
 * `docs/anchor/colegio-finlandes.md` sí lista `plantas_fisicas` entre los campos que la
 * sede necesita, pero `core-entities.md` no los recogió, así que la migración de fundación
 * tampoco. Es una omisión de la Fase Cero, no una decisión.
 *
 * Los tres campos son **P1**: describen un inmueble, no a una persona. Por eso esta tabla
 * no cambia de clasificación y `DataClassification` sigue tratando `sites` como P1 entero.
 *
 * El C600 pide estos datos en su módulo de infraestructura, así que el Slice 1 los va a
 * necesitar. Se añaden ahora porque el seeder ya los necesita para tener la forma real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->unsignedSmallInteger('classroom_count')->nullable();   // P1 — aulas
            $t->unsignedInteger('built_area_m2')->nullable();          // P1
            $t->unsignedInteger('lot_area_m2')->nullable();            // P1
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->dropColumn(['classroom_count', 'built_area_m2', 'lot_area_m2']);
        });
    }
};
