#!/usr/bin/env bash
# Stop the end-to-end test stack and delete its containers, network, volumes
# and generated secrets.
#
#   tests/e2e/setup/stop-stack.sh
#
# Uses the same E2E_PROJECT as start-stack.sh (default: lvcs-e2e).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
STATE_DIR="$ROOT/tests/e2e/.stack"
STATE_FILE="$STATE_DIR/stack.env"

if [ -f "$STATE_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$STATE_FILE"
    set +a
fi

E2E_PROJECT="${E2E_PROJECT:-lvcs-e2e}"

# Compose needs these to be set to parse the file; their values do not matter
# when removing the stack.
export E2E_DB_PASSWORD="${E2E_DB_PASSWORD:-unused}" E2E_DB_ROOT_PASSWORD="${E2E_DB_ROOT_PASSWORD:-unused}" \
    E2E_APP_KEY="${E2E_APP_KEY:-unused}"

docker compose -p "$E2E_PROJECT" -f "$ROOT/tests/e2e/compose.yaml" --profile runner \
    down --volumes --remove-orphans

rm -rf "$STATE_DIR"
echo "E2E stack $E2E_PROJECT removed."
