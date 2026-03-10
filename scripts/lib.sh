#!/usr/bin/env bash

psplus::fail() {
  echo "ERROR: $*" >&2
  exit 1
}

psplus::require_commands() {
  local missing=()
  local cmd
  for cmd in "$@"; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
      missing+=("${cmd}")
    fi
  done

  if [[ ${#missing[@]} -gt 0 ]]; then
    psplus::fail "Missing required commands: ${missing[*]}"
  fi
}

psplus::parse_plg_entity() {
  local entity_name="${1:-}"
  local plg_file="${2:-}"
  sed -n -E "s/.*<!ENTITY[[:space:]]+${entity_name}[[:space:]]+\"([^\"]+)\".*/\\1/p" "${plg_file}" | head -n 1 || true
}

psplus::read_plg_version() {
  local plg_file="${1:-}"
  local version=""
  version="$(psplus::parse_plg_entity version "${plg_file}")"
  if [[ -z "${version}" ]]; then
    psplus::fail "Could not parse version from ${plg_file}"
  fi
  echo "${version}"
}

psplus::read_plg_md5() {
  local plg_file="${1:-}"
  local md5_value=""
  md5_value="$(psplus::parse_plg_entity md5 "${plg_file}")"
  if [[ -z "${md5_value}" ]]; then
    psplus::fail "Could not parse md5 from ${plg_file}"
  fi
  echo "${md5_value}"
}

psplus::archive_dir() {
  local root_dir="${1:-}"
  echo "${PSPLUS_ARCHIVE_DIR:-${root_dir}/archive}"
}

psplus::archive_file() {
  local root_dir="${1:-}"
  local package_name="${2:-}"
  local version="${3:-}"
  local archive_dir=""
  archive_dir="$(psplus::archive_dir "${root_dir}")"
  echo "${archive_dir}/${package_name}-${version}-x86_64-1.txz"
}

psplus::archive_md5_file() {
  local root_dir="${1:-}"
  local package_name="${2:-}"
  local version="${3:-}"
  local archive_dir=""
  archive_dir="$(psplus::archive_dir "${root_dir}")"
  echo "${archive_dir}/${package_name}-${version}-x86_64-1.md5"
}

