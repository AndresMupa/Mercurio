<?php

use App\Http\Controllers\FoundationStatusController;
use Illuminate\Support\Facades\Route;

/*
 * Slice 0 · paso A1. La única ruta que existe todavía.
 * Las rutas del producto llegan con las pantallas aprobadas en B7 (PLAN.md).
 */
Route::get('/', FoundationStatusController::class)->name('foundation.status');
