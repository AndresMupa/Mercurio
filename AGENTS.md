# MASTER HARNESS v2 — Plataforma Enterprise de RR. HH., SST, Salud Ocupacional y Enfermería

> Versión 2.0 · Colombia como primera jurisdicción · Stack Laravel
> Cambios frente a v1: Fase Cero reducida de 18 a 6 artefactos, Definition of Done graduado,
> capa comercial obligatoria, modelo Responsable/Encargado, ciclo anual SG-SST,
> evidencia con valor probatorio, frontera de habilitación en salud y gestión de contexto entre sesiones.

---

## 1. Rol operativo

Trabaja como un equipo compuesto por especialistas senior en: arquitectura de producto,
arquitectura de software empresarial, UX, seguridad, datos, privacidad, RR. HH.,
Seguridad y Salud en el Trabajo, enfermería, QA/SDET y DevOps/SRE.

Añade dos roles que la v1 no tenía y que deciden si el producto existe o no:

- **Responsable comercial.** Ningún slice se construye sin declarar qué desbloquea: demo, venta o factura.
- **Oficial de protección de datos.** Ningún dato de persona se crea sin clasificación, base de tratamiento, retención y titular del derecho.

No empieces programando. Primero comprende, modela y valida.
Pero tampoco documentes durante semanas: la Fase Cero de este harness dura **cinco días** (§28).

---

## 2. Propósito y frontera del producto

Plataforma SaaS multiempresa, multisedes y preparada para múltiples países, adecuada para
colegios, universidades, PyMEs, grandes empresas, grupos empresariales, multinacionales,
contratistas y organizaciones con múltiples centros de trabajo.

**Lo que la plataforma hace:** personas, organización, RR. HH., SG-SST, salud ocupacional,
enfermería, cumplimiento, evidencia y analítica.

**Lo que la plataforma NO hace en v1, por decisión explícita:**

| Fuera de alcance | Razón |
|---|---|
| Liquidación de nómina | Motor de responsabilidad legal que cambia cada año (Ley 2466 de 2025 escalona recargos hasta 2027). Se **integra**, no se construye. |
| Facturación electrónica / DIAN | Dominio ajeno, alta carga regulatoria, cero diferenciación. |
| Práctica clínica asistencial | Puede exigir habilitación en salud del cliente. Ver §11. |
| Decisiones automatizadas sobre derechos | Prohibido por diseño. Ver §18. |

Escribir "no" aquí es una decisión de arquitectura, no una limitación. Cada dominio que
entra multiplica permisos, pruebas y superficie legal.

---

## 3. Criterio rector

La plataforma integra RR. HH. + SST + Salud Ocupacional + Enfermería + Cumplimiento +
Analítica + Gestión documental **sin mezclar indebidamente información administrativa,
laboral y clínica**.

No digitalices burocracia. Diseña procesos que reduzcan trabajo humano.

Prioriza en este orden:

1. operabilidad humana
2. claridad
3. seguridad
4. cumplimiento
5. mantenibilidad
6. automatización
7. funcionalidades accesorias

### Regla comercial (nueva en v2)

Todo slice declara su valor comercial con una de estas tres etiquetas:

```
COMMERCIAL VALUE: DEMOABLE | VENDIBLE | FACTURABLE
```

- **DEMOABLE** — se puede mostrar en una reunión de ventas.
- **VENDIBLE** — un cliente firmaría por esto.
- **FACTURABLE** — genera cobro recurrente o de implementación.

Un slice que no consigue ninguna de las tres etiquetas **no se construye ahora**: va al backlog.
Máximo un slice puramente estructural (sin etiqueta) por cada tres con etiqueta.

---

## 4. Modelo de identidad y relaciones

No uses `Employee` como entidad raíz universal.

```
PERSON → IDENTITY → ORGANIZATION MEMBERSHIP → RELATIONSHIP → ROLE/POSITION → WORKFLOW/CASE
```

Una persona mantiene relaciones simultáneas y con vigencia propia: empleado, trabajador
temporal, contratista, **trabajador de contratista** (sin vínculo laboral con el tenant
pero con obligaciones de SST en la sede — es donde el SG-SST se rompe en la práctica),
aprendiz, docente, estudiante, acudiente, proveedor, visitante, profesional SST, profesional de salud.

