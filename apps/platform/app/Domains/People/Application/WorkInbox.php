<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\Identity\Application\EffectiveRoles;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La bandeja: lo que le toca a alguien, con responsable, siguiente acción y plazo.
 *
 * Los asuntos **se derivan del estado real**, no de una tabla de tareas. Una tabla de
 * tareas se desincroniza —alguien carga el documento que faltaba y la tarea sigue ahí— y
 * la primera vez que eso pasa la gente deja de creerse la bandeja. Aquí, si el documento
 * aparece, el asunto desaparece solo.
 *
 * Cada asunto trae `propio`: la bandeja tiene que distinguir de un vistazo lo que te toca
 * a ti de lo que estás esperando de otra persona. Sin esa distinción, una bandeja con
 * seis cosas de las que solo dos son tuyas se lee como seis pendientes.
 */
final class WorkInbox
{
    private const DIAS_AVISO_VENCIMIENTO = 30;

    public function __construct(private readonly EffectiveRoles $roles) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function para(User $usuario): array
    {
        $esDireccion = $this->tieneRol($usuario, ['rector']);
        $administra = $this->tieneRol($usuario, ['owner', 'admin_rrhh']);

        $asuntos = [];

        // ── Relaciones suspendidas sin resolver ─────────────────────────
        foreach ($this->suspendidasHaceTiempo() as $relacion) {
            $asuntos[] = [
                'id' => 'suspendida-'.$relacion->getKey(),
                'tono' => 'critico',
                'estado' => 'Sin resolver',
                'icono' => 'pausa',
                'titulo' => $this->nombreDe($relacion->person).' lleva '
                    .$this->mesesDesde($relacion->updated_at).' meses suspendida sin resolución',
                'detalle' => 'Reanudar o cerrar exige registrar cómo se resolvió el motivo',
                'responsable' => $administra ? 'Tú' : 'Talento humano',
                'propio' => $administra,
                'esRol' => ! $administra,
                'accion' => $administra ? 'Reactivar o cerrar' : null,
                'ruta' => $administra ? '/personas/'.$relacion->person_id : null,
                'vence' => null,
            ];
        }

        // ── Personas sin documento: bloquean el C600 ────────────────────
        $sinDocumento = Person::withoutIdentity()->count();

        if ($sinDocumento > 0) {
            $asuntos[] = [
                'id' => 'sin-documento',
                'tono' => 'aviso',
                'estado' => 'Incompleto',
                'titulo' => $sinDocumento.' '.($sinDocumento === 1 ? 'persona' : 'personas')
                    .' sin documento de identidad',
                'detalle' => 'El C600 no se puede emitir sin este dato',
                'responsable' => $administra ? 'Tú' : 'Talento humano',
                'propio' => $administra,
                'esRol' => ! $administra,
                'accion' => $administra ? 'Ver quiénes son' : 'Ver quiénes son',
                'ruta' => '/personas?faltantes=documento',
                'vence' => null,
            ];
        }

        // ── Relaciones a término fijo por vencer ────────────────────────
        foreach ($this->porVencer() as $relacion) {
            $asuntos[] = [
                'id' => 'vence-'.$relacion->getKey(),
                'tono' => 'aviso',
                'estado' => 'Por vencer',
                'titulo' => 'La relación de '.$this->nombreDe($relacion->person).' termina pronto',
                'detalle' => 'Renovar o cerrar antes de la fecha, o el acceso queda vivo sin vínculo',
                'responsable' => $administra ? 'Tú' : 'Talento humano',
                'propio' => $administra,
                'esRol' => ! $administra,
                'accion' => 'Ver la relación',
                'ruta' => '/personas/'.$relacion->person_id,
                'vence' => $relacion->valid_to?->toDateString(),
            ];
        }

        // ── Lo que espera a dirección ───────────────────────────────────
        // Se muestra a los dos lados: a dirección como suyo, a talento humano como
        // «no puedes avanzarlo tú». Una tarea que solo ve quien no puede hacerla es una
        // tarea que se queda parada.
        $asuntos[] = [
            'id' => 'c600-firma',
            'tono' => $esDireccion ? 'critico' : 'neutro',
            'estado' => $esDireccion ? 'Espera tu firma' : 'En espera',
            'icono' => $esDireccion ? null : 'reloj',
            'titulo' => 'Formulario C600 del año lectivo en curso',
            'detalle' => $esDireccion
                ? 'Mientras no lo firmes, nadie más puede avanzarlo'
                : 'Tú ya no puedes avanzarlo: falta la firma de dirección',
            'responsable' => $esDireccion ? 'Tú' : 'Rector',
            'propio' => $esDireccion,
            'esRol' => ! $esDireccion,
            'accion' => $esDireccion ? 'Revisar y firmar' : 'Recordar por correo',
            'ruta' => null,
            'vence' => Carbon::today()->year.'-09-30',
        ];

        return $asuntos;
    }

    /** Cuántos asuntos son suyos. Es el número del rail. */
    public function pendientesDe(User $usuario): int
    {
        return count(array_filter($this->para($usuario), fn ($a) => $a['propio']));
    }

    public function hayVencidosDe(User $usuario): bool
    {
        foreach ($this->para($usuario) as $asunto) {
            if ($asunto['propio'] && $asunto['tono'] === 'critico') {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Relationship> */
    private function suspendidasHaceTiempo()
    {
        return Relationship::with('person')
            ->where('status', RelationshipStatus::Suspended->value)
            ->get();
    }

    /** @return Collection<int, Relationship> */
    private function porVencer()
    {
        return Relationship::with('person')
            ->where('status', RelationshipStatus::Active->value)
            ->whereNotNull('valid_to')
            ->whereBetween('valid_to', [
                Carbon::today()->toDateString(),
                Carbon::today()->addDays(self::DIAS_AVISO_VENCIMIENTO)->toDateString(),
            ])
            ->get();
    }

    private function nombreDe(?Person $persona): string
    {
        return $persona === null ? 'Una persona' : trim("{$persona->given_names} {$persona->family_names}");
    }

    private function mesesDesde($fecha): int
    {
        return $fecha === null ? 0 : (int) Carbon::parse($fecha)->diffInMonths(Carbon::now());
    }

    /** @param  list<string>  $claves */
    private function tieneRol(User $usuario, array $claves): bool
    {
        foreach ($this->roles->of($usuario) as $rol) {
            if (in_array($rol->value, $claves, true)) {
                return true;
            }
        }

        return false;
    }
}
