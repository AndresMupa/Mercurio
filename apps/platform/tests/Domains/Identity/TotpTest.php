<?php

/**
 * TOTP contra los **vectores de prueba publicados en el RFC 6238**, apéndice B.
 *
 * Esta es la prueba que justifica haber escrito el algoritmo en vez de instalarlo: no se
 * comprueba que el código «parezca un número de seis cifras», se comprueba que produzca
 * exactamente el valor que el estándar dice para cada instante. Si el truncado o el
 * relleno del contador estuvieran mal, todos estos casos fallarían.
 */

use App\Domains\Identity\Domain\Totp;

/** El secreto del RFC en ASCII: «12345678901234567890», codificado en base32. */
const SECRETO_RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

it('reproduce los vectores del RFC 6238 con ocho dígitos', function (int $instante, string $esperado) {
    $totp = new Totp(digits: 8);

    expect($totp->at(SECRETO_RFC, $instante))->toBe($esperado);
})->with([
    // [segundos desde epoch, código esperado] — RFC 6238, apéndice B, SHA1
    [59, '94287082'],
    [1111111109, '07081804'],
    [1111111111, '14050471'],
    [1234567890, '89005924'],
    [2000000000, '69279037'],
    [20000000000, '65353130'],
]);

it('con seis dígitos devuelve las seis últimas cifras del vector', function (int $instante, string $ocho) {
    $totp = new Totp;

    expect($totp->at(SECRETO_RFC, $instante))->toBe(substr($ocho, -6));
})->with([
    [59, '94287082'],
    [1111111109, '07081804'],
    [1234567890, '89005924'],
]);

it('el mismo código vale durante todo su intervalo y cambia al siguiente', function () {
    $totp = new Totp;

    // El intervalo 33 abarca [990, 1019]; el 34 empieza en 1020. Se usan los bordes
    // exactos: elegirlos «a ojo» fue lo que hizo fallar la primera versión de esta prueba.
    $inicio = $totp->at(SECRETO_RFC, 990);
    $mismoIntervalo = $totp->at(SECRETO_RFC, 1019);
    $siguiente = $totp->at(SECRETO_RFC, 1020);

    expect($mismoIntervalo)->toBe($inicio)
        ->and($siguiente)->not->toBe($inicio);
});

it('acepta un intervalo de desfase a cada lado, pero no dos', function () {
    $totp = new Totp;
    $ahora = 1_700_000_000;

    // El reloj del teléfono atrasado o adelantado un intervalo sigue sirviendo.
    expect($totp->verify(SECRETO_RFC, $totp->at(SECRETO_RFC, $ahora - 30), $ahora))->toBeTrue()
        ->and($totp->verify(SECRETO_RFC, $totp->at(SECRETO_RFC, $ahora + 30), $ahora))->toBeTrue();

    // Dos intervalos ya no: un código robado no vale dos minutos.
    expect($totp->verify(SECRETO_RFC, $totp->at(SECRETO_RFC, $ahora - 90), $ahora))->toBeFalse()
        ->and($totp->verify(SECRETO_RFC, $totp->at(SECRETO_RFC, $ahora + 90), $ahora))->toBeFalse();
});

it('rechaza códigos malformados sin lanzar', function (string $codigo) {
    $totp = new Totp;

    // Un código con letras es un intento fallido, no un error del servidor.
    expect($totp->verify(SECRETO_RFC, $codigo, 1_700_000_000))->toBeFalse();
})->with(['', '12345', '1234567', 'abcdef', '12 34 56', '000000x']);

it('rechaza un secreto que no es base32 sin lanzar', function () {
    $totp = new Totp;

    expect($totp->verify('esto-no-es-base32!', '123456', 1_700_000_000))->toBeFalse();
});

it('genera secretos distintos y utilizables', function () {
    $uno = Totp::generateSecret();
    $dos = Totp::generateSecret();
    $totp = new Totp;

    expect($uno)->not->toBe($dos)
        ->and(strlen($uno))->toBe(32)
        ->and($totp->verify($uno, $totp->at($uno, 1_700_000_000), 1_700_000_000))->toBeTrue()
        // Y el código de un secreto no vale para otro.
        ->and($totp->verify($dos, $totp->at($uno, 1_700_000_000), 1_700_000_000))->toBeFalse();
});

it('la URI de aprovisionamiento lleva lo que el autenticador necesita', function () {
    $uri = (new Totp)->provisioningUri(SECRETO_RFC, 'ana@colegio.test', 'Plataforma');

    expect($uri)->toStartWith('otpauth://totp/Plataforma:ana%40colegio.test?')
        ->and($uri)->toContain('secret='.SECRETO_RFC)
        ->and($uri)->toContain('algorithm=SHA1')
        ->and($uri)->toContain('digits=6')
        ->and($uri)->toContain('period=30');
});