Toda relación tiene: `type`, `valid_from`, `valid_to`, `legal_entity_id`, `site_id`, `status`.
Una persona sin relación vigente **no aparece** en las bandejas operativas, pero conserva su historia.

Estructura organizacional:

```
Organization → LegalEntity → Country → Site → WorkCenter → BusinessUnit → Department → Team → Position
```

Debe soportar grupos empresariales con varias entidades jurídicas bajo un mismo tenant.
El colegio ancla lo prueba de inmediato: un mismo tenant con docentes (empleados),
estudiantes (sin relación laboral) y acudientes (terceros con derechos sobre datos de menores).

---

## 5. Dominios funcionales (bounded contexts)

| Contexto | Alcance | Clasificación máxima |
|---|---|---|
| **Identity & Access** | identidades, usuarios, MFA, SSO, memberships, roles, políticas | P2 |
| **People & Organization** | personas, cargos, estructura, sedes, relaciones | P2 |
| **HR Core** | contratos, vinculación, onboarding, movimientos, documentos, ausencias, novedades, offboarding | P3 |
| **Talent** | competencias, objetivos, formación, evaluaciones, planes de desarrollo | P3 |
| **SST** | SG-SST, ciclo anual, estándares, peligros, riesgos, controles, indicadores, inspecciones, EPP, contratistas, emergencias, comités, auditorías | P3 |
| **Incidents & Cases** | accidentes, incidentes, actos y condiciones inseguras, investigaciones, evidencias, planes de acción | P3 |
| **Occupational Health** | evaluaciones ocupacionales, **conceptos** de aptitud, recomendaciones, restricciones, reincorporación, vigilancia epidemiológica | P3 (concepto) |
| **Clinical Vault** | historia clínica ocupacional y de enfermería | **P4** |
| **Nursing** | atenciones, triage, signos vitales, primeros auxilios, alergias, medicamentos autorizados, remisiones | **P4** |
| **Compliance** | obligaciones, normas, responsables, evidencias, vencimientos, cambios regulatorios | P2 |
| **Evidence & Signature** | firma electrónica, sellado de tiempo, cadena de custodia probatoria | P3 |
| **Billing & Tenancy** | planes, límites, suscripción, onboarding y offboarding de cliente, portabilidad | P2 |
| **Analytics** | indicadores agregados, tableros, exportación a BI | P1 agregado |

Regla dura: **Analytics jamás lee del Clinical Vault.** Consume agregados anonimizados o no consume.

---

## 6. Colombia Compliance Pack

Antes de implementar cualquier obligación regulatoria, consulta fuentes oficiales y registra
el estado de la norma: `VIGENTE | MODIFICADA | DEROGADA | SUSTITUIDA | REGLAMENTADA`.

**No des por vigente una norma sin comprobarlo.** Cuando la interpretación jurídica sea
incierta, marca `REQUIRES_LEGAL_VALIDATION` y no implementes lógica que dependa de esa lectura.

### Estado verificado a agosto de 2026

| Norma | Estado | Impacto directo en arquitectura |
|---|---|---|
| **Decreto 1072 de 2015** (Libro 2, Parte 2, Título 4, Cap. 6) | Vigente | Estructura el SG-SST como ciclo PHVA. Obliga a matriz legal actualizada, indicadores y auditoría. |
| **Resolución 0312 de 2019** | Vigente | Estándares mínimos por tamaño y riesgo. Autoevaluación anual + plan de mejoramiento. |
| **Circular 027 de 2026** (MinTrabajo, 26-feb-2026) | Vigente | Fija el calendario de reporte: autoevaluación en diciembre, **registro hasta el 31 de julio** en `sgrl.mintrabajo.gov.co`. **Prueba de que las fechas no se hardcodean.** |
| **Resolución 1843 de 2025** | Vigente desde 7-nov-2025 | Deroga la Res. 2346 de 2007 (y 1918/2009, 1075/1992, 4050/1994). Ver detalle abajo. |
| **Ley 1562 de 2012** | Vigente | Sistema General de Riesgos Laborales. |
| **Ley 2466 de 2025** (reforma laboral) | Vigente, con escalonamiento | Jornada 42 h. Recargo dominical 80 % (jul-2025) → 90 % (jul-2026) → 100 % (jul-2027). Debido proceso disciplinario con garantías mínimas. Licencias nuevas, incluida la de acudiente por obligaciones escolares. |
| **Ley 1581 de 2012** + decretos reglamentarios | Vigente | Datos de salud = sensibles. RNBD ante la SIC. Responsable vs. Encargado. |
| **Resolución 3100 de 2019** | Vigente | Habilitación de servicios de salud. Frontera crítica para enfermería escolar (§11). |
| **Ley 527 de 1999** | Vigente | Valor probatorio de mensajes de datos y firma electrónica (§10). |

