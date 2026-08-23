# PLAN DE IMPLEMENTACIÓN — Slice 0 y Slice 1

> **Este es el documento que se ejecuta.** Cada paso está escrito para pegarse tal cual en
> Claude Code. Se avanza un paso por vez, en orden, sin adelantarse.
>
> Antes de cada paso: leer `STATE.md`. Después de cada paso: actualizar `STATE.md`.
> Al terminar un paso, marcar su casilla aquí mismo y hacer commit.

## Reglas de la sesión

1. Un paso por sesión de trabajo. No se abren dos frentes a la vez.
2. Ningún paso se marca como hecho sin ejecutar `/dod` y obtener verde en su nivel.
3. Si aparece una condición del §26 de `AGENTS.md`, se detiene y se escribe en `STATE.md`.
4. Las skills se invocan en el momento indicado, no antes: primero el problema, después el formato.
5. Datos: sintéticos hasta el paso D1. Los datos reales del personal entran cuando el Slice 0
   pase su Definition of Done completo.

---

# ETAPA A · Preparación · ~2 días

## [x] A1 · Levantar el proyecto y la infraestructura

**Objetivo:** un Laravel 11 corriendo con PostgreSQL 16 en Docker, con **dos roles de base de datos**
separados: el dueño del esquema (migraciones) y el rol de la aplicación (runtime, sin `BYPASSRLS`).

**Skills:** ninguna.

**Prompt:**
> Lee STATE.md, CLAUDE.md y ARCHITECTURE.md. Crea el proyecto Laravel 11 en `apps/platform` con
> Inertia y Vue 3, y un `docker-compose.yml` con PostgreSQL 16 y Redis. Crea dos roles de base de
> datos: `platform_owner` (dueño del esquema, ejecuta migraciones) y `platform_app` (runtime, sin
> BYPASSRLS ni superusuario), y configura `DB_APP_ROLE`. No escribas todavía ninguna migración de
> dominio. Al terminar, actualiza STATE.md.

**Entregable:** proyecto que levanta con `docker compose up` y responde en el navegador.
**DoD:** nivel A.

> Hecho el 23-ago-2026. DoD nivel A en verde salvo **TYPECHECK**, sin analizador estático
> instalable en el entorno de la sesión. `docker compose up` quedó **sin ejecutar** por la
> misma restricción de red; la sustancia del paso —los dos roles, las migraciones con el
> dueño del esquema y la aplicación respondiendo— sí se verificó contra PostgreSQL 16 y
> Redis locales. Detalle y acciones pendientes en `STATE.md`.

## [ ] A2 · Armar el harness operativo

**Objetivo:** que las reglas se cumplan solas.

**Skills:** `skill-creator` (opcional, para empaquetar el loop del harness como skill del proyecto).

**Prompt:**
> Instala Pest y Playwright. Configura el grupo de pruebas `tenant-isolation`. Conecta
> `scripts/pre-commit-tenant-isolation.sh` como hook de git y verifica que bloquea un commit
> cuando el grupo falla. Añade al arranque de la aplicación la verificación
> `TenantContext::assertRoleCannotBypassRls()`. Actualiza STATE.md.

**Entregable:** un commit con una prueba de aislamiento rota **no pasa**.
**DoD:** nivel A. Verificación manual obligatoria de que el hook bloquea de verdad.

---

# ETAPA B · Slice 0 · Foundation · ~3 semanas

> `COMMERCIAL VALUE: —` · `DoD LEVEL: B` · `DATA CLASSIFICATION: P3`
> Especificación completa: `specs/identity/SPEC.md`. Criterios de aceptación CA-01 a CA-12.

## [ ] B1 · Migraciones, RLS y auditoría bloqueada

**Objetivo:** el esquema del Slice 0 con las tres capas de aislamiento.

**Skills:** ninguna.

**Prompt:**
> Lee `docs/architecture/core-entities.md`, `docs/architecture/data-classification.md` y
> `specs/identity/SPEC.md`. Las migraciones de referencia ya están en
> `apps/platform/database/migrations`. Revísalas críticamente contra la especificación,
> corrige lo que esté mal, ejecútalas en limpio y verifica: RLS activa y forzada en las 13 tablas,
> trigger de mayoría de edad funcionando, y `audit_events` sin UPDATE ni DELETE para
> `platform_app`. Ejecuta `/tenant-test`. Actualiza STATE.md.

