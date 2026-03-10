#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=scripts/lib.sh
source "${ROOT_DIR}/scripts/lib.sh"

cd "${ROOT_DIR}"

bash scripts/doctor.sh
bash scripts/release.sh "$@"
bash scripts/release_guard.sh
bash scripts/install_smoke.sh

echo "Release prepare checks passed."
echo "Version: $(psplus::read_plg_version "${ROOT_DIR}/plexstreamsplus.plg")"

