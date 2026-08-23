# ADR 0003 — Los estudiantes se modelan como agregado, no como personas identificadas

**Fecha:** 2026-08-23 · **Estado:** Aceptada · **Decide:** Andrés Muñoz

## Contexto

El cliente ancla tiene 883 estudiantes menores de edad. El diseño inicial contemplaba modelarlos
como `Person` con relación de tipo estudiante, acudientes asociados y atenciones de enfermería
individuales. Eso convertía a la plataforma, desde el primer día, en tratante de datos sensibles
de menores: autorización del representante legal, interés superior del menor, condición de
discapacidad, y eventualmente información clínica.

El foco real del producto son **docentes y personal**. Los estudiantes solo se necesitan como
**cantidades** para los reportes obligatorios (C600 del DANE y autoevaluación del MEN).

## Decisión

La plataforma **no almacena identidad de estudiantes**. No hay nombre, documento, fecha de
nacimiento ni ningún identificador directo o indirecto de un menor.

Los estudiantes existen únicamente como `EnrollmentSnapshot`: conteos por año escolar, sede, nivel,
grado, jornada, sexo, rango de edad, condición y modelo educativo. Es un agregado sin PII,
clasificado **P1**.

`Person` queda reservado a personas con relación con la organización: docentes, directivos,
administrativos, servicios generales, contratistas y profesionales de SST y salud.

## Consecuencias

**Positivas**

1. Desaparece del alcance el tratamiento de datos de menores. Sin autorizaciones de acudientes,
   sin interés superior del menor, sin datos sensibles de niñas, niños y adolescentes.
2. El nivel de clasificación máximo del Slice 0 y del Slice 1 baja de P4 a P3.
   El Definition of Done del Slice 1 baja de nivel C a nivel B.
3. El núcleo se vuelve más general: personal + población agregada sirve igual a un colegio,
   una empresa o una multinacional. Lo específico del colegio son las definiciones de reporte.
4. El riesgo reputacional y legal más grande del producto se elimina por diseño, no por control.

**Negativas y limitaciones que hay que aceptar**

1. **El módulo de enfermería escolar por estudiante queda fuera de alcance.** Una atención
   individual exige identificar al estudiante. Lo que queda es enfermería **ocupacional**,
   sobre el personal, que es donde vive el vínculo con salud ocupacional y SST.
2. Reactivar enfermería escolar en el futuro exige un ADR nuevo, habilitar identidad de estudiantes
   como capacidad opcional del Industry Pack de Educación, y construir el circuito completo de
   autorizaciones del representante legal. **No se hace por conveniencia de un cliente.**
3. Los agregados con conteos muy bajos pueden ser reidentificables dentro de la propia institución.
   Para exportaciones fuera del tenant se aplica supresión de celdas con conteo menor a 5.

## Regla derivada

> Ninguna tabla de la plataforma puede contener un identificador de una persona menor de edad
> sin un ADR que lo autorice expresamente. Las pruebas de esquema verifican que `people` no
> reciba filas cuya `birth_date` implique minoría de edad.
