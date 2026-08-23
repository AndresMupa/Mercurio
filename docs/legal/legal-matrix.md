# Matriz legal viva

> La regulación **no se escribe como `if` en el código**. Se modela como dato versionado.
> Este archivo es la semilla; el motor de cumplimiento la consume.

## Modelo

```
LEGAL RULE
  → VERSION
  → JURISDICTION
  → EFFECTIVE FROM
  → EFFECTIVE TO
  → APPLICABILITY   (tamaño, clase de riesgo, sector, tipo de relación)
  → REQUIREMENT
  → EVIDENCE        (qué prueba el cumplimiento)
  → RESPONSIBLE
  → STATUS          (VIGENTE | MODIFICADA | DEROGADA | SUSTITUIDA | REGLAMENTADA)
  → VERIFIED_AT     (fecha de la última comprobación)
```

## Semilla — Slices 0 y 1

| id | Norma | Requisito | Aplicabilidad | Evidencia | Vigencia | Estado |
|---|---|---|---|---|---|---|
| CO-SST-001 | Res. 0312/2019 | Autoevaluación anual de estándares mínimos | Todo empleador | Formulario firmado + puntaje | desde 2019 | VIGENTE |
| CO-SST-002 | Res. 0312/2019 | Plan de mejoramiento derivado de la autoevaluación | Puntaje < 100 | Plan con responsables y fechas | desde 2019 | VIGENTE |
| CO-SST-003 | Circular 027/2026 | Registro del reporte hasta el 31 de julio en sgrl.mintrabajo.gov.co | Todo obligado | Constancia de registro | ciclo 2025 | VIGENTE |
| CO-SST-004 | Decreto 1072/2015 | Plan anual de trabajo del SG-SST | Todo empleador | Plan aprobado por la dirección | desde 2015 | VIGENTE |
| CO-SST-005 | Decreto 1072/2015 | Matriz legal actualizada | Todo empleador | Matriz versionada | desde 2015 | VIGENTE |
| CO-SO-001 | Res. 1843/2025 | Evaluación médica de pre-ingreso | Toda vinculación | Concepto ocupacional | desde 2025-11-07 | VIGENTE |
| CO-SO-002 | Res. 1843/2025 | Prohibición del término "no apto" en el concepto | Todo concepto | Validación de dominio | desde 2025-11-07 | VIGENTE |
| CO-SO-003 | Res. 1843/2025 | Conservación de historia clínica ocupacional ≥ 20 años | Toda historia | Política de retención | desde 2025-11-07 | VIGENTE |
| CO-DP-001 | Ley 1581/2012 | Autorización del representante legal para datos de menores | *No aplica en v1* — la plataforma no almacena identidad de estudiantes (ADR 0003) | — | — | NO_APLICA |
| CO-EDU-001 | Ley 2335/2023 art. 19 | Diligenciar el Formulario Único Censal C600 | Todo establecimiento educativo | Constancia de envío al DANE | vigente | VIGENTE |
| CO-EDU-002 | Decreto 1075/2015 y Res. anual de tarifas | Autoevaluación anual de recursos y procesos (EVI) | Establecimientos privados | Reporte radicado ante la Secretaría de Educación | anual | VIGENTE |
| CO-EDU-003 | Res. 020309/2026 | Parámetros del incremento de tarifas 2027: IPC 6,14 %, autoevaluación 0,83 %, inclusiva 0,40 %, escalafón 3,20 %, permanencia 0,16–0,50 %, techo 11,24 % | Privados, régimen aplicable | Acta del consejo directivo + resolución rectoral | año escolar 2027 | VIGENTE |
| CO-DP-002 | Ley 1581/2012 | Base legal del tratamiento de sexo y fecha de nacimiento del personal: obligación estadística | Todo tenant | `PURPOSE` registrado en auditoría | desde 2012 | VIGENTE |
| CO-LAB-001 | Ley 2466/2025 | Recargo dominical 90 % | Todo empleador | Liquidación (integración de nómina) | desde 2026-07-01 | VIGENTE |
| CO-LAB-002 | Ley 2466/2025 | Recargo dominical 100 % | Todo empleador | Liquidación (integración de nómina) | desde 2027-07-01 | PROGRAMADA |

## Reglas de mantenimiento

1. Ninguna fila se implementa sin `VERIFIED_AT` de los últimos 90 días.
2. Una fila con estado `REQUIRES_LEGAL_VALIDATION` **no genera lógica de producto**.
3. Todo cambio normativo entra en `docs/legal/regulatory-changelog.md` con fecha, fuente y filas afectadas.
4. La plataforma apoya el cumplimiento; no reemplaza al abogado ni al profesional SST.
