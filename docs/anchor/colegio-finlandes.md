# Cliente ancla — Colegio Finlandés Juan Pablo II

> Datos tomados del Reporte de Autoevaluación 2025 del MEN entregado por el propio establecimiento
> y del Formulario Único Censal C600 del DANE (versión 8, DSO-EDUC-IDR-001).
> Este documento reemplaza toda suposición sobre el ancla: la Fase Cero se modela contra estas cifras.

## Identificación

| Campo | Valor |
|---|---|
| Establecimiento | Colegio Finlandés Juan Pablo II |
| Código establecimiento | 325430001368 |
| Municipio | Madrid, Cundinamarca — sector **rural**, vereda Boyero |
| Titularidad de la sede | Arriendo |
| Naturaleza jurídica | Persona jurídica — sociedad |
| PEI | Educación Finlandesa Educación del Futuro en Madrid (inscrito 30-11-2013) |
| Inicio de labores | 27-01-2014 |
| Régimen de tarifas | **Libertad Regulada** |
| Jornada | Mañana y parte de la tarde · Calendario A |

## Escala real (define el dimensionamiento del MVP)

| Dimensión | Valor |
|---|---|
| Matrícula total | **883 estudiantes** (prejardín a 11.º) |
| Estudiantes con discapacidad | 13 · matrícula inclusiva **Sí** |
| Docentes | 47 (preescolar 4 · primaria 18 · secundaria 15 · media 10) |
| Directivos docentes | 6 (rector 1 · coordinadores 4 · secretario académico 1) |
| Apoyo pedagógico | 2 (orientadora, auxiliar de notas) |
| Administrativos | 4 (**enfermera pasante**, secretaria, contadora, talento humano) |
| Apoyo y servicios generales | 5 |
| **Total personas con relación laboral** | **≈ 64** |
| Aulas | 29 · área construida 2.476 m² sobre lote de 4.633 m² |
| Índice de permanencia | 98,49 % → clasificación **Alto** |
| Tasa de aprobación | 100 % · SABER 11 categoría **B** |

**Consecuencia SG-SST:** con más de 50 trabajadores, aplican los estándares mínimos completos de la
Resolución 0312 de 2019, no el conjunto reducido. Verificar el conteo exacto de vinculados antes de parametrizar.

**Consecuencia de modelo:** un solo tenant con ≈ 64 personas en relación laboral, 883 estudiantes menores
de edad, sus acudientes y contratistas de transporte y aseo. Confirma que `Person` es la raíz y que
`Employee` habría sido un error estructural.

## Las tres obligaciones anuales que hoy se hacen tres veces a mano

| # | Obligación | Ante quién | Cuándo | Qué determina |
|---|---|---|---|---|
| 1 | **Autoevaluación EVI** (recursos + procesos) | MEN / Secretaría de Educación de Cundinamarca | ingreso oct · cierre dic | **La tarifa que puede cobrar el año siguiente** |
| 2 | **Formulario C600** | DANE — obligatorio, Ley 2335 de 2023 art. 19 | recolección oct–nov | Estadística oficial; su omisión es incumplimiento legal |
| 3 | **Autoevaluación de estándares mínimos SG-SST** | MinTrabajo | autoevaluación dic · reporte hasta 31-jul | Cumplimiento del Decreto 1072 y la Res. 0312 |

Las tres se alimentan de los **mismos datos de personas**: quién trabaja aquí, con qué vinculación, qué
título y escalafón tiene, qué estudiantes hay por grado, jornada, edad, sexo y condición.
Hoy esos datos se reconstruyen tres veces al año en tres formatos distintos.

## Aritmética de la tarifa — por qué esto vale dinero

La autoevaluación no es un trámite: es la fórmula que fija el incremento autorizado.

**Lo obtenido para 2026 — 6,73 %**

| Componente | Puntos |
|---|---|
| IPC 2025 | 5,10 % |
| Clasificación en autoevaluación (Libertad Regulada) | 0,83 % |
| Índice de permanencia (Alto) | 0,50 % |
| Educación inclusiva (Sí) | 0,30 % |
| Escalafón docente | **0,00 %** |

**Parámetros 2027 — Resolución 020309 del 31 de julio de 2026**

| Componente | Valor 2027 | Situación del colegio |
|---|---|---|
| IPC junio 2026 | 6,14 % | automático |
| Autoevaluación (Libertad Regulada) | 0,83 % | lo tiene |
| Acreditación de calidad | 1,00 % | **no lo tiene** (sustituye al anterior) |
| Índice de permanencia | 0,16 %–0,50 % | en el tope |
| Educación inclusiva | 0,40 % | lo tiene |
| Escalafón docente Decreto 2277 de 1979 | **3,20 %** | **no lo tiene** |
| Techo total | **11,24 %** | |

**Si no cambia nada: 7,87 %. Techo: 11,24 %. Brecha: 3,37 puntos.**

