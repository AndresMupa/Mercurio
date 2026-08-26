<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\PersonIdentity;
use App\Domains\People\Domain\PersonRepository;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\People\Domain\Site;
use App\Domains\People\Domain\Transitions\ActivateRelationship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recorrido J1: de buscar un documento a que la persona sume en la planta.
 *
 * La regla del recorrido es que **nadie llegue al final y descubra que faltaba un dato**,
 * así que los cuatro caminos de error viven aquí, resueltos antes de escribir nada, y
 * cada uno devuelve el texto que se le enseña a quien está rellenando el formulario.
 *
 * Los cuatro no son iguales, y la diferencia importa:
 *
 *  - **Documento repetido** y **sede ajena a la entidad** bloquean. El primero crearía dos
 *    fichas de la misma persona y rompería el C600; el segundo cuelga la relación de una
 *    sede que no es de esa entidad y rompe el aislamiento por sede del coordinador.
 *  - **Menor de edad** bloquea, y con un mensaje que explica el porqué en vez de dar un
 *    error técnico: esta plataforma no guarda identidad de menores (ADR 0003), y hay un
 *    trigger en base de datos que lo impide de todas formas. Este aviso existe para que
 *    la persona entienda qué pasó y sepa a dónde ir.
 *  - **Relación solapada** solo advierte. Un traslado dentro del mismo día es legítimo, y
 *    quien decide es la persona con el dato delante.
 */
final class PersonRegistration
{
    public function __construct(
        private readonly PersonRepository $personas,
        private readonly RelationshipRepository $relaciones,
    ) {}

    /**
     * Busca a alguien por documento antes de crearlo. Es el paso 1 de J1 y existe para
     * que el error de «documento repetido» casi nunca llegue al final del formulario.
     *
     * @return array{encontrada: bool, id: ?string, nombre: ?string, estado: ?string}
     */
    public function buscarPorDocumento(string $documento): array
    {
        $identidad = PersonIdentity::with('person.relationships')
            ->where('document_number', trim($documento))
            ->first();

        if ($identidad?->person === null) {
            return ['encontrada' => false, 'id' => null, 'nombre' => null, 'estado' => null];
        }

        $persona = $identidad->person;
        $vigente = $persona->relationships->first(fn (Relationship $r) => $r->isCurrentOn(Carbon::today()));

        return [
            'encontrada' => true,
            'id' => (string) $persona->getKey(),
            'nombre' => trim("{$persona->given_names} {$persona->family_names}"),
            'estado' => $vigente?->status->value ?? 'sin_relacion_vigente',
        ];
    }

    /**
     * Los problemas de un alta, antes de escribir nada.
     *
     * @param  array<string, mixed>  $datos
     * @return array{bloqueos: array<string, string>, avisos: array<string, array<string, mixed>>}
     */
    public function revisar(array $datos): array
    {
        $bloqueos = [];
        $avisos = [];

        // ── 1 · documento ya registrado ─────────────────────────────────
        if (! empty($datos['document_number'])) {
            $existente = $this->buscarPorDocumento((string) $datos['document_number']);

            if ($existente['encontrada']) {
                $bloqueos['document_number'] = "El documento {$datos['document_number']} ya está en "
                    ."{$existente['nombre']}. Abre esa ficha en vez de crear una segunda.";
            }
        }

        // ── 2 · menor de edad ───────────────────────────────────────────
        if (! empty($datos['birth_date']) && $this->esMenor((string) $datos['birth_date'])) {
            $bloqueos['birth_date'] = 'Esta plataforma no guarda datos de menores de edad. '
                .'Los estudiantes se registran como conteos en Matrícula, sin nombre ni documento.';
        }

        // ── 4 · sede ajena a la entidad jurídica ────────────────────────
        if (! empty($datos['site_id']) && ! empty($datos['legal_entity_id'])) {
            $sede = Site::find($datos['site_id']);

            if ($sede !== null && (string) $sede->legal_entity_id !== (string) $datos['legal_entity_id']) {
                $bloqueos['site_id'] = "La sede «{$sede->name}» pertenece a otra entidad jurídica. "
                    .'Elige una de las de la entidad seleccionada, o cambia la entidad.';
            }
        }

        // ── 3 · relación solapada · advierte, no bloquea ────────────────
        if (! empty($datos['person_id']) && ! empty($datos['valid_from'])) {
            $solape = $this->relacionQueSeSolapa(
                (string) $datos['person_id'],
                (string) $datos['legal_entity_id'],
                (string) $datos['valid_from'],
            );

            if ($solape !== null) {
                $avisos['valid_from'] = [
                    'texto' => 'Esta persona ya figura con una relación vigente desde el '
                        .$solape->valid_from->format('d/m/Y').', sin fecha de fin. Dos vínculos '
                        .'vigentes a la vez en la misma entidad no es algo que el C600 sepa reportar.',
                    'relacionId' => (string) $solape->getKey(),
                    // La corrección de un clic, con la fecha ya calculada.
                    'cerrarEl' => Carbon::parse($datos['valid_from'])->subDay()->toDateString(),
                ];
            }
        }

        return ['bloqueos' => $bloqueos, 'avisos' => $avisos];
    }

