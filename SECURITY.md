# SECURITY.md

## Modelo de responsabilidad de datos

- El **cliente** es Responsable del tratamiento.
- La **plataforma** es Encargada.

Requisitos derivados, todos de producto y no de letra menuda:

1. Contrato de encargo (DPA) por tenant, versionado y aceptado en producto.
2. Registro público de **subencargados**, incluidos proveedores de IA.
3. Política de retención por clasificación. La retención legal vence al derecho de supresión.
4. Exportación completa y portabilidad al terminar el contrato; borrado verificable después.
5. Notificación de incidentes al Responsable con tiempos definidos.
6. Soporte a los deberes del cliente ante la SIC, incluido el registro de bases de datos.
7. Autorizaciones y aviso de privacidad, con tratamiento reforzado para menores de edad:
   autorización del representante legal, interés superior del menor y su opinión según madurez.

## Clasificación de datos

| Nivel | Contenido | Controles |
|---|---|---|
| P0 | Público | — |
| P1 | Interno | — |
| P2 | Personal | RLS + auditoría de escritura |
| P3 | Sensible | + auditoría de lectura, cifrado de campo, propósito declarado |
| P4 | Clínico | + conexión separada, credenciales propias, doble autorización, lectura siempre auditada |

## Controles obligatorios

MFA · SSO OIDC · SAML · SCIM · sesiones seguras · rotación de claves · secret manager ·
TLS · cifrado at-rest · KMS · cifrado de campo P3/P4 · RLS · aislamiento multi-tenant ·
signed URLs · antivirus de archivos · validación MIME · límites de archivo · CSP · CSRF ·
rate limiting · protección contra enumeración · logging de seguridad · alertas · backup ·
PITR · disaster recovery probado.

## Prohibiciones absolutas

- Información clínica en logs, trazas, telemetría o mensajes de error.
- Secretos o PII/P3/P4 en herramientas de observabilidad.
- Datos reales en fixtures, seeds o entornos de demostración.
- Acceso clínico concedido por rol administrativo.
- Envío de P3/P4 a un proveedor de IA no declarado como subencargado y aceptado por el cliente.

## Retención mínima conocida

| Dato | Retención | Fuente |
|---|---|---|
| Historia clínica ocupacional | Mínimo 20 años | Resolución 1843 de 2025 |
| Evidencias del SG-SST | Según Decreto 1072 de 2015 y política interna del cliente | Decreto 1072 de 2015 |

Toda retención se implementa como política de datos, nunca como criterio del usuario.

## Respuesta a incidentes

Una filtración cross-tenant o una fuga de datos P4 es **P0**: detiene el desarrollo,
activa el runbook de `runbooks/`, y se notifica al Responsable antes de continuar
cualquier otra tarea.
