<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\Shared\Domain\AuditEvent;
use Illuminate\Support\Carbon;

/**
 * La ficha de una persona, con los datos sensibles ya enmascarados.
 *
 * Lo mismo que en el listado: lo que viaja al navegador son las máscaras. Y una cosa más
 * que el lienzo de B7 decidió y que casi ningún producto hace: **la ficha incluye quién ha
 * leído sus datos**, con la misma lista que se le entregaría a la titular si la pidiera.
 * Que ella también pueda verla es el punto —la auditoría no es solo para el inspector—.
 */
final class PersonFile
{
    /** @return array<string, mixed> */
    public function de(Person $persona): array
    {
        $persona->loadMissing(['identities', 'relationships.assignments.position', 'relationships.site']);

        $relacion = $this->relacionPrincipal($persona);
        $asignacion = $relacion?->assignments->sortByDesc('valid_from')->first();
        $documento = $persona->identities->first();

        return [
            'id' => (string) $persona->getKey(),
            'nombre' => trim("{$persona->given_names} {$persona->family_names}"),
            'nombres' => $persona->given_names,
            'apellidos' => $persona->family_names,
            'nivelEducativo' => $this->enPalabras($persona->education_level),
            'estatuto' => $this->estatutoEnPalabras($persona->teaching_statute),
            'grado' => $persona->teaching_grade,

            // Los tres P3, enmascarados. El valor entero se pide por la ruta que audita.
            'sensibles' => [
                'documento' => [
                    'tipo' => $this->enPalabras($documento?->document_type),
                    'mascara' => $documento === null ? null : '•••• '.mb_substr($documento->document_number, -4),
                    'falta' => $documento === null,
                ],
                'nacimiento' => ['mascara' => $persona->birth_date === null ? null : '••·••·••••'],
                'sexo' => ['mascara' => $persona->sex === null ? null : '••••••'],
            ],

            'relacion' => $relacion === null ? null : [
                'id' => (string) $relacion->getKey(),
                'estado' => $relacion->status->value,
                'tipo' => $this->enPalabras($relacion->type),
                'vinculacion' => $this->enPalabras($relacion->employment_type),
                'desde' => $relacion->valid_from?->toDateString(),
                'hasta' => $relacion->valid_to?->toDateString(),
                'sede' => $relacion->site?->name,
                'vigente' => $relacion->isCurrentOn(Carbon::today()),
                'cargo' => $asignacion?->position?->title,
                'nivelEnsenanza' => $this->enPalabras($asignacion?->teaching_level),
                'horas' => $asignacion?->weekly_hours,
            ],

            // La máquina de estados, para pintar por dónde va y por dónde puede ir.
            'recorrido' => $this->recorrido($relacion),
        ];
    }

    /**
     * Quién ha mirado los datos de esta persona.
     *
     * Incluye los intentos **denegados** a propósito: una traza que solo guarda los
     * aciertos no sirve ante una inspección, y para la titular es justo lo que quiere
     * saber —quién intentó verlo y no pudo—.
     *
     * @return list<array<string, mixed>>
     */
    public function rastroDe(Person $persona, int $limite = 12): array
    {
        return AuditEvent::query()
            ->where('resource_id', (string) $persona->getKey())
            ->whereIn('data_classification', ['P3', 'P4'])
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(fn (AuditEvent $evento) => [
                'cuando' => $evento->occurred_at?->toIso8601String(),
                'permitido' => $evento->result === 'allowed',
                'quien' => $evento->actor_label ?? 'Sin identificar',
                'que' => $this->accionEnPalabras((string) $evento->action),
                'proposito' => $evento->purpose,
                'regla' => $evento->context['rule'] ?? null,
            ])
            ->all();
    }

    /**
     * Manda la vigente; si no hay, la última. Una persona cerrada tiene que seguir
     * contando su historia en vez de aparecer en blanco.
     */
    private function relacionPrincipal(Person $persona): ?Relationship
    {
        $hoy = Carbon::today();

        return $persona->relationships->first(fn (Relationship $r) => $r->isCurrentOn($hoy))
            ?? $persona->relationships->sortByDesc('valid_from')->first();
    }

    /** @return list<array{estado: string, etiqueta: string, alcanzado: bool, cuando: ?string}> */
    private function recorrido(?Relationship $relacion): array
    {
        $actual = $relacion?->status;

        $alcanzado = match ($actual) {
            RelationshipStatus::Planned => [RelationshipStatus::Planned],
            RelationshipStatus::Active => [RelationshipStatus::Planned, RelationshipStatus::Active],
            RelationshipStatus::Suspended => [RelationshipStatus::Planned, RelationshipStatus::Active, RelationshipStatus::Suspended],
            RelationshipStatus::Ended => [RelationshipStatus::Planned, RelationshipStatus::Active, RelationshipStatus::Ended],
            default => [],
        };

        $etiquetas = [
            RelationshipStatus::Planned->value => 'Planeada',
            RelationshipStatus::Active->value => 'Vigente',
            RelationshipStatus::Suspended->value => 'Suspendida',
            RelationshipStatus::Ended->value => 'Cerrada',
        ];

        $hitos = [];

        foreach (RelationshipStatus::cases() as $estado) {
            $hitos[] = [
                'estado' => $estado->value,
                'etiqueta' => $etiquetas[$estado->value],
                'alcanzado' => in_array($estado, $alcanzado, true),
                'cuando' => match (true) {
                    $estado === RelationshipStatus::Active => $relacion?->valid_from?->toDateString(),
                    $estado === RelationshipStatus::Ended => $relacion?->valid_to?->toDateString(),
                    default => null,
                },
            ];
        }

        return $hitos;
    }

    private function accionEnPalabras(string $accion): string
    {
        return match (true) {
            str_starts_with($accion, 'identities.document_number') => 'Vio el documento',
            str_starts_with($accion, 'people.birth_date') => 'Vio la fecha de nacimiento',
            str_starts_with($accion, 'people.sex') => 'Vio el sexo registrado',
            default => $accion,
        };
    }

    private function enPalabras(?string $valor): ?string
    {
        return $valor === null ? null : ucfirst(str_replace('_', ' ', $valor));
    }

    private function estatutoEnPalabras(?string $estatuto): ?string
    {
        return match ($estatuto) {
            '1278_2002' => 'Decreto 1278 de 2002',
            '2277_1979' => 'Decreto 2277 de 1979',
            '804_1995' => 'Decreto 804 de 1995',
            default => null,
        };
    }
}
