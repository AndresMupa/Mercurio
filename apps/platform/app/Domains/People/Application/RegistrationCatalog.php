<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\People\Domain\LegalEntity;
use App\Domains\People\Domain\Position;
use App\Domains\People\Domain\Site;

/**
 * Las listas que el formulario de alta necesita: entidades, sedes y cargos del tenant.
 *
 * **Las sedes vienen agrupadas por entidad jurídica.** No es un detalle de presentación:
 * es lo que hace que el error 4 de J1 —«esa sede pertenece a otra entidad»— casi nunca
 * ocurra, porque el selector solo ofrece las de la entidad elegida. El error sigue
 * existiendo en el servidor para la carga masiva, donde nadie elige de una lista.
 */
final class RegistrationCatalog
{
    /** @return array<string, mixed> */
    public function paraFormulario(): array
    {
        $entidades = LegalEntity::query()->orderBy('name')->get();
        $sedes = Site::query()->orderBy('name')->get();
        $cargos = Position::query()->orderBy('title')->get();

        return [
            'entidades' => $entidades->map(fn (LegalEntity $e) => [
                'id' => (string) $e->getKey(),
                'nombre' => $e->name,
            ])->all(),

            'sedes' => $sedes->map(fn (Site $s) => [
                'id' => (string) $s->getKey(),
                'entidadId' => (string) $s->legal_entity_id,
                'nombre' => $s->name,
            ])->all(),

            'cargos' => $cargos->map(fn (Position $p) => [
                'id' => (string) $p->getKey(),
                'entidadId' => (string) $p->legal_entity_id,
                'titulo' => $p->title,
                'tipo' => $p->personnel_type,
            ])->all(),

            // Vocabularios cerrados. Van del servidor porque son los mismos que valida el
            // dominio: una lista escrita aparte en el formulario se desincroniza sola.
            'tiposDocumento' => [
                'cedula' => 'Cédula de ciudadanía',
                'cedula_extranjeria' => 'Cédula de extranjería',
                'pasaporte' => 'Pasaporte',
                'permiso_permanencia' => 'Permiso por protección temporal',
            ],
            'vinculaciones' => [
                'indefinido' => 'Término indefinido',
                'fijo' => 'Término fijo',
                'obra_labor' => 'Obra o labor',
                'prestacion_servicios' => 'Prestación de servicios',
            ],
            'tiposVinculo' => [
                'empleado' => 'Empleado',
                'contratista' => 'Contratista',
                'aprendiz' => 'Aprendiz o pasante',
            ],
            'nivelesEducativos' => [
                'sin_titulacion' => 'Sin titulación',
                'bachillerato_pedagogico' => 'Bachillerato pedagógico',
                'normalista_superior' => 'Normalista superior',
                'licenciado' => 'Licenciatura o profesional',
                'posgrado' => 'Posgrado',
            ],
            'estatutos' => [
                '1278_2002' => 'Decreto 1278 de 2002',
                '2277_1979' => 'Decreto 2277 de 1979',
                '804_1995' => 'Decreto 804 de 1995',
            ],
            'nivelesEnsenanza' => [
                'preescolar' => 'Preescolar',
                'basica_primaria' => 'Básica primaria',
                'basica_secundaria' => 'Básica secundaria',
                'media' => 'Media',
            ],
        ];
    }
}
