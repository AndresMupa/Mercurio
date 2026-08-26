<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\People\Domain\Person;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use Illuminate\Support\Carbon;

/**
 * La planta del colegio, lista para pintar en una tabla.
 *
 * **Los documentos salen enmascarados de aquí, no de la vista.** Es la decisión 2 del
 * lienzo de B7, y ponerla en el servidor y no en el componente importa: un dato que viaja
 * completo hasta el navegador ya salió, aunque el CSS lo tape. Lo que llega a Inertia son
 * los cuatro últimos dígitos y nada más; el número entero solo se sirve por la ruta que
 * pasa por el diálogo de propósito y deja evento.
 *
 * Ordenar y filtrar ocurren en SQL, no en PHP: una tabla que ordena en memoria solo ordena
 * la página que ya descargó, y eso es mentira en cuanto hay 64 filas y se ven 16.
 */
final class PersonDirectory
{
    private const POR_PAGINA = 25;

    /** Columnas por las que se puede ordenar. Lista blanca: el nombre viene de la URL. */
    private const ORDENABLES = ['nombre' => 'family_names', 'desde' => 'valid_from'];

    /*
     * Búsqueda insensible a tildes con `translate()` y no con la extensión `unaccent`.
     *
     * `unaccent` es mejor —es indexable— pero instalarla exige superusuario, y el rol que
     * corre las migraciones no lo es a propósito. Depender de que alguien la instale a mano
     * antes del despliegue es una trampa que se dispara en producción, no en desarrollo.
     *
     * No es un adorno: media planta del colegio se apellida Ibáñez, Bermúdez o Rincón, y
     * nadie escribe las tildes en un buscador. Sin esto, buscar «ibanez» no encuentra a
     * nadie y la persona concluye que la ficha no existe.
     */
    private const LETRAS = 'áàäâãéèëêíìïîóòöôõúùüûñçÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ';

    private const LLANAS = 'aaaaaeeeeiiiiooooouuuuncAAAAAEEEEIIIIOOOOOUUUUNC';

    private const SIN_TILDES = "translate(given_names || ' ' || family_names, '"
        .self::LETRAS."', '".self::LLANAS."')";

    private const SIN_TILDES_PARAM = "translate(?, '".self::LETRAS."', '".self::LLANAS."')";

    /**
     * @param  array{busqueda?: ?string, estado?: ?string, orden?: ?string, sentido?: ?string, pagina?: ?int}  $filtros
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, orden: array{columna: string, sentido: string}}
     */
    public function listar(array $filtros = []): array
    {
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        $estado = $filtros['estado'] ?? null;
        $columna = array_key_exists($filtros['orden'] ?? '', self::ORDENABLES) ? $filtros['orden'] : 'nombre';
        $sentido = ($filtros['sentido'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));

        $consulta = Person::query()
            ->with([
                'identities',
                'relationships' => fn ($q) => $q->with(['assignments.position', 'site']),
            ]);

        if ($busqueda !== '') {
            // El documento se busca por coincidencia exacta y no por `LIKE`: buscar
            // fragmentos de documento permitiría barrer el rango entero probando prefijos,
            // que es enumeración de datos P3 disfrazada de búsqueda.
            $consulta->where(function ($q) use ($busqueda) {
                $q->whereRaw(
                    self::SIN_TILDES.' ILIKE '.self::SIN_TILDES_PARAM,
                    ["%{$busqueda}%"]
                )->orWhereHas('identities', fn ($i) => $i->where('document_number', $busqueda));
            });
        }

        if ($estado !== null && $estado !== 'todas') {
            $consulta->whereHas('relationships', fn ($r) => $r->where('status', $estado));
        }

        $total = (clone $consulta)->count();

        $personas = $consulta
            ->orderBy(self::ORDENABLES[$columna] ?? 'family_names', $sentido)
            ->orderBy('given_names', $sentido)
            ->forPage($pagina, self::POR_PAGINA)
            ->get();

        return [
            'filas' => $personas->map(fn (Person $p) => $this->fila($p))->all(),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
            'orden' => ['columna' => $columna, 'sentido' => $sentido],
        ];
    }

    /** Recuento por estado para la cabecera. Tiene que cuadrar con el total del pie. */
    public function recuento(): array
    {
        $porEstado = [];

        foreach (RelationshipStatus::cases() as $estado) {
            $porEstado[$estado->value] = Person::query()
                ->whereHas('relationships', fn ($r) => $r->where('status', $estado->value))
                ->count();
        }

        return $porEstado;
    }

    /** @return array<string, mixed> */
    private function fila(Person $persona): array
    {
        $relacion = $this->relacionMasReciente($persona);
        $asignacion = $relacion?->assignments->sortByDesc('valid_from')->first();
        $documento = $persona->identities->first()?->document_number;

        return [
            'id' => (string) $persona->getKey(),
            'nombre' => trim("{$persona->given_names} {$persona->family_names}"),
            // Solo los cuatro últimos dígitos salen del servidor. Ver la cabecera.
            'documento' => $documento === null ? null : '•••• '.mb_substr($documento, -4),
            'cargo' => $asignacion?->position?->title,
            'tipo' => $this->enPalabras($asignacion?->position?->personnel_type),
            'estatuto' => $this->estatutoCorto($persona->teaching_statute),
            'estado' => $relacion?->status?->value,
            'desde' => $relacion?->valid_from?->toDateString(),
            // La fila se marca cuando le falta un dato que bloquea el C600. El motivo va
            // en su propia columna, no en la de estado: son dos cosas distintas y
            // mezclarlas fue uno de los defectos que encontró la revisión de B7.
            'atencion' => $documento === null,
            'apagada' => $relacion?->status === RelationshipStatus::Ended,
            'etiquetaAccesible' => trim("{$persona->given_names} {$persona->family_names}"),
        ];
    }

    /**
     * Una persona puede tener varias relaciones (invariante 4). Manda la vigente; si no
     * hay ninguna, la última que hubo, para que una persona cerrada siga contando su
     * historia en vez de aparecer en blanco.
     */
    private function relacionMasReciente(Person $persona): ?Relationship
    {
        $hoy = Carbon::today();

        return $persona->relationships->first(fn (Relationship $r) => $r->isCurrentOn($hoy))
            ?? $persona->relationships->sortByDesc('valid_from')->first();
    }

    private function enPalabras(?string $tipo): ?string
    {
        return $tipo === null ? null : ucfirst(str_replace('_', ' ', $tipo));
    }

    /** `1278_2002` → `1278`, que es como se nombra en la conversación real del colegio. */
    private function estatutoCorto(?string $estatuto): ?string
    {
        return $estatuto === null ? null : explode('_', $estatuto)[0];
    }
}
