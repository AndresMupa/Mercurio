---
description: Threat model del cambio en curso
---

Ejecuta el THREAT MODEL del loop obligatorio sobre el cambio en curso.

Analiza al menos:

1. **Cross-tenant** — ¿existe algún camino, incluida una ruta indirecta por relaciones,
   agregados, exportaciones, notificaciones o colas, que exponga datos de otro tenant?
2. **Escalamiento de privilegios** — ¿alguna combinación de roles o relaciones alcanza P4?
3. **Fuga por canal lateral** — logs, trazas, telemetría, correos, mensajes de error, nombres
   de archivo, URLs firmadas, respuestas de API con campos de más.
4. **Datos de menores** — ¿existe tratamiento sin autorización vigente del representante legal?
5. **Integridad de auditoría** — ¿algo puede modificarse o borrarse sin quedar registrado?
6. **Error humano** — ¿qué pasa si el usuario se equivoca de persona, de sede o de tenant?
7. **Retención** — ¿alguna operación borra algo que la ley obliga a conservar?

Para cada hallazgo: severidad, escenario concreto, mitigación y prueba que lo verifica.
Si aparece una condición del §26, **detente y documenta**.

Alcance: $ARGUMENTS
