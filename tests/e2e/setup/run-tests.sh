#!/usr/bin/env bash
# Run the Playwright tests inside the official Playwright image, against the
# stack started by start-stack.sh. Nothing needs to be installed on the host
# except Docker and the project's node_modules (`npm ci`).
#
#   tests/e2e/setup/run-tests.sh                       # whole suite
#   tests/e2e/setup/run-tests.sh tests/e2e/auth.spec.js
#
# Arguments are passed to `playwright test`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
STATE_FILE="$ROOT/tests/e2e/.stack/stack.env"

if [ ! -f "$STATE_FILE" ]; then
    echo "No running E2E stack found. Start it first: tests/e2e/setup/start-stack.sh" >&2
    exit 1
fi
if [ ! -f "$ROOT/node_modules/@playwright/test/cli.js" ]; then
    echo "node_modules is missing @playwright/test. Run: npm ci" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1090
. "$STATE_FILE"
set +a

# Inside the runner the app is reached on its container port, 8080. APP_URL
# must be the same address (product photo URLs are built from it), so the
# stack has to publish the app on host port 8080 as well.
if [ "$E2E_APP_PORT" != "8080" ]; then
    echo "The Docker runner needs the stack started with E2E_APP_PORT=8080 (it is $E2E_APP_PORT)." >&2
    echo "With another port, run Playwright on the host instead: npx playwright install chromium && npm run test:e2e" >&2
    exit 1
fi

# No pseudo-terminal when there is none to attach to (for example in CI).
tty_flag=()
[ -t 0 ] && [ -t 1 ] || tty_flag=(-T)

docker compose -p "$E2E_PROJECT" -f "$ROOT/tests/e2e/compose.yaml" --env-file "$STATE_FILE" \
    run --rm --no-deps ${tty_flag[@]+"${tty_flag[@]}"} playwright node node_modules/@playwright/test/cli.js test "$@"
