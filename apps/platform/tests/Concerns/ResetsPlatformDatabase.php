<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domains\Shared\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Deja la base de pruebas limpia entre pruebas.
 *
 * No se usa RefreshDatabase ni DatabaseTransactions, y las dos razones importan:
 *
 * 1. RefreshDatabase migra sobre la conexión por defecto, que corre con el rol de
 *    aplicación. Ese rol no puede crear tablas —a propósito— así que migrar desde
 *    la prueba fallaría. El esquema lo prepara el dueño, antes de la suite:
 *
 *        DB_DATABASE=platform_test php artisan migrate:fresh --database=pgsql_owner
 *
 * 2. DatabaseTransactions daría falsos verdes. En PostgreSQL, una sentencia que
 *    falla aborta la transacción entera, y varias pruebas de aislamiento comprueban
 *    justamente que una sentencia falle. La segunda expectativa de esas pruebas
 *    pasaría por «transacción abortada» y no por la política de RLS que se quería
 *    probar. Una prueba que pasa por el motivo equivocado es peor que una que falla.
 *
 * TRUNCATE corre por la conexión del dueño porque la aplicación no lo tiene concedido
 * sobre audit_events: la auditoría es append-only también para las pruebas.
 */
trait ResetsPlatformDatabase
{
    /** El orden no importa: CASCADE arrastra a las dependientes. */
    private const ROOT_TABLES = ['tenants', 'audit_events'];

    protected function setUpResetsPlatformDatabase(): void
    {
        $tables = implode(', ', self::ROOT_TABLES);

        DB::connection('pgsql_owner')->statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");

        // set_config(..., false) vive en la conexión, no en la transacción: sin esto,
        // el tenant de una prueba se filtraría a la siguiente.
        TenantContext::clear();
    }

    protected function tearDownResetsPlatformDatabase(): void
    {
        TenantContext::clear();

        // Cada prueba reconstruye la aplicación, y con ella el gestor de conexiones. Sin
        // cerrarlas explícitamente, las anteriores quedan abiertas contra PostgreSQL: una
        // suite con doscientos casos agota `max_connections` y falla por «remaining
        // connection slots are reserved», que no tiene nada que ver con lo que se probaba.
        DB::disconnect('pgsql');
        DB::disconnect('pgsql_owner');
    }
}
