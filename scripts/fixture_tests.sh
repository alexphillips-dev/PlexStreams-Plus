#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v node >/dev/null 2>&1; then
  echo "Skipping fixture tests: node is not installed."
  exit 0
fi

node "${ROOT_DIR}/tests/js/plex_render_fixtures.test.cjs"
echo "Fixture test suite passed."
