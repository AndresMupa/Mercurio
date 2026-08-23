---
description: Abrir un vertical slice nuevo bajo el harness
---

Abre un vertical slice siguiendo `AGENTS.md` §27 y §30.

1. Lee `STATE.md`, `AGENTS.md` y el `specs/<contexto>/SPEC.md` correspondiente.
2. Declara la cabecera del slice:

```
SLICE: <nombre>
COMMERCIAL VALUE: DEMOABLE | VENDIBLE | FACTURABLE
DoD LEVEL: A | B | C
DATA CLASSIFICATION: P0..P4
BOUNDED CONTEXTS: <lista>
```

3. Si no consigues asignar una etiqueta de valor comercial, **no abras el slice**: propónlo
   para el backlog y explica por qué.
4. Responde el formato de iteración completo antes de escribir una línea de código:
   Objective · User · Current behavior · Desired behavior · Domain impact · Regulatory impact ·
   Commercial value · Security impact · UX flow · Implementation plan · Acceptance criteria ·
   Tests · DoD level.
5. Actualiza `STATE.md` con el slice abierto.

Argumento recibido: $ARGUMENTS
