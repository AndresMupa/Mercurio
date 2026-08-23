# SPEC — incidents

> Léelo completo antes de tocar código de este contexto. Actualízalo al cerrar cada slice.

## Propósito

<Qué problema resuelve este contexto y para quién.>

## Frontera

**Dentro:** <…>
**Fuera:** <…>
**Depende de:** <contextos>
**Publica:** <eventos>

## Clasificación de datos

| Entidad | Campos sensibles | Nivel |
|---|---|---|
| | | |

## Entidades e invariantes

<Modelo y reglas que nunca pueden violarse.>

## Máquinas de estado

```
DRAFT → SUBMITTED → … 
```

| Transición | Actor | Precondiciones | Efectos | Auditoría |
|---|---|---|---|---|

## Autorización

| Recurso | Acción | Roles | Condiciones ABAC | Propósito requerido |
|---|---|---|---|---|

## Impacto normativo

| Regla (id de matriz legal) | Cómo se materializa | Evidencia |
|---|---|---|

## Recorridos de usuario

<Journeys, incluido el camino de error.>

## Criterios de aceptación

- [ ] …

## Pruebas obligatorias

- [ ] unit
- [ ] integration
- [ ] authorization
- [ ] tenant isolation
- [ ] E2E
- [ ] accesibilidad
- [ ] negative
- [ ] threat *(solo P3/P4)*

## Riesgos abiertos

| Riesgo | Severidad | Mitigación |
|---|---|---|