**Criterios:** CA-01, CA-02, CA-03, CA-06, CA-11, CA-12.
**DoD:** nivel B.

## [ ] B2 · Modelos, repositorios e invariantes

**Objetivo:** el dominio expresado en código, no en el controlador.

**Prompt:**
> Implementa los modelos y repositorios de `app/Domains/Identity` y `app/Domains/People`
> siguiendo la estructura DOMAIN / APPLICATION / INFRASTRUCTURE / INTERFACES. Implementa la máquina
> de estados de `relationships` del SPEC con clases de transición explícitas, no con booleanos.
> Los nueve invariantes del SPEC deben tener prueba unitaria propia. Ninguna lógica de negocio
> en controladores. Ejecuta `/dod`. Actualiza STATE.md.

**Criterios:** invariantes 1 a 9 del SPEC.
**DoD:** nivel B.

## [ ] B3 · Autorización RBAC + ABAC con propósito

**Objetivo:** que el rol nunca decida solo y que toda lectura P3 declare para qué.

**Prompt:**
> Lee `docs/architecture/permissions.md`. Implementa `AuthorizationContext` y las Policies de
> Laravel que lo consumen. `purpose` es obligatorio para P3 y se valida contra la lista cerrada.
> Escribe una prueba de autorización **por cada celda** de la matriz, incluidas las denegaciones.
> Verifica los siete invariantes de esa matriz. Ejecuta `/threat` sobre el resultado.
> Actualiza STATE.md.

**Criterios:** CA-04, CA-05, CA-07, CA-08.
**DoD:** nivel B.

## [ ] B4 · Auditoría append-only en el flujo real

**Prompt:**
> Implementa el registro de `AuditEvent` en toda operación relevante: lectura P3, escritura,
> cambio de estado, denegación y error. Registra actor, acción, recurso, propósito, clasificación,
> correlation id y resultado. **Nunca** contenido P3 dentro de `context`. Añade una prueba que
> falle si algún campo P3 aparece en logs o en el payload de auditoría. Actualiza STATE.md.

**Criterios:** CA-05, CA-06.
**DoD:** nivel B.

## [ ] B5 · Autenticación, MFA y roles con alcance

**Prompt:**
> Implementa autenticación con MFA obligatorio para todo rol con techo P3, sesiones seguras,
> rate limiting y protección contra enumeración de usuarios. Implementa `role_assignments` con
> alcance y vigencia: un rol vencido no otorga permisos. Cerrar una relación revoca el acceso en
> la siguiente petición, sin cerrar sesión. Actualiza STATE.md.

**Criterios:** CA-07, CA-08, CA-09.
**DoD:** nivel B.

## [ ] B6 · Tenant demo con datos sintéticos

**Objetivo:** poder demostrar y probar sin un solo dato real.

**Prompt:**
> Escribe un seeder que genere un tenant con **la forma del colegio ancla** descrita en
> `docs/anchor/colegio-finlandes.md`: una entidad jurídica, una sede rural con código DANE,
> 64 personas con la distribución real por tipo de personal, estatuto docente, nivel educativo,
> sexo y rango de edad, y `enrollment_snapshots` que sumen 883 estudiantes por grado, jornada y
> condición, con 13 en condición de discapacidad. **Todos los nombres y documentos son ficticios
> y generados.** Añade un segundo tenant para las pruebas de aislamiento. Actualiza STATE.md.

**Criterios:** CA-10, CA-11.
**DoD:** nivel A.

## [ ] B7 · Diseño de las pantallas del Slice 0

**Objetivo:** ver y corregir la interfaz **antes** de construirla.

**Skills:** **`design`** — obligatoria en este paso.

**Prompt:**
> Lee `docs/ux/personas.md`, `docs/ux/journeys.md` y `docs/ux/design-system.md`.
> Invoca la skill `design` y crea un lienzo con las pantallas del Slice 0:
> login con MFA · MY WORK de talento humano · MY WORK del rector · listado de personas con
> DataTable denso · ficha de persona · alta de persona (J1 completo, con sus errores) ·
> diálogo de propósito para leer un dato P3 · cierre de relación con impacto visible (J5) ·
> estados vacíos y de error de cada una.
> Respeta los tokens y principios del sistema de diseño. Densidad alta, estado antes que dato,
> el color nunca como único canal.