### Detalle crítico: Resolución 1843 de 2025

Cuatro consecuencias que **deben** materializarse en el modelo de datos:

1. **Seis tipos de evaluación** — pre-ingreso, periódica (programada o por cambio de ocupación),
   egreso, post-incapacidad, retorno laboral, seguimiento o control. Es un `enum`, no texto libre.
2. **La historia clínica ocupacional no viaja al empleador.** Solo viaja el **concepto ocupacional**
   con restricciones, recomendaciones y temporalidad. Dos entidades, dos APIs, dos permisos.
3. **Está prohibido el uso de "no apto".** El `enum` del concepto **no debe contener ese valor**,
   y debe existir una validación que lo rechace. Esto es una regla de dominio, no una guía de estilo.
4. **Conservación mínima de 20 años.** La retención es política de datos, no criterio del usuario.
   El derecho de supresión no puede borrar lo que la ley obliga a conservar.

### Frontera de responsabilidad

La plataforma **apoya** el cumplimiento. No reemplaza al abogado, al médico, al profesional
SST ni al responsable humano. Ninguna pantalla debe sugerir lo contrario, y el texto de la
interfaz tampoco.

---

## 7. SG-SST como sistema operativo, no como repositorio documental

### El ciclo anual es una entidad de primera clase

El error más caro que puede cometer este producto es modelar el SG-SST como una carpeta.
El SG-SST es un **ciclo con fechas** que se repite cada año:

```
SGSSTCycle (año, tenant, entidad jurídica, sede)
  → evaluación inicial / autoevaluación de estándares mínimos
  → puntaje y nivel de cumplimiento
  → plan de mejoramiento
  → plan anual de trabajo del año siguiente
  → ejecución (actividades, evidencias)
  → revisión por la dirección
  → cierre y archivo probatorio
```

Sin `SGSSTCycle`, el sistema funciona el primer año y muere el segundo.

### Alcance funcional mínimo

Evaluación inicial · plan anual · política · objetivos · roles y responsabilidades ·
matriz legal · matriz de peligros · valoración de riesgos · controles · indicadores ·
inspecciones · acciones correctivas · acciones preventivas · auditorías · revisión por
dirección · COPASST · comité de convivencia · capacitaciones · inducciones · reinducciones ·
EPP · gestión de contratistas · emergencias · simulacros · incidentes · accidentes ·
investigaciones · enfermedades laborales · ausentismo · reincorporación · sistemas de
vigilancia epidemiológica · riesgo psicosocial.

Cada actividad gestionable incluye, sin excepción:

```
OWNER · DUE DATE · STATUS · EVIDENCE · COMMENTS · APPROVAL · AUDIT HISTORY · REMINDERS · ESCALATION
```

### Modo Inspección (funcionalidad ancla)

Nadie compra "software de SG-SST". Compran **no ser sancionados y pasar la visita**.

Debe existir una función que, en un clic, arme el expediente probatorio de un ciclo:
autoevaluación, plan de mejoramiento, evidencias firmadas, trazabilidad de aprobaciones,
índice navegable y exportable, con los documentos en el orden en que los pide un inspector.

Esta funcionalidad es el ancla de retención del producto y debe entrar en el roadmap
temprano, no al final.

---

## 8. Privacidad, seguridad y modelo de responsabilidad

### Responsable y Encargado (ausente en v1, bloqueante para vender)

- El **cliente** es Responsable del tratamiento.
- La **plataforma** es Encargada.

Consecuencias que son requisitos de producto, no letra menuda:

1. Contrato de encargo (DPA) por tenant, versionado y aceptado en producto.
2. Registro de **subencargados**, incluidos proveedores de IA. Un LLM que procese datos de un tenant es un subencargado y debe estar declarado.
3. Política de retención por clasificación, con la regla de que la retención legal vence al derecho de supresión.
4. Portabilidad y exportación completa al terminar el contrato, y borrado verificable después del período pactado.
5. Notificación de incidentes de seguridad al Responsable, con tiempos definidos.
6. Soporte a los deberes del cliente ante la SIC, incluido el registro de bases de datos.
7. Aviso de privacidad y gestión de autorizaciones, con tratamiento reforzado para menores de edad: autorización del representante legal, interés superior del menor y su opinión según madurez.

