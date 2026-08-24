<?php

declare(strict_types=1);

namespace App\Domains\Identity\Application;

use App\Domains\Identity\Domain\Authorization\AuthorizationContext;
use App\Domains\Identity\Domain\Authorization\AuthorizationDecision;
use App\Domains\Identity\Domain\Authorization\Permission;
use App\Domains\Identity\Domain\Authorization\PermissionMatrix;
use App\Domains\Identity\Domain\Authorization\RoleKey;
use App\Domains\Identity\Domain\RoleAssignment;
use App\Domains\People\Domain\Relationship;
use App\Domains\Shared\Application\AuditRecorder;
use App\Domains\Shared\Domain\DataClassification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Punto único de decisión de autorización. RBAC **+** ABAC: el rol entra en la decisión,
 * no la toma.
 *
 * El orden de las comprobaciones importa y sigue al de `permissions.md`. El tenant se
 * mira **antes** que el rol, porque un acierto de rol sobre un recurso de otro tenant no
 * es un permiso concedido: es una fuga. Comprobarlo primero significa que ninguna
 * combinación de roles puede producir acceso cruzado (invariante 1 de la matriz).
 *
 * Toda denegación deja `AuditEvent` con `result = denied` (CA-04), y toda lectura P3
 * autorizada deja el suyo con propósito, actor, clasificación y correlación (CA-05). No
 * es opcional ni configurable: si auditar fallara, la operación falla.
 */
final class Authorizer
{
    // Un solo punto de escritura de auditoría en todo el sistema (B4): así ningún
    // evento depende de que su autor recordara qué campos rellenar.
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function authorize(AuthorizationContext $context): AuthorizationDecision
    {
        $decision = $this->decide($context);

        // Misma clasificación efectiva que usó la decisión: si la auditoría usara la que
        // vino en el contexto, una lectura P3 con la clasificación omitida se registraría
        // como si no fuera sensible —o no se registraría en absoluto—.
        $this->record(
            $context,
            $decision,
            PermissionMatrix::classificationOf($context->resource, $context->dataClassification),
        );

        return $decision;
    }

    public function allows(AuthorizationContext $context): bool
    {
        return $this->authorize($context)->allowed;
    }

    private function decide(AuthorizationContext $context): AuthorizationDecision
    {
        // ── ABAC 1 · el tenant, antes que nada ──────────────────────────────
        if ($context->resourceTenantId !== null && $context->resourceTenantId !== $context->tenantId) {
            return AuthorizationDecision::deny(
                'El recurso pertenece a otro tenant.', 'abac.tenant'
            );
        }

        if ($context->user->tenant_id !== $context->tenantId) {
            return AuthorizationDecision::deny(
                'El usuario no pertenece al tenant del contexto.', 'abac.tenant'
            );
        }

        // Un recurso o una acción que la matriz no conoce se deniega. Falla cerrada:
        // un recurso nuevo no nace accesible por no haberlo declarado.
        if (! PermissionMatrix::knows($context->resource, $context->action)) {
            return AuthorizationDecision::deny(
                "La matriz no declara «{$context->action}» sobre «{$context->resource}».",
                'matrix.undeclared'
            );
        }

        // ── CA-07 · la relación laboral vigente sostiene el acceso ──────────
        //
        // Un `role_assignment` vigente no basta si la persona ya no trabaja aquí. Cerrar
        // la relación tiene que cortar el acceso **en la siguiente petición**, sin
        // esperar a que alguien se acuerde de revocar el rol y sin cerrar la sesión.
        // Por eso se resuelve por petición y no se guarda en la sesión ni en caché.
        //
        // Un usuario sin persona asociada —cuentas de plataforma— no pasa por aquí: no
        // tiene relación laboral que cerrar.
        if ($context->user->person_id !== null && ! $this->hasCurrentRelationship($context)) {
            return AuthorizationDecision::deny(
                'La persona no tiene ninguna relación laboral vigente.', 'abac.relacion'
            );
        }

        $assignments = $this->effectiveAssignments($context);

        if ($assignments->isEmpty()) {
            return AuthorizationDecision::deny(
                'El usuario no tiene ningún rol vigente en este tenant.', 'abac.vigencia'
            );
        }

        $primeraDenegacion = null;

        foreach ($assignments as $assignment) {
            $role = RoleKey::tryFrom((string) $assignment->role?->key);

            if ($role === null) {
                continue;
            }

            $resultado = $this->evaluateRole($role, $assignment, $context);

            if ($resultado->allowed) {
                return $resultado;
            }

            // Se conserva la decisión entera, no solo su texto: la `rule` es lo que
            // permite diagnosticar una denegación sin reproducirla, y es lo que va a
            // `audit_events`. Aplanarla a «matrix.deny» convertiría «te falta el
            // propósito» y «tu rol no llega a P3» en el mismo suceso indistinguible.
            $primeraDenegacion ??= $resultado;
        }

        return $primeraDenegacion ?? AuthorizationDecision::deny(
            'El usuario no tiene ningún rol reconocido por la matriz.', 'matrix.rol_desconocido'
        );
    }

