# Modelo de entidades núcleo — Slice 0

> Fase Cero, artefacto 5. Modelado contra `docs/anchor/colegio-finlandes.md`.
> Los estudiantes **no** son entidades identificadas: ver ADR 0003.

## Diagrama de relaciones

```
Tenant
 └── Organization
      └── LegalEntity ── (país, NIT, clase de riesgo, régimen)
           ├── Site ── (código DANE, área rural/urbana, es centro de trabajo)
           │    └── EnrollmentSnapshot   ← estudiantes SOLO como conteo (P1)
           └── Position

Person  (solo adultos con relación)
 ├── Identity            (documento)
 ├── Relationship ──────► LegalEntity + Site   (tipo, vigencia)
 │    └── Assignment ───► Position             (nivel de enseñanza, horas)
 └── User                (acceso a la plataforma)

AuditEvent   ← append-only, transversal
```

## Tablas

### `tenants`
`id` uuid · `name` · `slug` · `plan` · `region` · `status` · `dpa_version` · `dpa_accepted_at`

Raíz del aislamiento. Todo lo demás cuelga de aquí.

### `organizations`
`id` · `tenant_id` · `name`

### `legal_entities`
`id` · `tenant_id` · `organization_id` · `name` · `country_code` · `tax_id` (NIT) ·
`ciiu_code` · `risk_class` (I–V) · `tariff_regime` (libertad_regulada | libertad_vigilada | controlado | n_a)

`risk_class` y el número de trabajadores determinan qué estándares mínimos de la Res. 0312 aplican.
`tariff_regime` solo aplica a establecimientos educativos privados.

### `sites`
`id` · `tenant_id` · `legal_entity_id` · `name` · `dane_code` (12 dígitos) · `address` ·
`area_type` (rural | urbana) · `department_code` · `municipality_code` · `is_work_center`

El colegio ancla tiene una sede rural en la vereda Boyero. El `dane_code` es la llave del C600.

### `people` — solo personas con relación, nunca menores
`id` · `tenant_id` · `given_names` · `family_names` · `birth_date` · `sex` ·
`education_level` · `teaching_statute` · `teaching_grade` · `status`

| Campo | Por qué existe | Clasificación |
|---|---|---|
| `sex` | Módulo III del C600 exige desagregación por sexo | P3 |
| `birth_date` | El C600 pide rangos de edad del docente | P3 |
| `education_level` | Bachillerato pedagógico, normalista, licenciado, posgrado, sin titulación | P2 |
| `teaching_statute` | Decreto 2277/1979, 1278/2002 o 804/1995 | P2 |
| `teaching_grade` | Grado en el escalafón. Alimenta el 3,2 % del incremento de tarifa | P2 |

**Invariante:** `birth_date` debe implicar mayoría de edad. Verificado por constraint y por prueba.

### `identities`
`id` · `tenant_id` · `person_id` · `document_type` · `document_number` · `country_code`
· único `(tenant_id, document_type, document_number)`

### `positions`
`id` · `tenant_id` · `legal_entity_id` · `title` · `personnel_type` · `risk_level`

`personnel_type` sigue la taxonomía del C600: `directivo_docente`, `docente_aula`,
`docente_lider_apoyo`, `docente_orientador`, `docente_labores_administrativas`,
`apoyo_en_aula`, `administrativo`, `servicios_generales`.

### `relationships` — el corazón del modelo
`id` · `tenant_id` · `person_id` · `legal_entity_id` · `site_id` · `type` ·
`employment_type` · `valid_from` · `valid_to` · `status`

`type`: `empleado` · `contratista` · `trabajador_contratista` · `temporal` · `aprendiz` ·
`proveedor` · `profesional_sst` · `profesional_salud` · `visitante`

`employment_type`: `indefinido` · `fijo` · `obra_labor` · `prestacion_servicios` · `hora_catedra`

**Invariantes**
1. Una persona puede tener varias relaciones vigentes simultáneas.
2. Sin relación vigente no aparece en bandejas operativas, pero conserva su historia.
3. `valid_to` nulo significa vigente. Nunca se borra una relación: se cierra.
4. Revocada la relación, el acceso cesa en la siguiente petición, no en el siguiente login.

### `assignments`
`id` · `tenant_id` · `relationship_id` · `position_id` · `teaching_level` ·
`weekly_hours` · `valid_from` · `valid_to`

`teaching_level`: `preescolar` · `basica_primaria` · `basica_secundaria` · `media` · `clei` · `n_a`

### `enrollment_snapshots` — estudiantes como agregado (ADR 0003)
`id` · `tenant_id` · `site_id` · `school_year` · `level` · `grade` · `shift` ·
`sex` · `age_range` · `condition` · `educational_model` · `headcount` · `source` · `captured_at`

Sin identificadores. Clasificación P1. `condition`: `ninguna` | `discapacidad`.
Para exportaciones fuera del tenant se suprimen celdas con `headcount < 5`.

### `users`
`id` · `tenant_id` · `person_id` · `email` · `password` · `mfa_enabled` · `mfa_secret` ·
`last_login_at` · `status` · único `(tenant_id, email)`

### `roles` y `role_assignments`
`roles`: `id` · `tenant_id` · `key` · `name`
`role_assignments`: `id` · `tenant_id` · `user_id` · `role_id` · `scope_type` · `scope_id` · `valid_from` · `valid_to`

`scope_type`: `tenant` | `legal_entity` | `site` | `self`. El rol nunca decide solo: es un input
del `AuthorizationContext` junto con alcance, relación, clasificación y propósito.

### `audit_events` — append-only
`id` · `tenant_id` · `actor_user_id` · `actor_label` · `action` · `resource_type` ·
`resource_id` · `purpose` · `data_classification` · `correlation_id` · `source` ·
`result` · `context` jsonb · `occurred_at`

El rol de aplicación **no tiene** `UPDATE` ni `DELETE` sobre esta tabla. Es un invariante de base
de datos, no una convención.

## Lo que deliberadamente NO existe todavía

`students` · `guardians` · `clinical_records` · `nursing_events` · `payroll_runs`

Cada uno exige su propio ADR antes de aparecer.
