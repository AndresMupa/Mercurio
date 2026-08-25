<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Generador de personas **ficticias**. Ni un dato real (PLAN.md B6, regla de datos §5).
 *
 * Está separado del seeder a propósito, para que la frase «todos los nombres y documentos
 * son ficticios y generados» sea comprobable mirando un solo fichero en vez de confiar en
 * que nadie pegó una lista de verdad en medio de la carga.
 *
 * Es determinista: la misma semilla produce el mismo tenant. Un demo que cambia de nombres
 * en cada ejecución es imposible de comentar con nadie —«mira la fila de Ana» deja de
 * significar algo— y hace que cualquier prueba sobre él sea frágil.
 *
 * Los documentos salen de un rango reservado que empieza en 90.000.000 y sube de uno en
 * uno. No es un rango que la Registraduría asigne hoy, y al ser secuencial se distingue de
 * un documento real de un vistazo.
 */
final class SyntheticPeople
{
    private const GIVEN_FEMALE = [
        'Adriana', 'Beatriz', 'Carolina', 'Diana', 'Elena', 'Fernanda', 'Gabriela',
        'Helena', 'Isabel', 'Julia', 'Karina', 'Lucía', 'Marcela', 'Natalia',
        'Olga', 'Patricia', 'Rocío', 'Sandra', 'Teresa', 'Valentina',
    ];

    private const GIVEN_MALE = [
        'Alberto', 'Bernardo', 'Camilo', 'Daniel', 'Esteban', 'Felipe', 'Gonzalo',
        'Hernán', 'Ignacio', 'Javier', 'Leonardo', 'Mauricio', 'Nicolás',
        'Óscar', 'Pablo', 'Ramiro', 'Sebastián', 'Tomás', 'Vicente', 'Wilson',
    ];

    private const FAMILY = [
        'Acosta', 'Bermúdez', 'Cadena', 'Duarte', 'Escobar', 'Fajardo', 'Galvis',
        'Higuera', 'Ibáñez', 'Jaramillo', 'Lozano', 'Mahecha', 'Neira', 'Ospina',
        'Pardo', 'Quintero', 'Rincón', 'Salgado', 'Tovar', 'Urrego', 'Vanegas', 'Zapata',
    ];

    private int $sequence = 0;

    private int $document = 90_000_000;

    /**
     * @return array{given_names: string, family_names: string, sex: string, document_number: string, birth_date: string}
     */
    public function next(string $sex, int $ageYears): array
    {
        $given = $sex === 'femenino' ? self::GIVEN_FEMALE : self::GIVEN_MALE;

        $nombre = $given[$this->sequence % count($given)];
        $primerApellido = self::FAMILY[($this->sequence * 3) % count(self::FAMILY)];
        $segundoApellido = self::FAMILY[($this->sequence * 7 + 5) % count(self::FAMILY)];

        $this->sequence++;

        return [
            'given_names' => $nombre,
            'family_names' => $primerApellido.' '.$segundoApellido,
            'sex' => $sex,
            'document_number' => (string) $this->document++,
            // Día fijo dentro del año: la edad importa para los rangos del C600, la fecha
            // exacta no, y una fecha inventada al azar solo añade ruido sin realismo.
            'birth_date' => now()->subYears($ageYears)->startOfYear()->addDays(
                ($this->sequence * 37) % 360
            )->toDateString(),
        ];
    }
}