    private function evaluateRole(
        RoleKey $role,
        RoleAssignment $assignment,
        AuthorizationContext $context,
    ): AuthorizationDecision {
        // La clasificación la decide la matriz, no quien pregunta. Ver el comentario de
        // PermissionMatrix::classificationOf(): omitirla saltaba el techo del rol.
        $classification = PermissionMatrix::classificationOf($context->resource, $context->dataClassification);
        $sensitive = in_array($classification, [DataClassification::P3, DataClassification::P4], true);

        // ── ABAC 2 · el alcance de la asignación cubre el recurso ───────────
        if (! $this->scopeCovers($assignment, $context)) {
            // Un alcance `self` incumplido es conceptualmente lo mismo que un ✓ˢ sobre
            // otra persona. Reportar dos reglas distintas para el mismo hecho obligaría
            // a quien lea la auditoría a saber cuál de las dos mirar.
            return $assignment->scope_type === 'self'
                ? AuthorizationDecision::deny(
                    "El rol «{$role->value}» solo alcanza sus propios datos.", 'abac.self'
                )
                : AuthorizationDecision::deny(
                    "El alcance del rol «{$role->value}» no cubre el recurso.", 'abac.alcance'
                );
        }

        // ── ABAC 6 · soporte_plataforma exige vencimiento explícito ─────────
        if ($role->requiresExplicitExpiry() && $assignment->valid_to === null) {
            return AuthorizationDecision::deny(
                'El acceso de soporte exige vencimiento explícito y esta asignación no lo tiene.',
                'abac.soporte_sin_vencimiento'
            );
        }

        $permission = PermissionMatrix::for($context->resource, $context->action, $role);

        if ($permission === Permission::Deny) {
            return AuthorizationDecision::deny(
                "La matriz deniega «{$context->action}» sobre «{$context->resource}» al rol «{$role->value}».",
                'matrix.deny'
            );
        }

        // ── Techo de clasificación del rol ──────────────────────────────────
        // Se comprueba aunque la celda permita: un rol con techo P2 no lee P3 ni con
        // propósito. Es lo que sostiene el invariante 7 —el auditor lee trazas, no P3—.
        if ($sensitive && ! $this->ceilingReaches($role, $context, $permission, $classification)) {
            return AuthorizationDecision::deny(
                "El rol «{$role->value}» tiene techo {$role->classificationCeiling()} y el dato es {$classification}.",
                'abac.techo'
            );
        }

        // ── ✓ˢ · solo sobre sí mismo ────────────────────────────────────────
        //
        // Incluido el listado: un `trabajador` que lista sus relaciones tiene que decir
        // en el contexto de quién son. La alternativa —permitir el listado y confiar en
        // que la consulta aplique el filtro— falla abierta: quien olvide filtrar enseña
        // la planta entera. Así, olvidarlo deniega.
        if ($permission->requiresSelf() && ! $context->isAboutSelf()) {
            return AuthorizationDecision::deny(
                "El rol «{$role->value}» solo alcanza sus propios datos.", 'abac.self'
            );
        }

        // ── ABAC 4 · para P3, propósito informado y de la lista cerrada ─────
        if ($this->purposeIsRequired($permission, $sensitive) && $context->purpose === null) {
            return AuthorizationDecision::deny(
                'Leer un dato P3 exige declarar el propósito.', 'abac.purpose'
            );
        }

        return AuthorizationDecision::allow(
            "Permitido por «{$role->value}»".($context->purpose ? " con propósito «{$context->purpose->value}»" : '')
        );
    }

