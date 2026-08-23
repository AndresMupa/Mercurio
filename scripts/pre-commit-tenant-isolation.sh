#!/usr/bin/env bash
# Bloquea el commit si las pruebas de aislamiento multi-tenant fallan.
# La regla que depende de la disciplina humana no es una regla.
set -euo pipefail

echo "→ Ejecutando pruebas de aislamiento multi-tenant…"

if ! ./vendor/bin/pest --group=tenant-isolation; then
  echo ""
  echo "✖ P0 SECURITY: fallaron las pruebas de aislamiento multi-tenant."
  echo "  El commit está bloqueado. Registra el hallazgo en STATE.md antes de continuar."
  exit 1
fi

echo "✓ Aislamiento multi-tenant verificado."