### Autorización

Zero Trust y least privilege. RBAC **+** ABAC: un rol por sí solo nunca determina el acceso.

Toda decisión de autorización evalúa:

```
USER · TENANT · LEGAL ENTITY · SITE · RELATIONSHIP · RESOURCE · ACTION · PURPOSE · DATA CLASSIFICATION
```

`PURPOSE` es obligatorio para P3 y P4: se registra para qué se accedió, no solo quién accedió.

### Clasificación de datos

| Nivel | Contenido | Controles adicionales |
|---|---|---|
| **P0** | Público | — |
| **P1** | Interno | — |
| **P2** | Personal | RLS, auditoría de escritura |
| **P3** | Sensible | RLS, auditoría de lectura y escritura, cifrado de campo, propósito declarado |
| **P4** | Clínico / altamente restringido | Todo lo anterior + conexión y credenciales separadas + doble autorización + lectura siempre auditada |

### Controles obligatorios

MFA · SSO OIDC · SAML enterprise · SCIM · sesiones seguras · rotación de claves · secret
manager · TLS · cifrado at-rest · KMS · cifrado de campo para P3/P4 · RLS · aislamiento
multi-tenant · signed URLs · antivirus de archivos · validación MIME · límites de archivo ·
CSP · CSRF · rate limiting · protección contra enumeración · logging de seguridad · alertas ·
backup · PITR · disaster recovery probado.

**Nunca** registres información clínica en logs, trazas, telemetría o mensajes de error.
**Nunca** envíes secretos ni PII/P3/P4 a herramientas de observabilidad.

---

## 9. Separación del dominio clínico

Distingue obligatoriamente tres cosas que la gente confunde:

```
EMPLOYMENT RECORD   →  administrativo, RR. HH.
OCCUPATIONAL CONCEPT →  restricciones y recomendaciones, visible al empleador
CLINICAL RECORD      →  reservado, Clinical Vault
```

No copies diagnósticos ni información clínica detallada hacia RR. HH., ni siquiera "para
facilitar el reporte". APIs separadas, políticas separadas, conexión de base de datos separada
con credenciales propias, de modo que **un error en el código de RR. HH. sea físicamente
incapaz de leer el vault**.

Evalúa y documenta en ADR: schema separado vs. base de datos separada, y claves criptográficas
independientes.

---

## 10. Evidencia con valor probatorio

En v1 la firma electrónica figuraba como integración. Es un error de encuadre: la evidencia
sin valor probatorio es un PDF, y un PDF es gratis. Lo que un cliente paga es **poder demostrar**.

Toda evidencia crítica — acta de COPASST, concepto de aptitud, entrega de EPP, inducción,
investigación de accidente, autorización de acudiente — debe registrar:

```
hash del contenido · sello de tiempo · identidad verificada del firmante · método de firma ·
versión inmutable · cadena de custodia · no repudio (Ley 527 de 1999)
```

Un documento firmado nunca se edita: se versiona. Un acta aprobada nunca se borra: se anula
con motivo, actor y fecha.

---

## 11. Enfermería escolar: frontera de habilitación (riesgo no declarado en v1)

La Resolución 3100 de 2019 regula la habilitación de servicios de salud. Un colegio que preste
servicios asistenciales puede quedar sujeto a habilitación ante el REPS.

**El producto no puede convertir a un colegio en prestador de salud no habilitado.**

Frontera de diseño, que además es argumento de venta:

| El módulo SÍ modela | El módulo NO modela |
|---|---|
| Primeros auxilios y su registro | Diagnóstico |
| Administración de medicamento **con orden médica y autorización del acudiente** | Prescripción |
| Signos vitales como observación puntual | Tratamiento |
| Notificación a acudiente y remisión | Historia clínica asistencial general |
| Retiro autorizado del estudiante | Continuidad de atención clínica |

Cada atención escolar registra: estudiante, acudientes, contactos de emergencia, EPS,
condiciones autorizadas, alergias, restricciones, consentimiento vigente, evento, acciones,
notificación, remisión y seguimiento.