**Entregable:** lienzo de diseño revisado y aprobado por el humano antes de programar.
**DoD:** no aplica; es entrada del paso siguiente.

## [ ] B8 · Frontend del Slice 0

**Skills:** `dataviz` solo si alguna pantalla lleva indicador o gráfico.

**Prompt:**
> Implementa en Inertia + Vue 3 las pantallas aprobadas en B7. Construye primero los componentes
> base del sistema de diseño: AppShell, MyWorkInbox, DataTable, FilterBar, StatusPill, OwnerChip,
> DueDateBadge, PurposeDialog, EmptyState, ConfirmWithImpact, WizardStepper, BulkImport.
> Sin lógica de negocio en componentes. Toda tabla con `tabular-nums` y virtualización por encima
> de 500 filas. Accesibilidad WCAG 2.2 AA verificada con teclado y lector de pantalla.
> Actualiza STATE.md.

**Criterios:** J1 y J5 completos de punta a punta.
**DoD:** nivel B, incluida la compuerta de accesibilidad.

## [ ] B9 · Cierre del Slice 0

**Prompt:**
> Ejecuta `/threat` sobre todo el slice, luego `/tenant-test`, luego `/dod`. Verifica los doce
> criterios de aceptación del SPEC uno por uno y reporta el estado real de cada uno. Documenta
> la deuda encontrada en STATE.md. No marques el slice como terminado si algún criterio está en rojo.

**DoD:** nivel B completo. **Aquí termina lo que no se puede reparar después.**

---

# ETAPA C · Slice 1 · Motor de reportes obligatorios · ~3 semanas

> `COMMERCIAL VALUE: FACTURABLE` · `DoD LEVEL: B`
> Especificación completa: `specs/reporting/SPEC.md`. Criterios CA-01 a CA-12.
> **Fecha que manda: octubre de 2026.**

## [ ] C1 · Definiciones de reporte como dato

**Prompt:**
> Lee `specs/reporting/SPEC.md`. Implementa `report_definitions`, `report_fields`, `report_runs`
> y `report_values`. Carga como datos —no como código— la definición del **Módulo III del C600**
> (personal ocupado por tipo y sexo; docentes por estatuto, escalafón, nivel educativo y rango de
> edad) y la definición de la **hoja EVI** con los ítems derivables. Cada campo declara su
> `source_expression`. Cambiar el formulario no debe exigir tocar código. Actualiza STATE.md.

**Criterios:** CA-10.
**DoD:** nivel B.

## [ ] C2 · Motor de derivación y detección de vacíos

**Prompt:**
> Implementa el motor que resuelve cada `source_expression` contra el núcleo de personas y los
> snapshots de matrícula. Cada valor guarda su origen (`derivado` o `manual`) y su trazabilidad
> hasta las filas que lo produjeron. Los campos que no se pueden derivar se marcan con el motivo
> y el dato que falta. **El sistema nunca inventa una cifra.** Toda generación declara `purpose`.
> Actualiza STATE.md.

**Criterios:** CA-01, CA-02, CA-03, CA-04, CA-12.
**DoD:** nivel B.

## [ ] C3 · Carga de snapshots de matrícula

**Prompt:**
> Implementa la carga de `enrollment_snapshots` por año escolar, con importación desde hoja de
> cálculo, validación de totales y comparación contra el año anterior. Sin ningún campo que
> permita identificar a un estudiante (ADR 0003). Actualiza STATE.md.

**Criterios:** CA-03, CA-11.
**DoD:** nivel A.

## [ ] C4 · Simulador de escalafón

**Objetivo:** responder la pregunta que hoy nadie puede responder: ¿conviene subir al 80 % de la
escala del Decreto 2277?

**Skills:** `dataviz` para la comparación visual.

**Prompt:**
> Implementa el simulador: dada la planta docente por estatuto y grado, calcula el costo adicional
> de llevarla al 80 % de la escala del Decreto 2277 de 1979 y compáralo con los 3,2 puntos de
> incremento de tarifa de la Resolución 020309 de 2026 aplicados a la base de ingresos del tenant.
> **Todos los supuestos deben ser visibles y editables en la misma pantalla.** El resultado se
> lleva a un consejo directivo: si no se puede defender, no sirve. Actualiza STATE.md.

