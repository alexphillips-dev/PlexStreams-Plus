#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

PLUS_MANIFEST="plexstreamsplus.plg"
LEGACY_MANIFEST="plexstreams.plg"
COMMON_FILE="src/plexstreamsplus/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php"
SOURCE_DIR="src/plexstreamsplus"
ARCHIVE_DIR="archive"
PLUS_NAME="plexstreamsplus"
LEGACY_NAME="plexstreams"
ARCH_SUFFIX="-x86_64-1.txz"
DEFAULT_CHANGELOG_NOTE="Automated release pipeline update."

release_date="${RELEASE_DATE:-}"
release_revision="${RELEASE_REVISION:-}"
changelog_note="${RELEASE_CHANGELOG:-${DEFAULT_CHANGELOG_NOTE}}"

if [[ $# -ge 1 && -n "${1:-}" ]]; then
  release_date="$1"
fi
if [[ $# -ge 2 && -n "${2:-}" ]]; then
  release_revision="$2"
fi
if [[ $# -ge 3 && -n "${3:-}" ]]; then
  changelog_note="$3"
fi

for required_file in "${PLUS_MANIFEST}" "${LEGACY_MANIFEST}" "${COMMON_FILE}"; do
  if [[ ! -f "${required_file}" ]]; then
    echo "Missing required file: ${required_file}" >&2
    exit 1
  fi
done

if [[ ! -d "${SOURCE_DIR}" ]]; then
  echo "Missing source directory: ${SOURCE_DIR}" >&2
  exit 1
fi

mkdir -p "${ARCHIVE_DIR}"

changelog_note="$(echo "${changelog_note}" | tr '\r\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//')"
if [[ -z "${changelog_note}" ]]; then
  changelog_note="${DEFAULT_CHANGELOG_NOTE}"
fi

if [[ -z "${release_date}" ]]; then
  release_date="$(TZ=America/New_York date +%Y.%m.%d)"
fi

if [[ ! "${release_date}" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}$ ]]; then
  echo "Invalid release date '${release_date}'. Use YYYY.MM.DD." >&2
  exit 1
fi

current_version="$(sed -n -E 's/.*<!ENTITY version[[:space:]]+"([^"]+)".*/\1/p' "${PLUS_MANIFEST}" | head -n1)"
if [[ ! "${current_version}" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+$ ]]; then
  echo "Could not parse current manifest version from ${PLUS_MANIFEST}." >&2
  exit 1
fi

version=""
if [[ -n "${release_revision}" ]]; then
  if [[ ! "${release_revision}" =~ ^[1-9][0-9]*$ ]]; then
    echo "Invalid revision '${release_revision}'. Revision must be a positive integer." >&2
    exit 1
  fi
  version="${release_date}.${release_revision}"
  if [[ -f "${ARCHIVE_DIR}/${PLUS_NAME}-${version}${ARCH_SUFFIX}" ]]; then
    echo "Archive already exists for ${version}. Pick a new revision." >&2
    exit 1
  fi
else
  next_revision=1
  date_regex="${release_date//./\\.}"
  if [[ "${current_version}" =~ ^${date_regex}\.([0-9]+)$ ]]; then
    next_revision="${BASH_REMATCH[1]}"
  fi

  version="${release_date}.${next_revision}"
  if [[ "${current_version}" == "${version}" && -f "${ARCHIVE_DIR}/${PLUS_NAME}-${version}${ARCH_SUFFIX}" ]]; then
    next_revision=$((next_revision + 1))
  fi

  while [[ -f "${ARCHIVE_DIR}/${PLUS_NAME}-${release_date}.${next_revision}${ARCH_SUFFIX}" ]]; do
    next_revision=$((next_revision + 1))
  done
  version="${release_date}.${next_revision}"
fi

plus_archive="${ARCHIVE_DIR}/${PLUS_NAME}-${version}${ARCH_SUFFIX}"
legacy_archive="${ARCHIVE_DIR}/${LEGACY_NAME}-${version}${ARCH_SUFFIX}"
plus_md5_file="${plus_archive%.txz}.md5"
legacy_md5_file="${legacy_archive%.txz}.md5"

tar -C "${SOURCE_DIR}" -cJf "${plus_archive}" usr
cp -f "${plus_archive}" "${legacy_archive}"

plus_md5="$(md5sum "${plus_archive}" | awk '{print $1}')"
legacy_md5="$(md5sum "${legacy_archive}" | awk '{print $1}')"
printf "%s  %s\n" "${plus_md5}" "$(basename "${plus_archive}")" > "${plus_md5_file}"
printf "%s  %s\n" "${legacy_md5}" "$(basename "${legacy_archive}")" > "${legacy_md5_file}"
md5="${plus_md5}"

sed -E -i "s/(define\('PLUGIN_VERSION', ')[^']+('\);)/\1${version}\2/" "${COMMON_FILE}"

update_manifest() {
  local manifest="$1"

  sed -E -i "s/(<!ENTITY version[[:space:]]+\")[^\"]+(\">)/\1${version}\2/" "${manifest}"
  sed -E -i "s/(<!ENTITY md5[[:space:]]+\")[^\"]+(\">)/\1${md5}\2/" "${manifest}"

  if ! grep -q "^###${version}$" "${manifest}"; then
    awk -v release_version="${version}" -v note="${changelog_note}" '
      BEGIN { inserted = 0 }
      {
        print
        if (!inserted && $0 ~ /^[[:space:]]*##&name;[[:space:]]*$/) {
          print "###" release_version
          print "- " note
          print ""
          inserted = 1
        }
      }
      END {
        if (!inserted) {
          exit 1
        }
      }
    ' "${manifest}" > "${manifest}.tmp"
    mv "${manifest}.tmp" "${manifest}"
  fi
}

update_manifest "${PLUS_MANIFEST}"
update_manifest "${LEGACY_MANIFEST}"

echo "Release version: ${version}"
echo "Package md5: ${md5}"

if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
  echo "version=${version}" >> "${GITHUB_OUTPUT}"
fi

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  {
    echo "## PlexStreams Plus Release"
    echo "- Version: \`${version}\`"
    echo "- MD5: \`${md5}\`"
    echo "- Package: \`$(basename "${plus_archive}")\`"
  } >> "${GITHUB_STEP_SUMMARY}"
fi