**Una atención de enfermería escolar no se modela como historia laboral.** Son dominios distintos
con titulares de derechos distintos.

Esta frontera se documenta en un ADR y se refleja en la interfaz y en los términos de servicio.

---

## 12. Diseño de experiencia humana

Antes de construir cualquier tablero, responde: ¿qué debe hacer hoy esta persona? ¿qué está
vencido? ¿qué requiere aprobación? ¿qué representa riesgo? ¿qué persona necesita seguimiento?
¿qué evidencia falta? ¿qué acción depende de este usuario?

Cada rol tiene una bandeja **MY WORK** con pendientes, alertas, casos, vencimientos,
aprobaciones y seguimientos.

Evita formularios largos. Prefiere progressive disclosure, autosave, drafts, validación
contextual, wizard donde aporte, smart defaults, búsqueda global, bulk actions, plantillas,
duplicación segura e historial visible.

En todo proceso importante muestra siempre: `STATUS · OWNER · NEXT ACTION · DEADLINE`.

Diseña primero para humanos. Automatiza después.

---

## 13. Accesibilidad

Objetivo mínimo **WCAG 2.2 AA**. Los recorridos críticos funcionan con teclado, lector de
pantalla, táctil, zoom y contraste suficiente. El color nunca es el único mecanismo para
distinguir un estado.

---

## 14. Desktop, móvil y trabajo de campo

Desktop es la experiencia principal para tareas administrativas.
Mobile es crítico para trabajador, docente, enfermería, brigadista, inspector, responsable SST
y aprobaciones gerenciales.

Considera PWA/offline solo donde aporte valor real: inspecciones, reportes de campo, brigadas,
checklists. **No habilites almacenamiento clínico offline** sin haber definido antes
sincronización, cifrado, resolución de conflictos y borrado seguro.

---

## 15. Arquitectura técnica

Comienza con **modular monolith**. No introduzcas microservicios sin necesidad demostrable
y ADR aprobado.

Organiza cada dominio como `DOMAIN · APPLICATION · INFRASTRUCTURE · INTERFACES`.

No pongas lógica de negocio en componentes Vue ni en controladores HTTP.
Usa eventos internos cuando reduzcan acoplamiento. Prepara transactional outbox para
integraciones críticas. Toda operación crítica es idempotente.

### Materialización en Laravel

| Concepto | Implementación |
|---|---|
| Bounded context | Módulo bajo `app/Domains/<Context>` con su propio `Domain/`, `Application/`, `Infrastructure/`, `Http/` |
| Tenant guard | Middleware + global scope + `SET LOCAL app.tenant_id` por request |
| RLS | Políticas PostgreSQL sobre `tenant_id`, activas también para el usuario de aplicación |
| ABAC | Objeto `AuthorizationContext` inyectado en Policies; el rol es un input, no la decisión |
| Clinical Vault | Conexión `clinical` con credenciales propias, modelos aislados, sin relaciones Eloquent hacia HR |
| Auditoría | Tabla append-only, sin `update`/`delete` concedidos al usuario de aplicación |
| Máquinas de estado | Clases de transición explícitas, no columnas booleanas |
| Colas | Horizon, con cola dedicada y de menor concurrencia para trabajos que tocan P4 |

---

## 16. Multi-tenancy

Toda entidad pertenece a un tenant. Defensa en profundidad:
**tenant guards de aplicación + RLS en base de datos + tests de aislamiento**.

Una filtración entre tenants es **P0 SECURITY INCIDENT**.

Mantén pruebas automáticas que intenten activamente acceder a recursos de otras organizaciones,
y que fallen el build si tienen éxito.

Prepara la arquitectura para soportar en el futuro: shared database, dedicated schema,
dedicated database, dedicated region. No los implementes antes de tener el cliente que los pague.

---

## 17. Integraciones

Adapters versionados para: Microsoft Entra ID, Google Workspace, Okta, nómina, ERP,
proveedores de exámenes ocupacionales, ARL, EPS cuando sea legal y técnicamente posible,
sistemas académicos, LMS, control de acceso, firma electrónica, correo, SMS y WhatsApp
mediante proveedores autorizados.

**El dominio nunca depende directamente de una API externa.** Siempre adapter, siempre
contrato propio, siempre modo degradado.

Prioridad real de integración: **nómina primero**. Es lo que evita competir con la suite
instalada del cliente y lo que convierte a la plataforma en complemento en vez de reemplazo.

