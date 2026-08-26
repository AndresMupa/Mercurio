<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FoundationStatusController;
use App\Http\Controllers\MyWorkController;
use App\Http\Controllers\People\PersonIndexController;
use App\Http\Controllers\People\PersonRegistrationController;
use App\Http\Controllers\People\PersonShowController;
use App\Http\Controllers\People\RelationshipCloseController;
use App\Http\Controllers\People\SensitiveFieldController;
use Illuminate\Support\Facades\Route;

/*
 * Las rutas del Slice 0 (paso B8), sobre las pantallas aprobadas en B7.
 *
 * Todo lo que toca datos de personas va detrás de `auth`. La autorización fina —quién
 * puede qué— no está aquí: la deciden las Policies llamando al `Authorizer`, para que la
 * matriz de permisos siga siendo la única fuente de verdad. Una ruta que decidiera por su
 * cuenta sería una segunda matriz que nadie mantiene.
 */

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'store']);
});

Route::post('/salir', [LoginController::class, 'destroy'])->name('salir');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/mi-trabajo');

    /*
     * La pantalla de diagnóstico de A1, ahora detrás de acceso.
     *
     * Era pública mientras fue la única ruta que existía. Al revisarla en B8 se vio lo que
     * cuenta a quien no ha entrado: los nombres de los dos roles de base de datos, el
     * nombre de la base y la versión exacta de PostgreSQL. Nada de eso le sirve a un
     * visitante y todo le sirve a quien busque contra qué apuntar.
     *
     * El monitoreo no la necesita: para eso está `/up`, que sigue siendo público y solo
     * dice si la aplicación responde.
     */
    Route::get('/estado', FoundationStatusController::class)->name('foundation.status');

    Route::get('/mi-trabajo', MyWorkController::class)->name('mi-trabajo');

    // ── Personas ────────────────────────────────────────────────────────
    Route::get('/personas', PersonIndexController::class)->name('personas');

    // Las tres del alta van **antes** de `/personas/{persona}`: si no, «nueva» se toma
    // por un identificador y el recorrido J1 muere en un 404 difícil de explicar.
    Route::get('/personas/nueva', [PersonRegistrationController::class, 'show'])->name('personas.nueva');
    Route::post('/personas/buscar', [PersonRegistrationController::class, 'buscar']);
    Route::post('/personas/revisar', [PersonRegistrationController::class, 'revisar']);
    Route::post('/personas', [PersonRegistrationController::class, 'store']);

    Route::get('/personas/{persona}', PersonShowController::class)->name('personas.ficha');

    // El único camino por el que sale un dato P3 completo. Deja evento siempre, permita
    // o deniegue.
    Route::post('/personas/{persona}/ver', SensitiveFieldController::class)->name('personas.ver');

    // ── Relaciones · J5 ─────────────────────────────────────────────────
    Route::get('/relaciones/{relacion}/impacto', [RelationshipCloseController::class, 'impacto']);
    Route::post('/relaciones/{relacion}/cierre', [RelationshipCloseController::class, 'store'])->name('relaciones.cierre');
});
