# Recorridos críticos

> Cada recorrido incluye el camino de error, que es donde se pierde la confianza.

## J1 — Alta de una persona hasta que aparece en el reporte *(Slice 0)*

```
Buscar por documento → no existe → Crear persona → Identidad → Relación (tipo, vigencia, sede)
→ Asignación (cargo, nivel, horas) → aparece en la planta → suma en el C600
```

**Errores que hay que diseñar:** documento duplicado en el mismo tenant · fecha de nacimiento de
menor de edad (mensaje que explique el porqué, no un error técnico) · relación que se solapa con
otra vigente · sede que no pertenece a la entidad jurídica elegida.

**Regla:** el usuario nunca debe llegar al final y descubrir que faltaba un dato. La validación
es contextual y ocurre al salir de cada campo.

## J2 — Generar el C600 *(Slice 1)*

```
Elegir año y sede → el sistema deriva lo que puede → muestra vacíos con el motivo
→ completar manualmente lo derivable a mano → revisar → exportar → adjuntar constancia
```

**Momento clave:** la pantalla de vacíos. No es una lista de errores, es una lista de tareas con
responsable sugerido. Cada celda derivada muestra de dónde salió al pasar el cursor.

**Regla dura:** el sistema jamás rellena una cifra que no puede justificar.

## J3 — Simular el escalafón *(Slice 1)*

```
Ver planta docente por estatuto y grado → simular llevar al 80 % de la escala
→ costo adicional de nómina vs. 3,2 puntos de incremento → ver supuestos → exportar al consejo directivo
```

**Regla:** los supuestos van visibles en la misma pantalla, no en una nota al pie.
Este cálculo se lleva a un consejo directivo: si no se puede defender, no sirve.

## J4 — Preparar la visita *(Slice 6)*

```
Elegir ciclo → el sistema arma el expediente en el orden en que lo pide un inspector
→ señala lo que falta → exportar PDF con índice navegable y trazabilidad
```

## J5 — Revocar un acceso *(Slice 0)*

```
Cerrar relación → motivo → confirmación con impacto visible ("pierde acceso a X, Y, Z")
→ el acceso cesa en la siguiente petición
```

**Regla:** antes de confirmar, el sistema dice exactamente qué se pierde. Sin sorpresas.