---

## 18. Inteligencia artificial

La IA es copiloto, no autoridad.

**Puede:** resumir, buscar, clasificar documentos, ayudar a redactar, extraer datos de
documentos, detectar pendientes, encontrar inconsistencias, explicar requisitos, sugerir acciones.

**Exige intervención humana identificada:** diagnóstico, tratamiento, aptitud médica, sanciones,
terminaciones, decisiones disciplinarias, valoración de riesgo legal y cualquier decisión que
afecte derechos.

Todo resultado sensible generado con IA se marca como tal, con modelo, versión y fecha.

No entrenes modelos externos con datos P3/P4. No envíes P3/P4 a un proveedor de IA que no esté
declarado como subencargado en el DPA del tenant y aceptado por el cliente.

---

## 19. Auditoría

`AuditEvent` append-only para toda operación relevante. Registra como mínimo:
actor, action, resource, resourceId, organization, timestamp, correlation id, source, result.

Para acciones sensibles registra además el **contexto de autorización y el propósito**,
sin copiar contenido clínico.

Los registros críticos no se modifican en silencio: se versionan o se registran como eventos.
La integridad de la auditoría es un invariante del sistema, no una funcionalidad.

---

## 20. Workflows y estados

No representes procesos complejos con booleanos. Usa máquinas de estado.

```
DRAFT → SUBMITTED → UNDER_REVIEW → CHANGES_REQUESTED → APPROVED → ACTIVE → ARCHIVED
```

Cada transición especifica: actor permitido, precondiciones, validaciones, efectos,
notificaciones y evento de auditoría.

Caso obligatorio en Colombia tras la Ley 2466 de 2025: el **proceso disciplinario** es una
máquina de estados con garantías mínimas verificables (notificación, descargos, contradicción
de pruebas, decisión motivada, recurso). Si el software no puede demostrar que esas etapas
ocurrieron, el cliente pierde el caso.

---

## 21. Observabilidad

Toda operación se correlaciona con un request/correlation ID. Implementa structured logs,
métricas, tracing distribuido, error tracking, health checks, dependency checks y monitoreo de colas.

No almacenes información clínica en herramientas de observabilidad. Nunca.

---

## 22. Calidad

Cada funcionalidad requiere: unit, integration, authorization, tenant-isolation, E2E,
accesibilidad y negative tests. Para dominios sensibles, además threat tests.

Compilar no significa terminar. Una funcionalidad está terminada cuando cumple sus criterios
de aceptación y su nivel de Definition of Done.

---

## 23. Definition of Done graduado

La v1 exigía catorce gates para todo. En la práctica eso significa que nada se termina.
El rigor se gradúa por **clasificación de datos y superficie de riesgo**, no por gusto.

### Nivel A — cambios P0–P2 que no tocan autorización, tenancy ni auditoría

```
BUILD PASS · LINT PASS · TYPECHECK PASS · UNIT PASS · INTEGRATION PASS · DOCUMENTATION UPDATED
```

### Nivel B — cambios P3, o que tocan autorización, tenancy, auditoría o dinero

```
Nivel A
+ AUTHORIZATION PASS · TENANT ISOLATION PASS · E2E PASS
+ ACCESSIBILITY PASS · MIGRATIONS PASS · OBSERVABILITY ADDED
```

### Nivel C — cualquier cosa que toque P4, Clinical Vault, menores de edad o evidencia probatoria

```
Nivel B
+ SECURITY REVIEW PASS · THREAT TESTS PASS
+ DATA RETENTION REVIEW · LEGAL CHECK REGISTERED
+ REVISIÓN HUMANA EXPLÍCITA ANTES DE MERGE
```

El nivel se declara al abrir el slice, no al cerrarlo. Bajar de nivel exige justificación escrita.

---

## 24. Loop obligatorio

```
DISCOVER → INSPECT → REGULATORY CHECK → COMMERCIAL CHECK → MODEL → UX → THREAT MODEL →
PLAN → IMPLEMENT → VERIFY → ATTACK → REVIEW → DOCUMENT → COMMIT → STATE UPDATE → NEXT
```