| Base de cálculo | Valor |
|---|---|
| Valor anual del servicio educativo | $3.246.833.944 |
| Ingresos de operación (neto de devoluciones y becas) | $2.690.621.788 |
| Resultado neto del ejercicio | $163.147.665 |
| **1 punto porcentual de incremento** | **$26,9 M (neto) – $32,5 M (bruto) al año** |
| **Brecha de 3,37 puntos** | **≈ $90,7 M al año** |

La brecha vale más de la mitad de la utilidad neta anual del colegio.

**Advertencia honesta:** los 3,2 puntos del escalafón exigen pagar al menos el 80 % de la escala del
Decreto 2277 de 1979. Eso cuesta dinero. Con 47 docentes y salarios básicos de $882.550.728 al año
(≈ $1,56 M mensuales promedio), la pregunta real es si el aumento de nómina es menor que los $86 M
de ingreso adicional. **Hoy el colegio no puede responderla porque no tiene los datos estructurados.**
Ese cálculo es una funcionalidad del producto, no un argumento de venta.

## Puntos perdidos en la autoevaluación 2025 — verificados en el propio reporte

| Pregunta | Respuesta actual | Puntos | Costo de corregirlo |
|---|---|---|---|
| 84a. Página web con dominio educativo | No — usan un `.com` | 0 | Bajo. Tienen sitio; falta el dominio `.edu.co` |
| 84b. Correo institucional con dominio educativo | No — usan Gmail | 0 | Bajo. Va con lo anterior |
| 34a. Ciberseguridad a nivel tecnológico | No | 0 | Bajo–medio |
| 21a. Puertas con medidas y mecanismos según NTC | No | 0 | Medio. Obra |
| 21b. Circulaciones, rampas y escaleras según NTC | No | 0 | Medio. Obra y señalización |
| 33a / 33c. Laboratorios virtuales / LMS | No | 0 | Bajo. Ya usan plataformas |
| 83. Sistema de estímulos e incentivos | En desarrollo | 1 de 2–3 | Bajo. Es documentación |
| 22. SG-SST | Implementado | 2 | Ya lo tiene, sin evidencia adjunta |
| 17. Espacio de enfermería | Tipo A | 1 | Ya lo tiene |
| 73. Plan institucional de gestión del riesgo | Buenos resultados | 2 de 3 | Medio |

Las dos primeras filas son aproximadamente dos puntos de autoevaluación por el costo de un dominio
y una migración de correo. Es el argumento de entrada a la reunión.

## Señales de riesgo detectadas

1. **Enfermera pasante única** para 883 estudiantes, 13 de ellos con discapacidad, en sede rural.
2. **Primer respondiente: No · Atención psicosocial: No · Espacios de salud mental: No** —
   declarado por el propio colegio en las estrategias de prevención y mitigación de riesgos.
3. **Control de materiales peligrosos: No.**
4. **Comedor escolar declarado, sin cocina y sin cumplimiento de la Resolución 2674 de 2013**,
   atendiendo 0 estudiantes. Inconsistencia a aclarar: si se sirve alimento, hay obligación sanitaria.
5. **Sin modelo de gestión de calidad**, lo que cierra la vía del punto de acreditación.

La combinación 1 + 2 es la exposición más seria: administración de medicamentos a menores por una
practicante, sin trazabilidad de la autorización del acudiente. Es exactamente el escenario que el
ADR 0002 acota y el Slice de enfermería resuelve.

## Consecuencias directas para el modelo de datos

El C600 y el EVI obligan a que el núcleo de personas cargue campos que sin estos documentos se habrían omitido:

**Persona con relación laboral**
`sexo` · `fecha_nacimiento` (para rangos de edad) · `nivel_educativo_alcanzado`
(bachillerato pedagógico, normalista superior, licenciado, posgrado, sin titulación…) ·
`estatuto_docente` (2277/1979, 1278/2002, 804/1995) · `grado_escalafon` · `tipo_vinculacion`
(planta/indefinido vs. contrato fijo) · `nivel_ensenanza_mayor_asignacion` · `tipo_personal`
(directivo docente, docente de aula, orientador, apoyo en aula, administrativo…)

**Estudiante**
`grado` · `jornada` · `edad` · `sexo` · `condicion_discapacidad` · `modelo_educativo` ·
`matricula_propia_o_contratada` · `repitencia`

**Sede**
`codigo_dane` (12 dígitos) · `nit` · `area` (rural/urbana) · `acto_administrativo` ·
`regimen_tarifas` · `plantas_fisicas`

**Nota de privacidad:** `sexo` y `condicion_discapacidad` de menores son datos sensibles bajo la
Ley 1581 de 2012. Su tratamiento aquí tiene base legal expresa —la obligatoriedad estadística del
C600 conforme a la Ley 2335 de 2023, artículo 19— y esa base debe quedar registrada como
`PURPOSE` en la autorización y en la auditoría. Clasificación **P3**; agregados de salida, **P1**.

## Ventana de tiempo

Hoy es **23 de agosto de 2026**. La autoevaluación EVI para las tarifas de 2027 se diligencia entre
octubre y diciembre de 2026, y el C600 se recolecta en el mismo periodo. Quedan alrededor de siete
semanas para llegar con algo funcionando.
