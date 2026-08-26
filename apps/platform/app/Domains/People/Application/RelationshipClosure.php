<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipRepository;
use App\Domains\People\Domain\Transitions\EndRelationship;
use Illuminate\Support\Carbon;

/**
 * Recorrido J5: cerrar una relación diciendo antes, y con cifras, qué se pierde y qué no.
 *
 * El impacto se **calcula**, no se escribe. Una frase del tipo «esta acción no se puede
 * deshacer» no le dice nada a nadie; «pierde ver las 18 personas de su sede y quedan 2
 * tareas suyas sin dueño» sí, y es lo que permite decidir.
 *
 * La columna de lo que **no** se pierde importa tanto como la otra: buena parte del miedo
 * a cerrar una relación viene de no saber si además desaparece el histórico. Decirlo evita
 * que alguien deje sin cerrar una relación que debía cerrar.
 */
final class RelationshipClosure
{
    public function __construct(private readonly RelationshipRepository $relaciones) {}

    /**
     * Qué pasa si se cierra esta relación. Se calcula al abrir el diálogo, con los números
     * de esta persona concreta.
     *
     * @return array<string, mixed>
     */
    public function impactoDe(Relationship $relacion): array
    {
        $persona = $relacion->person;
        $nombre = trim("{$persona?->given_names} {$persona?->family_names}");
        $usuario = User::where('person_id', $relacion->person_id)->first();

        $roles = $usuario === null ? [] : RoleAssignment::with('role')
            ->where('user_id', $usuario->getKey())
            ->effective(Carbon::today())
            ->get()
            ->map(fn (RoleAssignment $a) => (string) $a->role?->name)
            ->filter()
            ->values()
            ->all();

        // Cuántas personas alcanza hoy por su sede. Es la cifra que hace concreto el
        // «pierde acceso»: sin ella el aviso es una advertencia genérica más.
        $alcanceSede = $relacion->site_id === null ? 0 : Person::query()
            ->whereHas('relationships', fn ($r) => $r->where('site_id', $relacion->site_id))
            ->count();

        $otrasVigentes = $this->relaciones->currentForPerson((string) $relacion->person_id)
            ->reject(fn (Relationship $r) => $r->is($relacion))
            ->count();

        $pierde = [];

        if ($roles !== []) {
            $pierde[] = 'Su rol de <strong>'.e(implode(' y ', $roles)).'</strong>, y con él el acceso a la plataforma';
        }

        if ($alcanceSede > 0) {
            $pierde[] = 'Ver las <strong>'.$alcanceSede.' personas</strong> de su sede';
        }

        $pierde[] = 'Aparecer en la planta vigente del colegio';

        if ($pierde === []) {
            $pierde[] = 'El vínculo laboral con la entidad';
        }

        $conserva = [
            'Su <strong>ficha completa</strong>: una relación se cierra, no se borra',
            'Contar en los reportes del periodo en que estuvo vigente',
            'Todo su <strong>rastro de auditoría</strong>, con la retención de 5 años',
            'La posibilidad de <strong>volver a contratarla</strong>: sería una relación nueva sobre la misma persona',
        ];

        $advertencia = $otrasVigentes > 0
            ? 'Le quedan <strong>'.$otrasVigentes.' relaciones vigentes</strong> más en este colegio. '
                .'Cerrar esta no le quita el acceso mientras alguna de las otras siga abierta.'
            : null;

        return [
            'relacionId' => (string) $relacion->getKey(),
            'persona' => $nombre,
            'sujeto' => trim(($relacion->assignments->sortByDesc('valid_from')->first()?->position?->title ?? 'Sin cargo')
                .' · vigente desde el '.($relacion->valid_from?->format('d/m/Y') ?? '—')),
            'pierde' => $pierde,
            'conserva' => $conserva,
            'advertencia' => $advertencia,
            'tieneAcceso' => $usuario !== null,
        ];
    }

    /**
     * Cierra. El acceso cesa en la **petición siguiente**, no al guardar: el `Authorizer`
     * consulta la relación vigente en cada decisión y no la cachea nunca (CA-07).
     */
    public function cerrar(Relationship $relacion, string $ultimoDia, string $motivo, User $responsable): Relationship
    {
        return $this->relaciones->transition($relacion, new EndRelationship, [
            'valid_to' => $ultimoDia,
            // El motivo alimenta el reporte de rotación y la causal ante el Ministerio.
            // Va al contexto de la auditoría, no a una columna: hoy no hay reporte que lo
            // consuma, y una columna vacía durante meses invita a rellenarla con otra cosa.
            'motivo' => $motivo,
            // Regla 5 de CLAUDE.md: una decisión de terminación siempre tiene detrás una
            // persona identificada, y queda escrita antes de que la operación ocurra.
            'actor_label' => $responsable->email,
        ]);
    }

    /** Los motivos que alimentan el reporte de rotación. Lista cerrada. */
    public static function motivos(): array
    {
        return [
            'renuncia_voluntaria' => 'Renuncia voluntaria',
            'terminacion_contrato' => 'Terminación del contrato',
            'traslado' => 'Traslado a otra sede o entidad',
            'jubilacion' => 'Pensión o jubilación',
            'mutuo_acuerdo' => 'Terminación de mutuo acuerdo',
            'justa_causa' => 'Terminación con justa causa',
        ];
    }
}
