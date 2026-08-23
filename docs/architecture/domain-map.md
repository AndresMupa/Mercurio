# Domain Map y Context Map

> Fase Cero, artefacto 1.

## Contextos

| Contexto | Slice | Depende de | Publica | Clasif. máx |
|---|---|---|---|---|
| **Identity & Access** | 0 | — | `UserProvisioned`, `AccessRevoked` | P3 |
| **People & Organization** | 0 | Identity | `PersonCreated`, `RelationshipStarted`, `RelationshipEnded` | P3 |
| **Population** | 1 | Organization | `EnrollmentSnapshotCaptured` | P1 |
| **Reporting** | 1 | People, Population, Compliance | `ReportRunCompleted`, `ReportGapDetected` | P3 |
| **SST** | 2 | People | `CycleOpened`, `ActionAssigned`, `EvidenceAttached` | P3 |
| **Occupational Health** | 5 | People | `ConceptIssued`, `RestrictionApplied` | P3 |
| **Clinical Vault** | 5 | — (aislado) | *no publica contenido* | P4 |
| **Compliance** | 1 → 9 | todos | `ObligationDue`, `RuleChanged` | P2 |
| **Evidence & Signature** | 6 | todos | `DocumentSigned` | P3 |
| **Incidents** | 8 | People, SST | `IncidentReported` | P3 |
| **HR Core** | 7 | People | `ContractSigned`, `AbsenceApproved` | P3 |
| **Billing & Tenancy** | 0 mínimo | Identity | `PlanChanged`, `TenantOffboarded` | P2 |
| **Analytics** | 10 | eventos agregados | — | P1 |

**Population** es un contexto nuevo, consecuencia del ADR 0003: la población estudiantil existe
como serie de conteos, no como colección de personas. Aísla al resto del sistema de cualquier
tentación de identificar menores.

## Reglas de acoplamiento

1. Ningún contexto lee tablas de otro. Solo capa de aplicación o eventos.
2. Clinical Vault y las futuras atenciones no publican contenido clínico en eventos: publican el hecho.
3. Analytics consume agregados. Nunca consulta tablas P3.
4. Reporting lee de People y Population a través de consultas de solo lectura con `purpose` declarado;
   nunca copia datos P3 a su propio almacenamiento.

## Preguntas de Fase Cero, resueltas

| Pregunta | Resolución |
|---|---|
| ¿`Position` pertenece a People o a HR Core? | **People & Organization.** El cargo existe aunque no haya contrato; HR Core gestiona el contrato, no el cargo. |
| ¿El contratista se modela como relación o como entidad? | **Relación** de tipo `trabajador_contratista`, apuntando a la sede donde presta el servicio. Su empleador se registra como `LegalEntity` externa cuando se necesite. |
| ¿Sede del colegio y centro de trabajo del SG-SST son lo mismo? | **Misma tabla `sites`**, con la bandera `is_work_center`. El ancla tiene una sola sede que es ambas cosas. Separar habría creado dos verdades. |
| ¿Dónde viven los estudiantes? | En **Population**, como conteos. Ver ADR 0003. |
