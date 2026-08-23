# Roadmap por vertical slices

Orden por **valor comercial acumulado**, resolviendo primero lo que después es irreparable:
identidad, tenancy, autorización, auditoría y clasificación.

> **Cambio de secuencia (23-ago-2026), tras leer la autoevaluación EVI y el C600 del colegio ancla.**
> El ancla tiene tres obligaciones anuales que se alimentan de los mismos datos de personas, y la
> ventana de dos de ellas abre en octubre. El antiguo Slice 1 (SG-SST) se reporta hasta julio de 2027
> y puede esperar. El nuevo Slice 1 convierte la Foundation en algo cobrable sin desviarse: es una
> capa delgada de reportes sobre el mismo núcleo `Person → Relationship → Assignment`.
> Ver `docs/anchor/colegio-finlandes.md`.

| # | Slice | Valor | DoD | Contenido |
|---|---|---|---|---|
| 0 | Foundation | — | B | Tenant, persona, organización, entidad jurídica, sede, relación, cargo, auth, MFA, auditoría, clasificación de datos |
| 1 | **Motor de reportes obligatorios** | **FACTURABLE** | B | Definición de reporte como dato, pre-llenado del **C600** y hoja de datos de la **autoevaluación EVI**, con detección de campos faltantes |
| 2 | Ciclo SG-SST | VENDIBLE | B | `SGSSTCycle`, autoevaluación Res. 0312, puntaje, plan de mejoramiento, evidencias |
| 3 | Enfermería escolar | **FACTURABLE** | C | Consentimientos, contactos de emergencia, evento, medicamento autorizado, notificación a acudiente, remisión |
| 4 | Matriz de peligros e inspecciones | VENDIBLE | B | Peligros, valoración, controles, inspecciones, acciones correctivas |
| 5 | Salud ocupacional + Clinical Vault | FACTURABLE | C | Seis tipos de evaluación, concepto de aptitud sin "no apto", restricciones, reincorporación |
| 6 | Modo Inspección | **FACTURABLE** | B | Expediente probatorio exportable de un ciclo completo |
| 7 | HR Core mínimo | VENDIBLE | B | Contratos, documentos, ausencias, firma electrónica |
| 8 | Incidentes | VENDIBLE | B | Reporte, investigación, plan de acción, seguimiento |
| 9 | Motor de cumplimiento | FACTURABLE | B | Matriz legal viva, obligaciones, vencimientos, alertas |
| 10 | Analítica | VENDIBLE | A | Indicadores, tableros, exportación a BI |
| 11 | Integraciones | FACTURABLE | B | Nómina, directorio corporativo, sistema académico |
| 12 | Enterprise | FACTURABLE | C | SSO/SAML, SCIM, multi-entidad, residencia por región |

## Por qué el Slice 1 no es un desvío

El patrón que construye —datos de personas → agregado obligatorio con fecha → expediente de
evidencia— es el mismo del reporte de estándares mínimos ante MinTrabajo, del reporte de contratos
de personas con discapacidad de la Ley 2466 de 2025 y del registro de bases de datos ante la SIC.
Lo específico del colegio son las **definiciones de reporte**, que viven como dato en el Country Pack,
no como código. El motor se reutiliza; el formulario no.

## Regla de secuencia

Máximo **un** slice estructural (sin etiqueta comercial) por cada tres con etiqueta.
El slice 0 es ese único slice estructural permitido al inicio, y el slice 1 lo vuelve cobrable.

## Calendario que manda

| Mes | Obligación del ancla | Slice que debe estar listo |
|---|---|---|
| Octubre–diciembre 2026 | Autoevaluación EVI + C600 | 0 y 1 |
| Diciembre 2026 | Autoevaluación de estándares mínimos SG-SST | 2 |
| Enero–junio 2027 | Operación de enfermería y SST del año escolar | 3 y 4 |
| Hasta 31 de julio 2027 | Reporte de estándares mínimos en sgrl.mintrabajo.gov.co | 6 |
