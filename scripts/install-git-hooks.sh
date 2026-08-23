#!/usr/bin/env bash
# Activa los hooks versionados del repositorio. Se corre una vez por clon.
set -euo pipefail

RAIZ="$(git rev-parse --show-toplevel)"
git -C "$RAIZ" config core.hooksPath scripts/git-hooks

echo "✓ Hooks activos: $(git -C "$RAIZ" config core.hooksPath)"
echo "  pre-commit → pruebas del grupo tenant-isolation"
