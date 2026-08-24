<?php

declare(strict_types=1);

namespace App\Domains\People\Domain\Transitions;

use App\Domains\People\Domain\Exceptions\InvalidRelationshipTransition;
use App\Domains\People\Domain\Relationship;
use App\Domains\People\Domain\RelationshipStatus;
use App\Domains\Shared\CorrelationId;
use App\Domains\Shared\Domain\AuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * Una transición de la máquina de estados de `relationships`, como clase y no como
 * booleano ni como `switch` dentro de un controlador.
 *
 * Cada transición declara, tal como pide el SPEC: estados de origen admitidos, estado
 * destino, actores permitidos, precondiciones, efectos y evento de auditoría. Tenerlo
 * como clase es lo que permite que B3 pregunte «¿quién puede hacer esto?» sin ejecutar
 * nada, y que la auditoría no dependa de que alguien se acuerde de escribirla.
 *
 * `allowedRoles()` no se comprueba aquí: la autorización es B3 y necesita el
 * AuthorizationContext completo —alcance, relación, clasificación y propósito—, no solo
 * el nombre del rol. Esta clase declara el dato; B3 lo consume.
 */
abstract class RelationshipTransition
{
    abstract public function to(): RelationshipStatus;

    /** @return list<RelationshipStatus> */
    abstract public function allowedFrom(): array;

    abstract public function auditAction(): string;

    /** @return list<string> claves de rol de docs/architecture/permissions.md */
    public function allowedRoles(): array
    {
        return ['owner', 'admin_rrhh'];
    }

    /**
     * Aplica la transición: valida, muta, guarda y audita, todo o nada.
     *
     * @param  array<string, mixed>  $context  datos de la transición (motivo, fecha de cierre…).
     *                                         Nunca contenido P3: va al `context` de auditoría.
     */
    final public function apply(Relationship $relationship, array $context = []): Relationship
    {
        $this->assertTransitionIsLegal($relationship);
        $this->assertPreconditions($relationship, $context);

        return DB::transaction(function () use ($relationship, $context): Relationship {
            $this->applyEffects($relationship, $context);
            $relationship->status = $this->to();
            $relationship->save();

            // Regla 4 de CLAUDE.md: ninguna operación relevante sin AuditEvent.
            // Un cambio de estado de una relación laboral lo es.
            AuditEvent::create([
                'action' => $this->auditAction(),
                'resource_type' => 'relationships',
                'resource_id' => (string) $relationship->getKey(),
                'data_classification' => 'P2',
                'correlation_id' => $context['correlation_id'] ?? CorrelationId::current(),
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'actor_label' => $context['actor_label'] ?? null,
                'purpose' => $context['purpose'] ?? null,
                'result' => 'allowed',
                'source' => $context['source'] ?? 'web',
                'context' => $this->auditContext($relationship, $context),
                'occurred_at' => now(),
            ]);

            return $relationship;
        });
    }

    public function isLegalFrom(RelationshipStatus $status): bool
    {
        return in_array($status, $this->allowedFrom(), strict: true);
    }

    /** @param  array<string, mixed>  $context */
    protected function assertPreconditions(Relationship $relationship, array $context): void
    {
        // Sin precondiciones propias por defecto.
    }

    /** @param  array<string, mixed>  $context */
    protected function applyEffects(Relationship $relationship, array $context): void
    {
        // Sin efectos propios por defecto.
    }

    /**
     * Lo que queda escrito en la auditoría. Solo metadatos del cambio: nunca nombres,
     * documentos ni fechas de nacimiento (data-classification.md, regla 4).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function auditContext(Relationship $relationship, array $context): array
    {
        return [
            'from' => $relationship->getOriginal('status'),
            'to' => $this->to()->value,
        ];
    }

    private function assertTransitionIsLegal(Relationship $relationship): void
    {
        $actual = $relationship->status;

        if ($actual->isTerminal()) {
            throw InvalidRelationshipTransition::terminal($actual);
        }

        if (! $this->isLegalFrom($actual)) {
            throw InvalidRelationshipTransition::from($actual, $this->to(), $this->allowedFrom());
        }
    }
}