    /**
     * El propósito se exige cuando la celda es ✓ᵖ y también cuando un ✓ˢ toca un dato P3:
     * que el trabajador lea su propio documento sigue siendo una lectura sensible, y
     * `data-classification.md` no hace excepción por ser el titular.
     */
    private function purposeIsRequired(Permission $permission, bool $sensitive): bool
    {
        return $permission->requiresPurpose() || ($permission->requiresSelf() && $sensitive);
    }

    /**
     * Un ✓ˢ sobre el propio titular alcanza su P3 aunque el techo del rol `trabajador`
     * sea P2 para lo ajeno (matriz: «P2 propio · P3 propio», invariante 3).
     */
    private function ceilingReaches(RoleKey $role, AuthorizationContext $context, Permission $permission, string $classification): bool
    {
        if ($permission->requiresSelf() && $context->isAboutSelf()) {
            return true;
        }

        $techo = $role->classificationCeiling();
        $dato = $classification;

        // P4 no lo alcanza ningún rol de esta matriz: el Clinical Vault se otorga aparte
        // y por asignación explícita, nunca por herencia (permissions.md, nota final).
        if ($dato === DataClassification::P4) {
            return false;
        }

        return $techo >= $dato;
    }

    /** ABAC 2 y 5: el alcance de la asignación contiene la entidad o la sede del recurso. */
    private function scopeCovers(RoleAssignment $assignment, AuthorizationContext $context): bool
    {
        return match ($assignment->scope_type) {
            'tenant' => true,
            'legal_entity' => $context->resourceLegalEntityId === null
                || $assignment->scope_id === $context->resourceLegalEntityId,
            'site' => $context->resourceSiteId === null
                || $assignment->scope_id === $context->resourceSiteId,
            'self' => $context->isAboutSelf() || $context->resourcePersonId === null,
            default => false,
        };
    }

    /**
     * ABAC 3 · solo asignaciones vigentes hoy. CA-08: un rol vencido no otorga permisos
     * aunque siga asignado.
     *
     * @return Collection<int, RoleAssignment>
     */
    private function effectiveAssignments(AuthorizationContext $context): Collection
    {
        return RoleAssignment::with('role')
            ->where('user_id', $context->user->getKey())
            ->effective(Carbon::today())
            ->get();
    }

    /** CA-07: se consulta en cada decisión, nunca se cachea. */
    private function hasCurrentRelationship(AuthorizationContext $context): bool
    {
        return Relationship::where('person_id', $context->user->person_id)
            ->current()
            ->exists();
    }

    private function record(
        AuthorizationContext $context,
        AuthorizationDecision $decision,
        string $classification,
    ): void {
        // Se audita toda denegación (CA-04) y toda lectura sensible autorizada (CA-05).
        // Lo demás —un listado P1 permitido— no deja evento aquí: lo registra la
        // operación cuando ocurre, y duplicarlo llenaría la traza de ruido.
        $sensitive = in_array($classification, [DataClassification::P3, DataClassification::P4], true);

        if ($decision->allowed && ! $sensitive) {
            return;
        }

        $this->recorder->record(
            action: "{$context->resource}.{$context->action}",
            resourceType: $context->resource,
            resourceId: $context->resourcePersonId,
            result: $decision->allowed ? AuditRecorder::ALLOWED : AuditRecorder::DENIED,
            classification: $classification,
            purpose: $context->purpose,
            // Nunca el dato: solo la regla que decidió. `reason` está escrito para eso.
            context: array_filter([
                'rule' => $decision->rule,
                'reason' => $decision->reason,
            ]),
            actorUserId: (string) $context->user->getKey(),
        );
    }
}
