#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

PLUS_MANIFEST="plexstreamsplus.plg"
LEGACY_MANIFEST="plexstreams.plg"
PLUS_CA_TEMPLATE="plexstreamsplus.xml"
LEGACY_CA_TEMPLATE="plexstreams.xml"
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

for required_file in "${PLUS_MANIFEST}" "${LEGACY_MANIFEST}" "${PLUS_CA_TEMPLATE}" "${LEGACY_CA_TEMPLATE}" "${COMMON_FILE}"; do
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

today_ny="$(TZ=America/New_York date +%Y.%m.%d)"
if [[ "${release_date}" != "${today_ny}" && "${ALLOW_RELEASE_DATE_OVERRIDE:-0}" != "1" ]]; then
  echo "Release date '${release_date}' does not match today's date '${today_ny}' (America/New_York)." >&2
  echo "Set ALLOW_RELEASE_DATE_OVERRIDE=1 to intentionally publish a non-today date." >&2
  exit 1
fi

current_version="$(sed -n -E 's/.*<!ENTITY version[[:space:]]+"([^"]+)".*/\1/p' "${PLUS_MANIFEST}" | head -n1)"
if [[ ! "${current_version}" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}(\.[1-9][0-9]*)?$ ]]; then
  echo "Could not parse current manifest version from ${PLUS_MANIFEST}." >&2
  exit 1
fi

is_stable_version() {
  local input="${1:-}"
  [[ "${input}" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}(\.[1-9][0-9]*)?$ ]]
}

normalize_stable_version_for_unraid() {
  local input="${1:-}"
  if [[ "${input}" =~ ^([0-9]{4}\.[0-9]{2}\.[0-9]{2})$ ]]; then
    echo "${BASH_REMATCH[1]}.1"
    return
  fi
  if [[ "${input}" =~ ^([0-9]{4}\.[0-9]{2}\.[0-9]{2})\.([1-9][0-9]*)$ ]]; then
    local base="${BASH_REMATCH[1]}"
    local patch=$((10#${BASH_REMATCH[2]}))
    printf '%s.%d\n' "${base}" "${patch}"
    return
  fi
  echo "${input}"
}

next_patch_version() {
  local input="${1:-}"
  if [[ "${input}" =~ ^([0-9]{4}\.[0-9]{2}\.[0-9]{2})\.([1-9][0-9]*)$ ]]; then
    local base="${BASH_REMATCH[1]}"
    local patch=$((10#${BASH_REMATCH[2]} + 1))
    printf '%s.%d\n' "${base}" "${patch}"
    return
  fi
  echo "${input}"
}

highest_archive_version_for_date() {
  local target_date="${1:-}"
  local archive
  local versions=()

  shopt -s nullglob
  for archive in "${ARCHIVE_DIR}/${PLUS_NAME}-${target_date}."*"${ARCH_SUFFIX}"; do
    local name="${archive##*/}"
    local version_part="${name#${PLUS_NAME}-}"
    version_part="${version_part%${ARCH_SUFFIX}}"
    if is_stable_version "${version_part}"; then
      versions+=("$(normalize_stable_version_for_unraid "${version_part}")")
    fi
  done
  shopt -u nullglob

  if [[ ${#versions[@]} -eq 0 ]]; then
    echo ""
    return
  fi

  printf '%s\n' "${versions[@]}" | sort -V | tail -n1
}

version=""
if [[ -n "${release_revision}" ]]; then
  if [[ ! "${release_revision}" =~ ^[1-9][0-9]*$ ]]; then
    echo "Invalid revision '${release_revision}'. Revision must be a positive integer." >&2
    exit 1
  fi
  version="$(printf '%s.%d' "${release_date}" "$((10#${release_revision}))")"
  if [[ -f "${ARCHIVE_DIR}/${PLUS_NAME}-${version}${ARCH_SUFFIX}" ]]; then
    echo "Archive already exists for ${version}. Pick a new revision." >&2
    exit 1
  fi
else
  highest_version="$(highest_archive_version_for_date "${release_date}")"

  if is_stable_version "${current_version}"; then
    normalized_current="$(normalize_stable_version_for_unraid "${current_version}")"
    if [[ "${normalized_current}" =~ ^${release_date//./\\.}\.[0-9]+$ ]]; then
      if [[ -z "${highest_version}" ]]; then
        highest_version="${normalized_current}"
      else
        max_version="$(printf '%s\n%s\n' "${highest_version}" "${normalized_current}" | sort -V | tail -n1)"
        highest_version="${max_version}"
      fi
    fi
  fi

  if [[ -z "${highest_version}" ]]; then
    version="${release_date}.1"
  else
    version="$(next_patch_version "${highest_version}")"
  fi

  while [[ -f "${ARCHIVE_DIR}/${PLUS_NAME}-${version}${ARCH_SUFFIX}" ]]; do
    version="$(next_patch_version "${version}")"
  done
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

xml_date="${release_date//./-}"
sed -E -i "s|(<Date>)[^<]+(</Date>)|\1${xml_date}\2|" "${PLUS_CA_TEMPLATE}"
sed -E -i "s|(<Date>)[^<]+(</Date>)|\1${xml_date}\2|" "${LEGACY_CA_TEMPLATE}"

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
