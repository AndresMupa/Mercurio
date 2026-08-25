<?php

declare(strict_types=1);

namespace App\Domains\Identity\Domain;

use InvalidArgumentException;

/**
 * Segundo factor por tiempo (TOTP, RFC 6238 sobre HOTP, RFC 4226).
 *
 * **Por qué está escrito aquí en vez de instalar una librería.** El entorno de esta sesión
 * no puede descargar paquetes nuevos de Composer: `api.github.com` está bloqueado por la
 * política de egreso (ver `## Bloqueos` en STATE.md). Escribirlo no es «criptografía
 * propia»: TOTP es HMAC-SHA1 sobre un contador de tiempo más un truncado, todo con
 * `hash_hmac` de PHP. Lo delicado es equivocarse en el truncado o en el relleno, y contra
 * eso hay defensa: **el RFC publica vectores de prueba y esta implementación se compara
 * con ellos uno a uno** en `TotpTest`. Si algún día se instala una librería, se cambia por
 * dentro y las pruebas siguen valiendo.
 *
 * Decisiones que no son cosméticas:
 *
 *  - SHA1 y no SHA256. No es descuido: es lo que implementan Google Authenticator, Aegis y
 *    los demás. Cambiarlo haría que ningún autenticador real generase el código correcto.
 *    Su uso aquí es HMAC, donde SHA1 sigue siendo aceptable.
 *  - Ventana de ±1 intervalo. Cubre el reloj desfasado del teléfono sin regalar dos minutos
 *    de validez a un código robado.
 *  - Comparación en tiempo constante. Comparar con `===` filtra por tiempo cuántos dígitos
 *    coinciden, y eso convierte un millón de intentos en unas decenas.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $digits = 6,
        private readonly int $period = 30,
        private readonly string $algorithm = 'sha1',
    ) {}

    /** Secreto nuevo en base32, el formato que esperan los autenticadores. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** El código correspondiente a un instante. */
    public function at(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), $this->period);

        return $this->hotp(self::base32Decode($secret), $counter);
    }

    /**
     * Comprueba un código admitiendo un intervalo de desfase a cada lado.
     *
     * Devuelve false ante cualquier entrada malformada en vez de lanzar: un código con
     * letras es un intento fallido, no un error del servidor.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code = trim($code);

        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), $this->period);

        try {
            $key = self::base32Decode($secret);
        } catch (InvalidArgumentException) {
            return false;
        }

        $valido = false;

        for ($offset = -$window; $offset <= $window; $offset++) {
            // No se corta el bucle al acertar: salir antes revelaría por tiempo *qué*
            // intervalo coincidió, y con ello el desfase del reloj del usuario.
            $valido = hash_equals($this->hotp($key, $counter + $offset), $code) || $valido;
        }

        return $valido;
    }

    /** URI que leen los autenticadores al escanear el QR. */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ]);
    }

    /** HOTP, RFC 4226 §5.3: HMAC del contador y truncado dinámico. */
    private function hotp(string $key, int $counter): string
    {
        // El contador va en 8 bytes big-endian.
        $binaryCounter = pack('J', $counter);

        $hash = hash_hmac($this->algorithm, $binaryCounter, $key, binary: true);

        // El nibble bajo del último byte dice dónde empieza el número.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad(
            (string) ($truncated % (10 ** $this->digits)),
            $this->digits,
            '0',
            STR_PAD_LEFT
        );
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim(preg_replace('/\s+/', '', $secret) ?? '', '='));

        if ($secret === '' || strspn($secret, self::ALPHABET) !== strlen($secret)) {
            throw new InvalidArgumentException('El secreto no está en base32 válida.');
        }

        $bits = '';

        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
