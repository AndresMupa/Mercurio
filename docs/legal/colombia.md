# Colombia — Marco normativo aplicable

> Estado verificado a **23 de agosto de 2026**.
> Ninguna norma se da por vigente sin comprobación. Toda implementación que dependa de una
> norma cita aquí su fila y su estado.

## Normas núcleo

### Decreto 1072 de 2015 — Decreto Único Reglamentario del Sector Trabajo
**VIGENTE.** Libro 2, Parte 2, Título 4, Capítulo 6. Estructura el SG-SST como sistema de
mejora continua (PHVA): planificación, asignación de responsabilidades, indicadores,
auditoría y matriz legal actualizada.

*Impacto:* el ciclo anual `SGSSTCycle` y la matriz legal viva son requisitos, no funcionalidades.

### Resolución 0312 de 2019 — Estándares mínimos del SG-SST
**VIGENTE.** Estándares diferenciados por número de trabajadores y clase de riesgo.
Autoevaluación anual y plan de mejoramiento.

*Impacto:* la autoevaluación es un formulario versionado con puntaje, nivel de cumplimiento
y plan derivado, parametrizado por tamaño y riesgo del tenant.

### Circular 027 de 2026 (MinTrabajo, 26-feb-2026)
**VIGENTE.** Fija el calendario del reporte de autoevaluación y plan de mejoramiento:
autoevaluación en diciembre, **registro hasta el 31 de julio** del año siguiente,
a través de `https://sgrl.mintrabajo.gov.co/`. Obliga a empleadores públicos y privados,
trabajadores independientes, cooperativas, empresas de servicios temporales, estudiantes
afiliados al SGRL y ARL.

*Impacto:* **es la mejor prueba de por qué las fechas no se escriben en el código.**
Una circular movió el calendario. El motor de cumplimiento consume fechas como dato versionado.

### Resolución 1843 de 2025 — Evaluaciones médicas ocupacionales
**VIGENTE desde el 7 de noviembre de 2025** (expedida 29-abr-2025, publicada 6-may-2025,
transición de seis meses). **Deroga la Resolución 2346 de 2007**, y también las
Resoluciones 1918 de 2009, 1075 de 1992 y 4050 de 1994.

Cuatro reglas que se materializan en el modelo de datos:

1. **Seis tipos de evaluación:** pre-ingreso · periódica (programada o por cambio de ocupación) ·
   egreso · post-incapacidad · retorno laboral · seguimiento o control. Es un `enum`.
2. **La historia clínica ocupacional no viaja al empleador.** Solo el **concepto ocupacional**,
   con restricciones, recomendaciones y temporalidad.
3. **Prohibido el uso de "no apto".** El `enum` del concepto no contiene ese valor y una
   validación de dominio lo rechaza.
4. **Conservación mínima: 20 años**, bajo custodia del prestador de medicina ocupacional.
   Reserva estricta: divulgación solo por orden judicial, autorización escrita del trabajador,
   solicitud médica con consentimiento, o petición de entidad competente para calificar.

### Ley 2466 de 2025 — Reforma laboral
**VIGENTE, con escalonamiento.**

| Materia | Contenido | Vigencia |
|---|---|---|
| Jornada | Máximo 42 h semanales | Vigente |
| Recargo dominical y festivo | 80 % | 1-jul-2025 |
| Recargo dominical y festivo | 90 % | 1-jul-2026 |
| Recargo dominical y festivo | 100 % | 1-jul-2027 |
| Contrato a término fijo | Máximo 4 años | Vigente |
| Debido proceso disciplinario | Garantías mínimas verificables | Vigente |
| Licencias | Nuevas causales, incluida obligaciones escolares como acudiente | Vigente |
| Reporte de contratos de personas con discapacidad | 15 días al Ministerio del Trabajo | Vigente |
| Registro de horas extras | Detallado y entregable al trabajador | Vigente |

*Impacto:* el escalonamiento de recargos es el caso de prueba perfecto de la regla versionada
con `effective_from` / `effective_to`. El debido proceso disciplinario es una máquina de estados
cuyas etapas deben ser demostrables.

### Ley 1562 de 2012
**VIGENTE.** Sistema General de Riesgos Laborales.

### Ley 1581 de 2012 y reglamentación de protección de datos
**VIGENTE.** Los datos de salud son datos sensibles. Datos de niñas, niños y adolescentes con
garantías reforzadas: interés superior del menor, derechos fundamentales, autorización del
representante legal y consideración de la opinión del menor según su madurez.
Registro Nacional de Bases de Datos (RNBD) ante la SIC, con obligaciones actualizadas en 2026.

*Impacto:* el módulo de enfermería escolar es el punto de mayor exposición legal del producto.

### Resolución 3100 de 2019 — Habilitación de servicios de salud
**VIGENTE.** Define los servicios de salud sujetos a habilitación ante el REPS.

*Impacto:* frontera de diseño del módulo de enfermería escolar. Ver `AGENTS.md` §11 y el ADR
correspondiente. El producto no puede inducir a un colegio a operar como prestador no habilitado.

### Ley 527 de 1999
**VIGENTE.** Valor probatorio de los mensajes de datos y de la firma electrónica.

*Impacto:* fundamenta el dominio `Evidence & Signature`.

## Pendientes de verificación

| Norma | Por qué importa | Estado |
|---|---|---|
| Resolución 2764 de 2022 | Batería de riesgo psicosocial | `REQUIRES_LEGAL_VALIDATION` |
| Normativa de incapacidades y reincorporación | Flujo de retorno laboral | `REQUIRES_LEGAL_VALIDATION` |
| Reglamentación posterior a la Ley 2466 de 2025 | Detalles operativos | `REQUIRES_LEGAL_VALIDATION` |

## Fuentes consultadas

- Función Pública — Gestor Normativo (Decreto 1072 de 2015, Ley 2466 de 2025)
- SUIN-Juriscol (Resolución 0312 de 2019, Resolución 1843 de 2025, Resolución 3100 de 2019, Ley 1581 de 2012)
- Ministerio del Trabajo — `https://sgrl.mintrabajo.gov.co/`
- Superintendencia de Industria y Comercio — Registro Nacional de Bases de Datos
