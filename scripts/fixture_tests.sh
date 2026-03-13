#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v node >/dev/null 2>&1; then
  echo "Skipping fixture tests: node is not installed."
else
  node "${ROOT_DIR}/tests/js/plex_render_fixtures.test.cjs"
  echo "Fixture test suite passed (js)."
fi

if ! command -v php >/dev/null 2>&1; then
  echo "Skipping PHP helper tests: php is not installed."
else
  php "${ROOT_DIR}/tests/php/common_privacy_helpers.test.php"
  echo "Fixture test suite passed (php)."
fi
