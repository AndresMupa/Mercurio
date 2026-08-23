# ADR 0001 — Stack: Laravel + Inertia + Vue 3 + PostgreSQL

**Fecha:** 2026-08-23 · **Estado:** Aceptada

## Contexto

Producto enterprise multi-tenant con datos clínicos, construido inicialmente por un único
desarrollador senior. La propuesta original planteaba TypeScript + NestJS + Next.js.

## Decisión

Laravel 11 (PHP 8.3) + Inertia + Vue 3 + PostgreSQL 16.

## Razones

1. Velocidad de ejecución del equipo real, que es el factor que más incide en que el producto exista.
2. PostgreSQL RLS, políticas ABAC, colas, auditoría y aislamiento multi-tenant funcionan igual
   de bien desde Laravel. La garantía de seguridad vive en la base de datos, no en el lenguaje.
3. El riesgo mayor de este producto es un error de autorización en el dominio clínico. Ese riesgo
   sube si se construye sobre un stack menos dominado.

## Consecuencias

- Menor atractivo narrativo frente a un CTO acostumbrado a Node. Se mitiga con la calidad de las
  pruebas de aislamiento y la documentación de arquitectura.
- La contratación futura apunta a perfiles PHP/Vue.
- Revisar esta decisión solo si se incorpora un equipo con perfil mayoritariamente TypeScript.
