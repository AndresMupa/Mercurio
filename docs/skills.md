# Mapa de skills — cuándo invocar cada una

> Regla de oro del harness: **primero investiga y modela, después invoca la skill de formato.**
> Leer una skill de documento antes de tener el contenido ancla al agente en la mecánica del
> archivo en vez de en el problema.

| Skill | Cuándo | En qué paso del plan |
|---|---|---|
| `design` | **Antes** de escribir una línea de frontend. Genera el lienzo con las pantallas, el flujo y los estados, incluidos los vacíos y los de error. Se itera visualmente antes de que exista código. | B7, C6 |
| `dataviz` | Antes de la primera línea de cualquier gráfico, indicador, medidor o tablero. Define paleta, forma y accesibilidad del dato visual. | C6, tableros de SST |
| `xlsx` | Exportación del C600 y hoja de trabajo del EVI. El entregable es una hoja de cálculo con la estructura de los módulos oficiales. | C5 |
| `pdf` | Expediente probatorio del Modo Inspección y actas firmadas. | D3, Slice 6 |
| `docx` | Actas del consejo directivo, informes institucionales, propuesta comercial, manual de usuario. | D3, comercial |
| `ux-refactor-lead` | **Solo cuando ya hay sistema construido.** Auditoría UX, accesibilidad y rendimiento del Slice 0 y 1 contra el diseño objetivo. No sirve para diseñar desde cero. | D2, y al cierre de cada slice con interfaz |
| `artifact-design` | Páginas de estado o informes que se comparten con el colegio fuera del producto. | comercial, seguimiento del piloto |
| `skill-creator` | Empaquetar el loop del harness como skill del proyecto, para que no dependa de recordar leer `AGENTS.md`. | A2, opcional |

## Secuencia correcta en el frontend

```
design (lienzo de pantallas)  →  dataviz (si hay gráficos)  →  implementar componentes
   →  pruebas de accesibilidad  →  ux-refactor-lead (auditoría contra el diseño)
```

Invertir este orden produce interfaces que funcionan y no se pueden usar.