- **DISCOVER** — entiende el contexto real del usuario.
- **INSPECT** — revisa código, dominio, base de datos, permisos y pruebas afectados.
- **REGULATORY CHECK** — determina efectos jurídicos o clínicos. Registra la norma consultada y su estado.
- **COMMERCIAL CHECK** *(nuevo)* — declara la etiqueta DEMOABLE / VENDIBLE / FACTURABLE.
- **MODEL** — entidades, estados, reglas, invariantes.
- **UX** — recorrido humano completo, incluido el error.
- **THREAT MODEL** — abuso, filtración, escalamiento de privilegios, error humano.
- **PLAN** — el cambio mínimo coherente.
- **IMPLEMENT** — una vertical slice funcional de punta a punta.
- **VERIFY** — pruebas del nivel de DoD declarado.
- **ATTACK** — intenta romper autorización, tenancy, validaciones y estados.
- **REVIEW** — deuda, duplicación, inconsistencias, riesgos.
- **DOCUMENT** — spec y ADR cuando corresponda.
- **COMMIT** — commit lógico y autocontenido.
- **STATE UPDATE** *(nuevo)* — actualiza `STATE.md` antes de terminar la sesión.

---

## 25. Reglas de comportamiento del agente

Nunca modifiques áreas grandes sin comprender el contexto.
Nunca inventes obligaciones legales ni cites normas sin verificar su estado.
Nunca escondas errores con `catch` vacío.
Nunca desactives tests para obtener una ejecución verde.
Nunca uses tipos laxos como atajo sin justificación documentada.
Nunca publiques una API sin autorización explícita.
Nunca crees una tabla multi-tenant sin aislamiento por tenant.
Nunca introduzcas datos sensibles reales en fixtures, seeds o demos.
Nunca almacenes secretos en el repositorio.
Nunca otorgues acceso clínico por el solo hecho de tener un rol administrativo.
Nunca alteres una migración ya desplegada.
Nunca mezcles refactors grandes con funcionalidades nuevas.
Nunca continúes si falla una prueba crítica de aislamiento.
Nunca cierres una sesión sin actualizar `STATE.md`.

---

## 26. Stop conditions

Detente y documenta cuando aparezca:

posible exposición cross-tenant · ambigüedad jurídica significativa · riesgo de pérdida de
información clínica · migración destructiva sin plan · cambio incompatible de API ·
falta de autorización para información sensible · operación irreversible sin backup ·
decisión clínica que el software intentaría ejecutar de forma autónoma ·
pérdida de integridad de auditoría · dato de menor de edad sin autorización de representante legal ·
retención legal en conflicto con una solicitud de supresión.

**No improvises para poder seguir avanzando.**

---

## 27. Vertical slices

No construyas toda la base de datos y después toda la interfaz. Entrega recorridos completos.

Cada slice atraviesa:

```
UI → API → DOMAIN → DB → AUTH → AUDIT → EVIDENCE → TEST
```

y declara, antes de empezar:

```
SLICE: <nombre>
COMMERCIAL VALUE: DEMOABLE | VENDIBLE | FACTURABLE
DoD LEVEL: A | B | C
DATA CLASSIFICATION: P0..P4
BOUNDED CONTEXTS: <lista>
```

---

## 28. Fase Cero mínima — cinco días, seis artefactos

La v1 pedía dieciocho documentos antes de escribir código. Eso son semanas de escritura sin
validación, y en un producto de un solo constructor es la causa número uno de abandono.
Peor aún: los documentos escritos antes de ver código real quedan invalidados por el primer slice.

**Produce solo estos seis antes de programar:**

| # | Artefacto | Archivo | Tiempo |
|---|---|---|---|
| 1 | Domain Map + Context Map | `docs/architecture/domain-map.md` | 1 día |
| 2 | Permission Matrix (roles × recursos × acciones × clasificación) | `docs/architecture/permissions.md` | 1 día |
| 3 | Data Classification Matrix | `docs/architecture/data-classification.md` | ½ día |
| 4 | Colombia Legal Matrix (semilla, solo lo que toca el slice 0 y 1) | `docs/legal/legal-matrix.md` | 1 día |
| 5 | Core Entity Model (Person, Tenant, Org, Relationship, AuditEvent) | `docs/architecture/core-entities.md` | 1 día |
| 6 | Criterios de aceptación del Slice 0 | `specs/identity/SPEC.md` | ½ día |

**Los otros doce artefactos de la v1 se producen durante el slice que los necesita**, como
requisito de su Definition of Done: personas y journeys con el primer slice de UI, threat model
con el primer slice que toque P3, DR strategy antes del primer dato productivo real, ADR cuando
haya una decisión que revertir cueste dinero.

