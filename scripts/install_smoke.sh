#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUS_PLG="${ROOT_DIR}/plexstreamsplus.plg"
LEGACY_PLG="${ROOT_DIR}/plexstreams.plg"
# shellcheck source=scripts/lib.sh
source "${ROOT_DIR}/scripts/lib.sh"

psplus::require_commands tar sed grep find php

if [[ ! -f "${PLUS_PLG}" || ! -f "${LEGACY_PLG}" ]]; then
  psplus::fail "Missing plugin manifest(s)."
fi

VERSION_PLUS="$(psplus::read_plg_version "${PLUS_PLG}")"
VERSION_LEGACY="$(psplus::read_plg_version "${LEGACY_PLG}")"
if [[ "${VERSION_PLUS}" != "${VERSION_LEGACY}" ]]; then
  psplus::fail "Manifest version mismatch: plus=${VERSION_PLUS}, legacy=${VERSION_LEGACY}"
fi

PLUS_ARCHIVE="$(psplus::archive_file "${ROOT_DIR}" "plexstreamsplus" "${VERSION_PLUS}")"
LEGACY_ARCHIVE="$(psplus::archive_file "${ROOT_DIR}" "plexstreams" "${VERSION_PLUS}")"
if [[ ! -f "${PLUS_ARCHIVE}" || ! -f "${LEGACY_ARCHIVE}" ]]; then
  psplus::fail "Missing release archive(s) for ${VERSION_PLUS}"
fi

ICON_NAME="$(sed -n -E 's/.*icon="([^"]+)".*/\1/p' "${PLUS_PLG}" | head -n 1 || true)"
if [[ -z "${ICON_NAME}" ]]; then
  psplus::fail "Could not parse icon name from plexstreamsplus.plg"
fi

ARCHIVE_LIST="$(tar -tf "${PLUS_ARCHIVE}")"
ARCHIVE_LIST_NORMALIZED="$(printf '%s\n' "${ARCHIVE_LIST}" | sed 's#^\./##')"
if grep -q '^./local/' <<< "${ARCHIVE_LIST}"; then
  psplus::fail "Archive contains invalid top-level './local/' paths."
fi

REQUIRED_ARCHIVE_ENTRIES=(
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlus.page"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlusSettings.page"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlusNav.page"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlusTools.page"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlus_dashboard.page"
  "usr/local/emhttp/plugins/plexstreamsplus/Legacy/Dashboard.page"
  "usr/local/emhttp/plugins/plexstreamsplus/Legacy/Settings.page"
  "usr/local/emhttp/plugins/plexstreamsplus/includes/common.php"
  "usr/local/emhttp/plugins/plexstreamsplus/includes/config.php"
  "usr/local/emhttp/plugins/plexstreamsplus/README.md"
  "usr/local/emhttp/plugins/plexstreamsplus/${ICON_NAME}"
  "usr/local/emhttp/plugins/plexstreams/${ICON_NAME}"
  "usr/local/emhttp/plugins/plexstreams/README.md"
)

for required_entry in "${REQUIRED_ARCHIVE_ENTRIES[@]}"; do
  if ! grep -Fxq "${required_entry}" <<< "${ARCHIVE_LIST_NORMALIZED}"; then
    psplus::fail "Missing required archive entry: ${required_entry}"
  fi
done

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT
tar -xf "${PLUS_ARCHIVE}" -C "${TMP_DIR}"

PLUGIN_PLUS_DIR="${TMP_DIR}/usr/local/emhttp/plugins/plexstreamsplus"
PLUGIN_LEGACY_DIR="${TMP_DIR}/usr/local/emhttp/plugins/plexstreams"
if [[ ! -d "${PLUGIN_PLUS_DIR}" || ! -d "${PLUGIN_LEGACY_DIR}" ]]; then
  psplus::fail "Extracted plugin directories are missing."
fi

while IFS= read -r -d '' php_file; do
  php -l "${php_file}" >/dev/null
done < <(find "${PLUGIN_PLUS_DIR}" -type f -name "*.php" -print0)

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' js_file; do
    node --check "${js_file}" >/dev/null
  done < <(find "${PLUGIN_PLUS_DIR}" -type f -name "*.js" -print0)
fi

echo "Install smoke checks passed:"
echo "  version: ${VERSION_PLUS}"
echo "  archive: ${PLUS_ARCHIVE##*/}"

