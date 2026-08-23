---
description: Verificar impacto normativo antes de implementar
---

Ejecuta el REGULATORY CHECK del loop obligatorio.

1. Lee `docs/legal/colombia.md` y `docs/legal/legal-matrix.md`.
2. Identifica qué filas de la matriz legal toca el cambio en curso.
3. Para cada fila comprueba: estado (`VIGENTE | MODIFICADA | DEROGADA | SUSTITUIDA | REGLAMENTADA`),
   vigencia temporal y `VERIFIED_AT`. Si la verificación tiene más de 90 días, **verifica en
   fuente oficial antes de continuar**.
4. Si hay ambigüedad jurídica, marca `REQUIRES_LEGAL_VALIDATION`, escríbelo en `STATE.md`
   bajo `## Bloqueos` y **no implementes lógica que dependa de esa lectura**.
5. Si detectas un cambio normativo, registra la entrada en `docs/legal/regulatory-changelog.md`.
6. Nunca inventes una obligación legal. Nunca cites una norma sin comprobar su estado.

Cambio a verificar: $ARGUMENTS
