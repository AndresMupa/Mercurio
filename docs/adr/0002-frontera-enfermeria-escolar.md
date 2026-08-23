# ADR 0002 — Frontera de habilitación en el módulo de enfermería escolar

**Fecha:** 2026-08-23 · **Estado:** Aceptada

## Contexto

La Resolución 3100 de 2019 regula la habilitación de servicios de salud ante el REPS.
Un colegio que preste servicios asistenciales puede quedar sujeto a habilitación.
El producto no puede inducir a un cliente a operar como prestador no habilitado.

## Decisión

El módulo de enfermería escolar modela **primeros auxilios, administración autorizada de
medicamentos con orden médica y autorización del acudiente, observación de signos vitales,
notificación y remisión**. No modela diagnóstico, prescripción, tratamiento ni continuidad
de atención clínica.

## Consecuencias

- La interfaz, la terminología y los términos de servicio reflejan esta frontera.
- Se convierte en argumento comercial: el producto protege al colegio en vez de exponerlo.
- Cualquier solicitud de cliente que cruce esta frontera exige revisión legal previa y un ADR nuevo.
