#!/usr/bin/env bash
# Bloquea el commit si las pruebas de aislamiento multi-tenant fallan.
# La regla que depende de la disciplina humana no es una regla.
#
# Principio de diseño: **falla cerrado**. Si el hook no puede ejecutar las pruebas
# —falta vendor, la base no responde, el esquema de pruebas no está migrado— bloquea
# igual. Un hook que deja pasar el commit cuando no pudo comprobar nada es peor que no
# tener hook: da confianza sin respaldarla.
set -euo pipefail

RAIZ="$(git rev-parse --show-toplevel)"
APP="$RAIZ/apps/platform"

bloquear() {
  echo ""
  echo "✖ Commit bloqueado."
  echo "  $1"
  echo ""
  echo "  Saltarse esto con --no-verify es una decisión que se registra en STATE.md."
  exit 1
}

[ -d "$APP" ] || bloquear "No encuentro la aplicación en apps/platform."

cd "$APP"

[ -x ./vendor/bin/pest ] || bloquear \
  "No hay ejecutable de Pest. Corre: (cd apps/platform && composer install)"

echo "→ Ejecutando pruebas de aislamiento multi-tenant…"

if ! ./vendor/bin/pest --group=tenant-isolation; then
  echo ""
  echo "✖ P0 SECURITY: fallaron las pruebas de aislamiento multi-tenant."
  echo ""
  echo "  Si el fallo es de entorno y no de código, la base de pruebas se prepara así:"
  echo "    cd apps/platform"
  echo "    DB_DATABASE=platform_test php artisan migrate:fresh --database=pgsql_owner --force"
  echo ""
  bloquear "Registra el hallazgo en STATE.md antes de continuar."
fi

echo "✓ Aislamiento multi-tenant verificado."
