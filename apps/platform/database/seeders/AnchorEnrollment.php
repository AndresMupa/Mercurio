<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * La matrícula del ancla como **dato**, no como bucle que improvisa cifras.
 *
 * `docs/anchor/colegio-finlandes.md` fija dos números que no se pueden aproximar: **883
 * estudiantes** y **13 en condición de discapacidad**. Son las cifras que el C600 va a
 * reportar y con las que se calcula la tarifa, así que un demo que sume 881 no serviría
 * para probar el Slice 1: el motor de reportes daría un total que no cuadra con el
 * documento y nadie sabría si el fallo está en el motor o en los datos.
 *
 * Por eso la distribución está escrita a mano y hay una prueba que comprueba las sumas.
 * Si alguien edita un grado, la prueba se lo dice.
 *
 * ADR 0003: aquí **no hay estudiantes**, hay conteos. Ninguna de estas filas identifica a
 * nadie, y la tabla no tiene una sola columna que permitiría hacerlo.
 */
final class AnchorEnrollment
{
    /** Total que exige el documento del ancla. */
    public const TOTAL = 883;

    /** Estudiantes en condición de discapacidad, matrícula inclusiva «Sí». */
    public const WITH_DISABILITY = 13;

    /**
     * grado => [nivel, total, discapacidad, rango de edad]
     *
     * La forma es la de un colegio real: primaria más poblada que media, porque la
     * deserción se nota al subir. El índice de permanencia del ancla es 98,49 %, así que
     * la caída entre 10.º y 11.º es suave, no abrupta.
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: string}>
     */
    private const GRADES = [
        'prejardin' => ['preescolar', 32, 0, '3-4'],
        'jardin' => ['preescolar', 38, 0, '4-5'],
        'transicion' => ['preescolar', 45, 1, '5-6'],

        '1' => ['basica_primaria', 78, 2, '6-7'],
        '2' => ['basica_primaria', 80, 1, '7-8'],
        '3' => ['basica_primaria', 82, 2, '8-9'],
        '4' => ['basica_primaria', 78, 1, '9-10'],
        '5' => ['basica_primaria', 76, 2, '10-11'],

        '6' => ['basica_secundaria', 74, 1, '11-12'],
        '7' => ['basica_secundaria', 72, 1, '12-13'],
        '8' => ['basica_secundaria', 70, 1, '13-14'],
        '9' => ['basica_secundaria', 66, 1, '14-15'],

        '10' => ['media', 52, 0, '15-16'],
        '11' => ['media', 40, 0, '16-17'],
    ];

    /**
     * Filas de `enrollment_snapshots`, desagregadas por grado, sexo y condición como pide
     * el C600.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::GRADES as $grade => [$level, $total, $disability, $ageRange]) {
            // PHP convierte las claves de array numéricas en enteros, así que '1' llega
            // aquí como int 1 y no como string. Se recupera el tipo antes de seguir.
            $grade = (string) $grade;

            $sinCondicion = $total - $disability;

            // El reparto por sexo se hace sobre cada grupo por separado y el resto va al
            // masculino, para que la suma cierre exacta sin redondeos que se pierdan.
            foreach (self::bySex($sinCondicion) as $sex => $headcount) {
                $rows[] = self::row($grade, $level, $ageRange, $sex, 'ninguna', $headcount);
            }

            if ($disability > 0) {
                foreach (self::bySex($disability) as $sex => $headcount) {
                    if ($headcount > 0) {
                        $rows[] = self::row($grade, $level, $ageRange, $sex, 'discapacidad', $headcount);
                    }
                }
            }
        }

        return $rows;
    }

    /** @return array<string, int> */
    private static function bySex(int $total): array
    {
        $femenino = intdiv($total, 2);

        return ['femenino' => $femenino, 'masculino' => $total - $femenino];
    }

    /** @return array<string, mixed> */
    private static function row(
        string $grade,
        string $level,
        string $ageRange,
        string $sex,
        string $condition,
        int $headcount,
    ): array {
        return [
            'school_year' => 2026,
            'level' => $level,
            'grade' => $grade,
            // Jornada única: el ancla declara «mañana y parte de la tarde», Calendario A.
            'shift' => 'completa',
            'sex' => $sex,
            'age_range' => $ageRange,
            'condition' => $condition,
            'educational_model' => 'tradicional',
            'headcount' => $headcount,
            'source' => 'sintetico',
        ];
    }
}