**Criterios:** CA-06.
**DoD:** nivel B.

## [ ] C5 · Exportación del C600

**Skills:** **`xlsx`** — obligatoria en este paso, **después** de que el motor ya produzca las cifras.

**Prompt:**
> Con los datos ya derivados por el motor, invoca la skill `xlsx` y genera la exportación del C600
> con la estructura de los módulos oficiales del formulario DSO-EDUC-IDR-001 versión 8.
> Incluye una hoja de trazabilidad con el origen de cada cifra, la versión de la definición
> del reporte, la fecha y el responsable. Suprime celdas con conteo menor a 5 en exportaciones
> fuera del tenant. Actualiza STATE.md.

**Criterios:** CA-07, CA-08, CA-09, CA-11.
**DoD:** nivel B.

## [ ] C6 · Tablero del ciclo de reportes

**Skills:** **`design`** para el lienzo, luego **`dataviz`** para los indicadores.

**Prompt:**
> Invoca `design` y diseña el tablero del ciclo anual: qué reporte está abierto, cuánto falta,
> qué vence, puntaje EVI proyectado y brecha contra el techo autorizado. Después invoca `dataviz`
> para definir la forma y la paleta de los indicadores, respetando el sistema de diseño y el
> requisito de que el color nunca sea el único canal. Implementa solo después de aprobado el lienzo.
> Actualiza STATE.md.

**DoD:** nivel B, incluida accesibilidad.

## [ ] C7 · Cierre del Slice 1

**Prompt:**
> Ejecuta `/legal-check` sobre las obligaciones CO-EDU-001 a CO-EDU-003 y verifica que su
> `VERIFIED_AT` tenga menos de 90 días. Después `/threat`, `/tenant-test` y `/dod`.
> Verifica los doce criterios de aceptación del SPEC. Actualiza STATE.md.

**DoD:** nivel B completo. **Aquí el producto ya se puede cobrar.**

---

# ETAPA D · Piloto en el colegio · ~2 semanas

## [ ] D1 · Carga de datos reales del personal

**Prompt:**
> Solo si el Slice 0 pasó su DoD completo. Implementa la importación de la planta real desde
> hoja de cálculo, con validación previa, informe de errores y confirmación antes de escribir.
> Registra el `PURPOSE` y la base legal del tratamiento. Los estudiantes entran únicamente como
> conteos. Actualiza STATE.md.

**Precondición dura:** B9 en verde. Sin eso, no entran datos reales.

## [ ] D2 · Auditoría UX del sistema construido

**Skills:** **`ux-refactor-lead`** — este es su momento, no antes.

**Prompt:**
> Invoca `ux-refactor-lead` y audita las pantallas construidas contra `docs/ux/design-system.md`
> y los lienzos aprobados en B7 y C6. Evalúa fricción en las tareas repetitivas de talento humano,
> accesibilidad real con teclado y lector de pantalla, rendimiento de las tablas largas y estados
> vacíos. Entrega hallazgos priorizados por impacto y esfuerzo, con parches quirúrgicos que no
> rompan la arquitectura existente. Actualiza STATE.md.

## [ ] D3 · Entrega al colegio

**Skills:** `pdf` para el expediente, `docx` para el informe institucional.

**Prompt:**
> Genera el expediente del piloto: C600 pre-llenado, hoja EVI con los puntos recuperables
> priorizados, simulación de escalafón con supuestos, y el informe para el consejo directivo.
> Deja explícito en todos los documentos que la plataforma apoya el cumplimiento y no reemplaza
> al profesional responsable.

---

## Después del piloto

| Slice | Contenido | Cuándo |
|---|---|---|
| 2 | Ciclo SG-SST y autoevaluación Res. 0312 | diciembre 2026 |
| 3 | Enfermería ocupacional del personal | primer trimestre 2027 |
| 4 | Matriz de peligros e inspecciones | primer semestre 2027 |
| 6 | Modo Inspección | antes del 31 de julio de 2027 |

`ROADMAP.md` mantiene la secuencia completa.
