# SPEC — Slice 1 · Motor de reportes obligatorios

`COMMERCIAL VALUE: FACTURABLE` · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`
`BOUNDED CONTEXTS: Reporting · Population · Compliance`

## Propósito

Un colegio privado responde cada año ante tres entidades distintas con los mismos datos de
personas. Este slice convierte ese trabajo manual en un motor: definición de reporte como dato,
recolección desde el núcleo, detección de vacíos y expediente exportable.

Primeras dos salidas: **Formulario C600 del DANE** y **hoja de datos de la autoevaluación EVI**.

## Por qué no es un desvío

El patrón —datos de personas → agregado obligatorio con fecha → expediente de evidencia— se
reutiliza tal cual para el reporte de estándares mínimos ante MinTrabajo (Slice 2 y 6), el reporte
de contratos de personas con discapacidad de la Ley 2466 de 2025, y el registro de bases de datos
ante la SIC. **El motor se reutiliza; el formulario no.** Las definiciones viven en el Country Pack.

## Entidades

```
report_definitions
  id · country_code · key (c600 | evi | estandares_minimos) · version · authority
  period_type (anual) · opens_on · due_on · source_url · status

report_fields
  id · report_definition_id · path · label · data_type · aggregation
  source_expression · required · notes

report_runs
  id · tenant_id · report_definition_id · legal_entity_id · site_id
  period · status · score · computed_at · computed_by · evidence_bundle_id

report_values
  id · report_run_id · report_field_id · value · source (derivado | manual)
  confidence · gap_reason
```

`source_expression` describe de dónde sale cada celda —por ejemplo, «conteo de `relationships`
activas con `positions.personnel_type = docente_aula`, agrupado por `people.sex` y rango de edad»—.
Es dato, no código.

## Máquina de estados — `report_runs`

```
DRAFT → COLLECTING → GAPS_DETECTED → READY → SUBMITTED → ARCHIVED
                   ↘ BLOCKED (falta información fuente)
```

## Criterios de aceptación

- [ ] **CA-01** Con el tenant demo cargado, el sistema genera el Módulo III del C600 (personal por tipo y sexo) sin intervención manual.
- [ ] **CA-02** El sistema genera la desagregación de docentes por estatuto, grado de escalafón, nivel educativo y rango de edad.
- [ ] **CA-03** Los conteos de matrícula del C600 salen de `enrollment_snapshots`, nunca de personas identificadas.
- [ ] **CA-04** Todo campo que no se puede derivar aparece marcado como vacío, con el motivo y el dato que falta. **El sistema nunca inventa una cifra.**
- [ ] **CA-05** La hoja EVI calcula el puntaje de los ítems derivables y señala los que exigen respuesta humana.
- [ ] **CA-06** El simulador de escalafón responde: cuánto sube la nómina al llevar a los docentes al 80 % de la escala del Decreto 2277, contra los 3,2 puntos de incremento de tarifa. **Muestra los supuestos.**
- [ ] **CA-07** La exportación del C600 sale en formato de hoja de cálculo con la estructura de los módulos oficiales.
- [ ] **CA-08** El expediente exportado incluye la fecha, el responsable, la versión de la definición del reporte y la trazabilidad de cada cifra hasta su origen.
- [ ] **CA-09** Ningún dato P3 individual aparece en la exportación: solo agregados.
- [ ] **CA-10** Cambiar la definición del reporte (nueva versión del formulario) no exige tocar código.
- [ ] **CA-11** Celdas con conteo menor a 5 se suprimen en exportaciones fuera del tenant.
- [ ] **CA-12** Toda generación de reporte declara `purpose` (`reporte_c600` o `autoevaluacion_evi`) y queda en auditoría.

## Riesgos abiertos

| Riesgo | Severidad | Mitigación |
|---|---|---|
| El colegio confía en una cifra derivada mal | **P1** | trazabilidad obligatoria de cada celda + marca visible de origen derivado vs. manual |
| El formulario oficial cambia de versión | P2 | definiciones versionadas; el run guarda la versión usada |
| Se promete un puntaje que no se cumple | **P1** | el producto calcula y evidencia; no garantiza resultado. Copy explícito en la interfaz |