    /**
     * Crea persona, identidad, relación y asignación en una transacción.
     *
     * Todo o nada: una persona sin identidad no se puede reportar en el C600, y una
     * relación sin asignación no dice qué hace. Dejar el alta a medias por un fallo en el
     * último paso obliga a alguien a limpiar a mano lo que quedó.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos): Person
    {
        return DB::transaction(function () use ($datos) {
            $persona = $this->personas->create([
                'given_names' => $datos['given_names'],
                'family_names' => $datos['family_names'],
                'birth_date' => $datos['birth_date'],
                'sex' => $datos['sex'] ?? 'no_informado',
                'education_level' => $datos['education_level'] ?? null,
                'teaching_statute' => $datos['teaching_statute'] ?? null,
                'teaching_grade' => $datos['teaching_grade'] ?? null,
                'status' => 'active',
            ]);

            $this->personas->addIdentity(
                $persona,
                (string) ($datos['document_type'] ?? 'cedula'),
                (string) $datos['document_number'],
            );

            $relacion = $this->relaciones->startPlanned([
                'person_id' => $persona->getKey(),
                'legal_entity_id' => $datos['legal_entity_id'],
                'site_id' => $datos['site_id'],
                'type' => $datos['type'] ?? 'empleado',
                'employment_type' => $datos['employment_type'] ?? 'indefinido',
                'valid_from' => $datos['valid_from'],
            ]);

            // Nace planeada y se activa por la máquina de estados, no asignando `status`:
            // así el alta deja `relationship.activated` en la auditoría, que es el evento
            // que un inspector busca para saber desde cuándo esta persona trabaja aquí.
            if (($datos['activar'] ?? true) && Carbon::parse($datos['valid_from'])->lte(Carbon::today())) {
                $relacion = $this->relaciones->transition(
                    $relacion,
                    new ActivateRelationship,
                );
            }

            $relacion->assignments()->create([
                'position_id' => $datos['position_id'],
                'teaching_level' => $datos['teaching_level'] ?? null,
                'weekly_hours' => $datos['weekly_hours'] ?? null,
                'valid_from' => $datos['valid_from'],
            ]);

            return $persona->fresh(['identities', 'relationships']);
        });
    }

    private function esMenor(string $nacimiento): bool
    {
        return Carbon::parse($nacimiento)->diffInYears(Carbon::today()) < 18;
    }

    private function relacionQueSeSolapa(string $personaId, string $entidadId, string $desde): ?Relationship
    {
        $inicio = Carbon::parse($desde)->toImmutable()->startOfDay();

        return $this->relaciones->forPerson($personaId)
            ->first(function (Relationship $r) use ($entidadId, $inicio) {
                if ((string) $r->legal_entity_id !== $entidadId) {
                    return false;
                }

                if ($r->status === RelationshipStatus::Ended) {
                    return false;
                }

                // Se solapa si la anterior no ha terminado, o termina después de que
                // empiece la nueva.
                return $r->valid_to === null || $r->valid_to->gte($inicio);
            });
    }
}
