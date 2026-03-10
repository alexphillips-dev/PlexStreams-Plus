#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUS_PLG="${ROOT_DIR}/plexstreamsplus.plg"
LEGACY_PLG="${ROOT_DIR}/plexstreams.plg"
PLUS_CA_XML="${ROOT_DIR}/plexstreamsplus.xml"
LEGACY_CA_XML="${ROOT_DIR}/plexstreams.xml"
COMMON_FILE="${ROOT_DIR}/src/plexstreamsplus/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php"
SOURCE_DIR="${ROOT_DIR}/src/plexstreamsplus"
# shellcheck source=scripts/lib.sh
source "${ROOT_DIR}/scripts/lib.sh"

psplus::require_commands bash tar sed awk grep find md5sum date php

for required_file in "${PLUS_PLG}" "${LEGACY_PLG}" "${PLUS_CA_XML}" "${LEGACY_CA_XML}" "${COMMON_FILE}"; do
  if [[ ! -f "${required_file}" ]]; then
    psplus::fail "Missing required file: ${required_file}"
  fi
done

if [[ ! -d "${SOURCE_DIR}" ]]; then
  psplus::fail "Missing source directory: ${SOURCE_DIR}"
fi

validate_xml() {
  local file_path="$1"
  if command -v xmllint >/dev/null 2>&1; then
    xmllint --noout "${file_path}" >/dev/null 2>&1 || psplus::fail "Invalid XML file: ${file_path}"
    return
  fi

  php -r '
    libxml_use_internal_errors(true);
    $xml = @file_get_contents($argv[1]);
    if ($xml === false) {
      fwrite(STDERR, "Cannot read XML file\n");
      exit(1);
    }
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      fwrite(STDERR, "Invalid XML\n");
      exit(1);
    }
  ' "${file_path}" >/dev/null 2>&1 || psplus::fail "Invalid XML file: ${file_path}"
}

validate_xml "${PLUS_PLG}"
validate_xml "${LEGACY_PLG}"
validate_xml "${PLUS_CA_XML}"
validate_xml "${LEGACY_CA_XML}"

VERSION_PLUS="$(psplus::read_plg_version "${PLUS_PLG}")"
VERSION_LEGACY="$(psplus::read_plg_version "${LEGACY_PLG}")"
if [[ "${VERSION_PLUS}" != "${VERSION_LEGACY}" ]]; then
  psplus::fail "Manifest version mismatch: plus=${VERSION_PLUS}, legacy=${VERSION_LEGACY}"
fi

if [[ ! "${VERSION_PLUS}" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]{2,}$ ]]; then
  psplus::fail "Version has unexpected format: ${VERSION_PLUS}"
fi

VERSION_DATE="${VERSION_PLUS%.*}"
TODAY_DATE="$(date +%Y.%m.%d)"
if [[ "${VERSION_DATE}" > "${TODAY_DATE}" ]]; then
  psplus::fail "Version date (${VERSION_DATE}) is in the future (today: ${TODAY_DATE})."
fi

EXPECTED_XML_DATE="${VERSION_DATE//./-}"
PLUS_XML_DATE="$(sed -n 's|.*<Date>\([^<]*\)</Date>.*|\1|p' "${PLUS_CA_XML}" | head -n 1 || true)"
LEGACY_XML_DATE="$(sed -n 's|.*<Date>\([^<]*\)</Date>.*|\1|p' "${LEGACY_CA_XML}" | head -n 1 || true)"
if [[ "${PLUS_XML_DATE}" != "${EXPECTED_XML_DATE}" ]]; then
  psplus::fail "CA XML date mismatch for plexstreamsplus.xml (expected ${EXPECTED_XML_DATE}, found ${PLUS_XML_DATE:-missing})"
fi
if [[ "${LEGACY_XML_DATE}" != "${EXPECTED_XML_DATE}" ]]; then
  psplus::fail "CA XML date mismatch for plexstreams.xml (expected ${EXPECTED_XML_DATE}, found ${LEGACY_XML_DATE:-missing})"
fi

PLUGIN_VERSION_IN_COMMON="$(sed -n -E "s/.*define\('PLUGIN_VERSION', '([^']+)'\).*/\1/p" "${COMMON_FILE}" | head -n 1 || true)"
if [[ "${PLUGIN_VERSION_IN_COMMON}" != "${VERSION_PLUS}" ]]; then
  psplus::fail "PLUGIN_VERSION mismatch in common.php (expected ${VERSION_PLUS}, found ${PLUGIN_VERSION_IN_COMMON:-missing})"
fi

if ! grep -q "^###${VERSION_PLUS}$" "${PLUS_PLG}"; then
  psplus::fail "Missing CHANGES entry for ${VERSION_PLUS} in plexstreamsplus.plg"
fi
if ! grep -q "^###${VERSION_PLUS}$" "${LEGACY_PLG}"; then
  psplus::fail "Missing CHANGES entry for ${VERSION_PLUS} in plexstreams.plg"