Documentación **just-in-time**, no just-in-case.

---

## 29. Secuencia de implementación

La v1 ordenaba por capas técnicas. La v2 ordena por **valor comercial acumulado**, sin sacrificar
los cimientos: identidad, tenancy, autorización, auditoría y clasificación se resuelven primero
porque después son irreparables.

| # | Slice | Valor | DoD |
|---|---|---|---|
| 0 | Foundation: tenant, persona, organización, sede, relación, auth, MFA, auditoría, clasificación | — (único slice estructural permitido) | B |
| 1 | Ciclo SG-SST + autoevaluación Res. 0312 + plan de mejoramiento | **VENDIBLE** | B |
| 2 | Enfermería escolar: consentimientos, contactos, evento, medicamento autorizado, notificación | **FACTURABLE** | C |
| 3 | Matriz de peligros, inspecciones, acciones correctivas, evidencias | VENDIBLE | B |
| 4 | Salud ocupacional: evaluaciones, concepto de aptitud, restricciones, reincorporación + Clinical Vault | FACTURABLE | C |
| 5 | HR Core mínimo: contratos, documentos, ausencias, firma electrónica | VENDIBLE | B |
| 6 | Modo Inspección: expediente probatorio exportable | **FACTURABLE** (ancla de retención) | B |
| 7 | Incidentes e investigación de accidentes | VENDIBLE | B |
| 8 | Motor de cumplimiento y matriz legal viva | FACTURABLE | B |
| 9 | Analítica e indicadores, exportación a BI | VENDIBLE | A |
| 10 | Integraciones: nómina, directorio corporativo, sistema académico | FACTURABLE | B |
| 11 | Enterprise: SSO/SAML, SCIM, multi-entidad, residencia por región | FACTURABLE | C |
| 12 | Segundo país | — | C |

No construyas características avanzadas antes de resolver identidad, tenancy, autorización,
auditoría y clasificación de datos.

---

## 30. Formato de cada iteración

Antes de tocar código responde:

**Objective** · **User** · **Current behavior** · **Desired behavior** · **Domain impact** ·
**Regulatory impact** · **Commercial value** · **Security impact** · **UX flow** ·
**Implementation plan** · **Acceptance criteria** · **Tests** · **DoD level**

Al finalizar entrega:

**Implemented** · **Files changed** · **DB changes** · **Security validation** ·
**Tests executed** · **Results** · **Remaining risks** · **STATE.md updated** ·
**Next recommended slice**

---

## 31. Gestión de contexto entre sesiones

Este es el punto donde los harness fallan en la práctica: a la cuarta sesión el agente ya no
recuerda que existe un harness.

Mecanismos obligatorios:

1. **`STATE.md`** — se lee al abrir y se escribe al cerrar. Contiene: slice actual, decisiones
   tomadas, bloqueos, deuda conocida y próximo paso concreto.
2. **`specs/<contexto>/SPEC.md`** — se lee obligatoriamente antes de tocar ese contexto.
3. **Comandos de repositorio** en `.claude/commands/` — `/slice`, `/legal-check`, `/threat`,
   `/tenant-test`, `/dod`.
4. **Hook de pre-commit** que ejecuta los tests de aislamiento multi-tenant y bloquea el commit
   si fallan. La regla que depende de la disciplina humana no es una regla.
5. **ADR** para toda decisión cuya reversión cueste más de un día.

---

## 32. Primera instrucción

Todavía **no programes**.

Ejecuta la Fase Cero mínima (§28) y entrega, en este orden:

1. Domain Map y Context Map.
2. Personas y roles del colegio ancla, con sus bandejas MY WORK.
3. Matriz de permisos, con la separación clínica explícita.
4. Matriz de clasificación de datos.
5. Matriz legal semilla para los slices 0 y 1, con estado verificado de cada norma.
6. Modelo de entidades núcleo.
7. Criterios de aceptación del Slice 0.
8. Riesgos P0/P1/P2.
9. Preguntas que realmente bloqueen arquitectura — no preguntas de preferencia.

Después define el MVP contra el colegio ancla y construye el roadmap por vertical slices
según §29.

No generes código hasta comprobar que la arquitectura inicial es coherente
**y que el Slice 0 tiene criterios de aceptación verificables**.
