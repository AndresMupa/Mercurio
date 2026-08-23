# PRODUCT.md — Visión, posicionamiento y frontera comercial

## Una frase

Plataforma SaaS de personas, trabajo, salud y seguridad para organizaciones colombianas,
que unifica RR. HH., SG-SST, salud ocupacional, enfermería y cumplimiento sobre un modelo
de identidad centrado en la **persona**, no en el empleado.

## Por qué existe

Las suites de RR. HH. instaladas en el mercado colombiano — Buk como referente, con presencia
en cinco países — cubren nómina, asistencia, documentos, beneficios y desarrollo organizacional.
**No cubren SG-SST, salud ocupacional ni enfermería.** Ese es el hueco: el área con obligación
legal, sanción asociada y calendario anual inevitable, y sin producto integral que la atienda
en el mismo lugar donde vive la información de las personas.

La visión de producto es la suite completa. La secuencia comercial empieza donde no hay
competencia instalada, para financiar la construcción del resto.

## Cliente ancla

Institución educativa. Elección deliberada: un colegio es el caso más exigente para el modelo
de identidad, porque en un mismo tenant conviven

- docentes y administrativos (relación laboral),
- estudiantes (sin relación laboral, muchos menores de edad),
- acudientes (terceros con derechos sobre datos de menores),
- contratistas (transporte, alimentación, aseo) con obligaciones de SST en sede.

Un modelo centrado en `Employee` no sobrevive a este caso. Si la plataforma funciona aquí,
funciona en una empresa; al revés no.

## Segmentos

| Segmento | Dolor principal | Entrada |
|---|---|---|
| Colegios y universidades | Enfermería escolar sin trazabilidad + SG-SST + datos de menores | Enfermería + SG-SST |
| PyME 11–200 trabajadores | Estándares mínimos y visitas, sin área de SST dedicada | SG-SST + Modo Inspección |
| Mediana y gran empresa | Multi-sede, contratistas, ausentismo, salud ocupacional | SG-SST + Salud Ocupacional |
| Grupos y multinacionales | Multi-entidad, SSO, residencia de datos, consolidación | Enterprise |

## Frontera del producto

**Dentro:** personas, organización, RR. HH., SG-SST, salud ocupacional, enfermería,
cumplimiento, evidencia probatoria, analítica.

**Fuera de v1, por decisión explícita:** liquidación de nómina, facturación electrónica,
práctica clínica asistencial, decisiones automatizadas sobre derechos de las personas.

## Las tres obligaciones anuales

El ancla revela el patrón que define el producto: un colegio privado responde cada año ante tres
entidades distintas con los mismos datos de personas.

| Obligación | Ante quién | Cuándo | Qué determina |
|---|---|---|---|
| Autoevaluación EVI | MEN / Secretaría de Educación | oct–dic | La tarifa del año siguiente |
| Formulario C600 | DANE (Ley 2335 de 2023, art. 19) | oct–nov | Obligación estadística legal |
| Autoevaluación de estándares mínimos | MinTrabajo | dic, reporte hasta 31-jul | Cumplimiento SG-SST |

Hoy se reconstruyen tres veces al año, a mano, en tres formatos. Ese es el trabajo que el producto elimina.

## Funcionalidad ancla

**Modo Inspección.** Un clic que arma el expediente probatorio de un ciclo SG-SST completo,
listo para una visita del Ministerio del Trabajo o de la ARL, y para el reporte anual de
estándares mínimos. Es lo que sostiene la renovación año tras año.

## Principio comercial

Cada slice declara `DEMOABLE | VENDIBLE | FACTURABLE`. Un slice sin etiqueta va al backlog.
Máximo un slice estructural por cada tres con valor comercial.