fi

PLUS_ENTITY_MD5="$(psplus::read_plg_md5 "${PLUS_PLG}")"
LEGACY_ENTITY_MD5="$(psplus::read_plg_md5 "${LEGACY_PLG}")"
if [[ "${PLUS_ENTITY_MD5}" != "${LEGACY_ENTITY_MD5}" ]]; then
  psplus::fail "Manifest md5 mismatch: plus=${PLUS_ENTITY_MD5}, legacy=${LEGACY_ENTITY_MD5}"
fi

PLUS_ARCHIVE="$(psplus::archive_file "${ROOT_DIR}" "plexstreamsplus" "${VERSION_PLUS}")"
LEGACY_ARCHIVE="$(psplus::archive_file "${ROOT_DIR}" "plexstreams" "${VERSION_PLUS}")"
PLUS_MD5_FILE="$(psplus::archive_md5_file "${ROOT_DIR}" "plexstreamsplus" "${VERSION_PLUS}")"
LEGACY_MD5_FILE="$(psplus::archive_md5_file "${ROOT_DIR}" "plexstreams" "${VERSION_PLUS}")"

for required_archive_file in "${PLUS_ARCHIVE}" "${LEGACY_ARCHIVE}" "${PLUS_MD5_FILE}" "${LEGACY_MD5_FILE}"; do
  if [[ ! -f "${required_archive_file}" ]]; then
    psplus::fail "Missing release artifact: ${required_archive_file}"
  fi
done

PLUS_ARCHIVE_MD5="$(md5sum "${PLUS_ARCHIVE}" | awk '{print $1}')"
LEGACY_ARCHIVE_MD5="$(md5sum "${LEGACY_ARCHIVE}" | awk '{print $1}')"
if [[ "${PLUS_ARCHIVE_MD5}" != "${LEGACY_ARCHIVE_MD5}" ]]; then
  psplus::fail "Archive md5 mismatch: plus=${PLUS_ARCHIVE_MD5}, legacy=${LEGACY_ARCHIVE_MD5}"
fi
if [[ "${PLUS_ENTITY_MD5}" != "${PLUS_ARCHIVE_MD5}" ]]; then
  psplus::fail "Manifest md5 does not match archive md5: manifest=${PLUS_ENTITY_MD5}, archive=${PLUS_ARCHIVE_MD5}"
fi

PLUS_MD5_FILE_VALUE="$(awk '{print $1}' "${PLUS_MD5_FILE}" | head -n 1 || true)"
LEGACY_MD5_FILE_VALUE="$(awk '{print $1}' "${LEGACY_MD5_FILE}" | head -n 1 || true)"
if [[ "${PLUS_MD5_FILE_VALUE}" != "${PLUS_ARCHIVE_MD5}" ]]; then
  psplus::fail "plus .md5 file mismatch: expected ${PLUS_ARCHIVE_MD5}, found ${PLUS_MD5_FILE_VALUE:-missing}"
fi
if [[ "${LEGACY_MD5_FILE_VALUE}" != "${LEGACY_ARCHIVE_MD5}" ]]; then
  psplus::fail "legacy .md5 file mismatch: expected ${LEGACY_ARCHIVE_MD5}, found ${LEGACY_MD5_FILE_VALUE:-missing}"
fi

ARCHIVE_LIST="$(tar -tf "${PLUS_ARCHIVE}")"
if grep -q '^./local/' <<< "${ARCHIVE_LIST}"; then
  psplus::fail "Archive contains invalid top-level './local/' paths. Must install under './usr/local/'."
fi

ARCHIVE_LIST_NORMALIZED="$(printf '%s\n' "${ARCHIVE_LIST}" | sed 's#^\./##')"
REQUIRED_ARCHIVE_ENTRIES=(
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlus.page"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreamsPlusSettings.page"
  "usr/local/emhttp/plugins/plexstreamsplus/NewDashboard.page"
  "usr/local/emhttp/plugins/plexstreamsplus/includes/common.php"
  "usr/local/emhttp/plugins/plexstreamsplus/PlexStreams-icon.png"
  "usr/local/emhttp/plugins/plexstreamsplus/README.md"
  "usr/local/emhttp/plugins/plexstreams/PlexStreams-icon.png"
  "usr/local/emhttp/plugins/plexstreams/README.md"
)

for required_entry in "${REQUIRED_ARCHIVE_ENTRIES[@]}"; do
  if ! grep -Fxq "${required_entry}" <<< "${ARCHIVE_LIST_NORMALIZED}"; then
    psplus::fail "Missing required archive entry: ${required_entry}"
  fi
done

echo "Release guard checks passed:"
echo "  version: ${VERSION_PLUS}"
echo "  md5: ${PLUS_ARCHIVE_MD5}"
echo "  archive: ${PLUS_ARCHIVE##*/}"

