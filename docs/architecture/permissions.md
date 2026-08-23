# Matriz de permisos

> Fase Cero, artefacto 2. RBAC **+** ABAC.
> El rol es un **input** de la decisión de autorización, nunca la decisión.

## Contexto de autorización

```php
AuthorizationContext {
  user, tenant, legalEntity, site, relationship,
  resource, action, purpose, dataClassification
}
```

`purpose` es obligatorio para toda lectura P3 y queda registrado en `audit_events`.
Valores válidos de `purpose` en el Slice 0 y 1:
`reporte_c600` · `autoevaluacion_evi` · `gestion_laboral` · `gestion_sst` ·
`soporte_plataforma` · `auditoria_interna` · `solicitud_del_titular`

## Roles y alcance

| Rol | `scope_type` | Techo de clasificación |
|---|---|---|
| `owner` | tenant | P3 |
| `admin_rrhh` | legal_entity | P3 |
| `responsable_sst` | legal_entity o site | P3 |
| `rector` *(alias de dirección)* | legal_entity | P3 |
| `coordinador` | site | P2 |
| `jefe_area` | site | P2 (solo su equipo) |
| `trabajador` | self | P2 propio · P3 propio |
| `auditor` | tenant | P2 + lectura de auditoría |
| `soporte_plataforma` | tenant, temporal | P2, con consentimiento y vencimiento |

## Matriz recurso × acción

Leyenda: **✓** permitido · **✓ᵖ** permitido con `purpose` declarado · **✓ˢ** solo sobre sí mismo · **—** denegado

| Recurso | Acción | owner | admin_rrhh | responsable_sst | coordinador | jefe_area | trabajador | auditor | soporte |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| `people` (P2) | listar | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ˢ | ✓ | — |
| `people.birth_date` (P3) | leer | ✓ᵖ | ✓ᵖ | ✓ᵖ | — | — | ✓ˢ | — | — |
| `people.sex` (P3) | leer | ✓ᵖ | ✓ᵖ | ✓ᵖ | — | — | ✓ˢ | — | — |
| `identities.document_number` (P3) | leer | ✓ᵖ | ✓ᵖ | ✓ᵖ | — | — | ✓ˢ | — | — |
| `people` | crear / editar | ✓ | ✓ | — | — | — | — | — | — |
| `relationships` | crear / cerrar | ✓ | ✓ | — | — | — | — | — | — |
| `relationships` | listar | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ˢ | ✓ | — |
| `assignments` | gestionar | ✓ | ✓ | — | ✓ | — | — | — | — |
| `positions` | gestionar | ✓ | ✓ | — | — | — | — | — | — |
| `sites`, `legal_entities` | gestionar | ✓ | ✓ | — | — | — | — | — | — |
| `enrollment_snapshots` (P1) | leer | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | ✓ |
| `enrollment_snapshots` | cargar / editar | ✓ | ✓ | — | ✓ | — | — | — | — |
| `reports.c600` | generar | ✓ | ✓ | — | ✓ | — | — | — | — |
| `reports.evi` | generar | ✓ | ✓ | ✓ | — | — | — | — | — |
| `reports.*` | exportar | ✓ | ✓ | ✓ | — | — | — | ✓ | — |
| `users`, `role_assignments` | gestionar | ✓ | — | — | — | — | — | — | — |
| `audit_events` | leer | ✓ | — | — | — | — | — | ✓ | — |
| `audit_events` | modificar / borrar | — | — | — | — | — | — | — | — |
| `tenant.settings`, `dpa` | gestionar | ✓ | — | — | — | — | — | — | — |

## Condiciones ABAC que se evalúan siempre

1. `resource.tenant_id === context.tenant.id` — sin esto, denegado antes de mirar el rol.
2. El alcance del `role_assignment` contiene la entidad jurídica o la sede del recurso.
3. El `role_assignment` está vigente hoy (`valid_from` / `valid_to`).
4. Para P3, `purpose` viene informado y pertenece a la lista de valores válidos.
5. `jefe_area` solo alcanza personas con `assignment` bajo su misma sede y área.
6. `soporte_plataforma` requiere un ticket de consentimiento vigente con vencimiento explícito.

## Invariantes que las pruebas deben verificar

| # | Invariante | Prueba |
|---|---|---|
| 1 | Ninguna combinación de roles produce acceso cross-tenant | `tenant-isolation` |
| 2 | Ningún rol lee P3 sin `purpose` | `authorization` |
| 3 | `trabajador` siempre lee su propio P3 | `authorization` |
| 4 | Nadie, incluido `owner`, puede modificar `audit_events` | `tenant-isolation` + privilegios de BD |
| 5 | Revocada la relación, el acceso cesa en la siguiente petición | `authorization` |
| 6 | `soporte_plataforma` caduca solo, sin intervención | `authorization` |
| 7 | `auditor` lee trazas pero no P3 | `authorization` |

## Nota sobre el futuro Clinical Vault

Cuando entre el Slice 5, **ningún rol de esta tabla asciende a P4 por herencia**.
El acceso clínico se otorga por asignación explícita a `profesional_salud` o `enfermeria`,
sobre la conexión separada, y siempre con lectura auditada. Ni `owner` lo obtiene por serlo.
