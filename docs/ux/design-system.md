# Sistema de diseño

> Producto operativo, denso y probatorio. No es un dashboard bonito: es una herramienta de trabajo
> que además tiene que aguantar que la mire un inspector.

## Principios

1. **El estado antes que el dato.** Toda pantalla de proceso muestra `ESTADO · RESPONSABLE · SIGUIENTE ACCIÓN · VENCE`.
2. **El origen siempre visible.** Una cifra derivada se distingue de una cifra escrita a mano. Siempre.
3. **Densidad con jerarquía.** Estos usuarios manejan tablas largas. Densidad alta, pero con tipografía y espaciado que permitan escanear.
4. **El color nunca es el único canal.** Forma, icono y texto acompañan a todo estado (WCAG 2.2 AA).
5. **Sin callejones sin salida.** Todo estado vacío explica qué es, por qué está vacío y cuál es la acción.
6. **Móvil real en campo.** Inspecciones y evidencias se capturan con una mano, con guantes, con mala luz.

## Tokens

```
--color-ground        fondo de aplicación
--color-surface       tarjetas y tablas
--color-ink           texto principal
--color-muted         texto secundario y etiquetas
--color-rule          separadores
--color-accent        acción primaria e identidad
--color-ok            cumplido / vigente
--color-warn          por vencer / incompleto
--color-critical      vencido / denegado / P0
--color-clinical      exclusivo de superficies P4 (nunca reutilizar)
```

Escala de espaciado 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64.
Radio 2 px en controles densos, 4 px en tarjetas. Sombras solo para elevación real, nunca decorativas.

**Regla de color:** el estado semántico (ok / warn / critical) es independiente del acento de marca.
El color reservado a superficies clínicas no se usa en ningún otro contexto: cuando aparezca P4,
el usuario debe reconocerlo antes de leer.

## Tipografía

Una familia con versión monoespaciada para identificadores, fechas, códigos DANE, puntajes y
cualquier columna numérica. `font-variant-numeric: tabular-nums` obligatorio en tablas.
Escala tipográfica fija; nada de tamaños improvisados.

## Componentes base (orden de construcción)

`AppShell` · `MyWorkInbox` · `DataTable` (orden, filtro, selección múltiple, densidad) ·
`FilterBar` · `StatusPill` · `OwnerChip` · `DueDateBadge` · `EvidenceDropzone` ·
`AuditTrail` · `PurposeDialog` · `GapList` · `WizardStepper` · `BulkImport` · `EmptyState` ·
`ConfirmWithImpact` · `SignaturePanel`

`PurposeDialog` es específico de este producto: antes de leer un dato P3, el usuario declara para
qué. No es fricción gratuita, es el registro que sostiene la auditoría. Debe ser rápido: propósito
sugerido por contexto y confirmación en un clic.

## Accesibilidad — no negociable

WCAG 2.2 AA. Recorridos críticos operables con teclado y lector de pantalla. Foco visible siempre.
Contraste mínimo 4.5:1 en texto. Objetivos táctiles de 44 px en móvil. Respeto a `prefers-reduced-motion`.
La accesibilidad es un gate del Definition of Done nivel B, no una tarea posterior.

## Rendimiento

Primera pintura útil bajo 1,5 s en 4G. Tablas de más de 500 filas con virtualización.
Ninguna pantalla operativa depende de más de dos peticiones en cascada.
