<?php

declare(strict_types=1);

namespace App\Domains\People\Application;

use App\Domains\Identity\Application\Authorizer;
use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\Authorization\Purpose;
use App\Domains\Identity\Domain\User;
use App\Domains\People\Domain\Person;
use App\Domains\Shared\Domain\DataClassification;
use DomainException;

/**
 * La única puerta por la que sale un dato P3 completo.
 *
 * Todo lo demás —la tabla, la ficha, el buscador— sirve los datos sensibles enmascarados.
 * Ver uno entero pasa por aquí, y aquí pasa por el `Authorizer`, que decide y **audita**:
 * CA-04 si deniega, CA-05 si permite. No hay un camino corto, y eso es el punto.
 *
 * Que sean tres campos y no «cualquier campo» también es deliberado: un lector genérico
 * que acepte el nombre de la columna por parámetro es una puerta por la que mañana sale
 * `password` porque alguien pasó la cadena equivocada.
 */
final class SensitiveFieldReader
{
    /** campo público => [recurso de la matriz, cómo nombrarlo en pantalla] */
    private const CAMPOS = [
        'documento' => ['identities.document_number', 'el documento'],
        'nacimiento' => ['people.birth_date', 'la fecha de nacimiento'],
        'sexo' => ['people.sex', 'el sexo registrado'],
    ];

    public function __construct(private readonly Authorizer $authorizer) {}

    /** @return list<string> */
    public static function camposConocidos(): array
    {
        return array_keys(self::CAMPOS);
    }

    public static function enPalabras(string $campo): string
    {
        return self::CAMPOS[$campo][1] ?? 'este dato';
    }

    /**
     * Devuelve el valor completo, o null si la decisión fue denegar.
     *
     * Devolver null y no lanzar es deliberado: quien llama tiene que decidir qué contarle
     * a la persona, y el motivo real ya quedó en `audit_events`. Un mensaje distinto por
     * cada regla que denegó le enseñaría a quien prueba dónde está el borde del permiso.
     */
    public function leer(User $usuario, string $tenantId, Person $persona, string $campo, Purpose $proposito): ?string
    {
        if (! array_key_exists($campo, self::CAMPOS)) {
            throw new DomainException("«{$campo}» no es un campo sensible declarado.");
        }

        [$recurso] = self::CAMPOS[$campo];

        $decision = $this->authorizer->authorize(new AuthorizationContext(
            user: $usuario,
            tenantId: $tenantId,
            resource: $recurso,
            action: 'leer',
            resourceTenantId: (string) $persona->tenant_id,
            resourcePersonId: (string) $persona->getKey(),
            purpose: $proposito,
            dataClassification: DataClassification::P3,
        ));

        if (! $decision->allowed) {
            return null;
        }

        return match ($campo) {
            'documento' => $persona->identities->first()?->document_number,
            'nacimiento' => $persona->birth_date?->toDateString(),
            'sexo' => $persona->sex,
        };
    }

    /**
     * Los propósitos que se le ofrecen a alguien, con el sugerido según de dónde venga.
     *
     * El sugerido no es un capricho de interfaz: el diálogo tiene que resolverse en un
     * clic o la gente aprende a elegir siempre el primero, y entonces el registro deja de
     * significar nada. Se sugiere el que corresponde al contexto, no el más permisivo.
     *
     * @return list<array{valor: string, titulo: string, sugerido: bool}>
     */
    public function propositosPara(string $contexto = 'ficha'): array
    {
        $sugerido = match ($contexto) {
            'reporte' => Purpose::ReporteC600,
            'evi' => Purpose::AutoevaluacionEvi,
            'titular' => Purpose::SolicitudDelTitular,
            'auditoria' => Purpose::AuditoriaInterna,
            default => Purpose::GestionLaboral,
        };

        // Pares y no un mapa: un `enum` no puede ser clave de array en PHP, y escribirlo
        // así compila pero revienta al recorrerlo.
        $catalogo = [
            [Purpose::GestionLaboral, 'Gestión laboral'],
            [Purpose::ReporteC600, 'Reporte C600 al DANE'],
            [Purpose::AutoevaluacionEvi, 'Autoevaluación EVI'],
            [Purpose::GestionSst, 'Gestión de seguridad y salud'],
            [Purpose::SolicitudDelTitular, 'Solicitud del titular'],
            [Purpose::AuditoriaInterna, 'Auditoría interna'],
        ];

        $propositos = [];

        foreach ($catalogo as [$proposito, $titulo]) {
            $propositos[] = [
                'valor' => $proposito->value,
                'titulo' => $titulo,
                'sugerido' => $proposito === $sugerido,
            ];
        }

        // El sugerido primero: es el que se va a elegir en el 90 % de los casos y bajarlo
        // al medio de la lista obliga a leerla entera cada vez.
        usort($propositos, fn ($a, $b) => $b['sugerido'] <=> $a['sugerido']);

        return $propositos;
    }
}
