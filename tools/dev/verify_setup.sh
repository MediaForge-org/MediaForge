#!/usr/bin/env bash
# Regression/smoke check for the `make setup` contract.
#
# Proves, against the real running dev stack, that a fresh clone actually
# ends up in a working state -- not just that `make setup` exited 0. Run
# automatically at the end of `make setup`, or any time via `make setup-check`.
#
# Does NOT start or stop anything itself; run after `make setup` / `make up`.
set -euo pipefail
cd "$(dirname "$0")/../.."

COMPOSE="docker compose -f deploy/dev/docker-compose.yml"
failed=0

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; failed=1; }

# 1. Composer dependencies actually landed on the host bind mount (not just
#    inside a throwaway container), so app/worker/horizon/scheduler can see them.
if [ -f vendor/autoload.php ]; then
    pass "host vendor/autoload.php exists (composer install reached the bind mount)"
else
    fail "host vendor/autoload.php is missing"
fi

# 2. Same for the built frontend assets -- required for @vite() to render at all.
if [ -f public/build/manifest.json ]; then
    pass "host public/build/manifest.json exists (npm run build reached the bind mount)"
else
    fail "host public/build/manifest.json is missing"
fi

# 3. The production runtime image must still NOT contain a composer binary --
#    proves the fix did not "solve" this by fattening the shipped image.
if $COMPOSE exec -T app sh -lc 'command -v composer' >/dev/null 2>&1; then
    fail "composer is present in the runtime image (it must only exist in the dedicated composer service)"
else
    pass "runtime image still has no composer binary"
fi

# 4. The sessions table (created by the existing users-table migration) exists,
#    i.e. `migrate --force` actually ran against a real schema.
sessions_present=$($COMPOSE exec -T postgres psql -U mediaforge -d mediaforge -tAc \
    "SELECT to_regclass('public.sessions') IS NOT NULL;" 2>/dev/null | tr -d '[:space:]')
if [ "$sessions_present" = "t" ]; then
    pass "sessions table exists in the dev database"
else
    fail "sessions table is missing from the dev database (got: '${sessions_present:-<empty>}')"
fi

# 5. The app answers from inside its own container on the port nginx actually
#    listens on (8080; the host-side 8100 is just this port published).
if $COMPOSE exec -T app sh -lc 'curl -fsS -o /dev/null http://localhost:8080/up'; then
    pass "app responds on /up"
else
    fail "app does not respond on /up"
fi

# 6. A real page that goes through the Vite manifest AND touches the session
#    (login) renders 200 -- the two failure modes a missing-manifest or a
#    not-yet-migrated database would each produce on their own.
login_status=$($COMPOSE exec -T app sh -lc \
    'curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/login' 2>/dev/null || echo "000")
if [ "$login_status" = "200" ]; then
    pass "GET /login renders 200"
else
    fail "GET /login returned HTTP $login_status (expected 200)"
fi

echo
if [ "$failed" -ne 0 ]; then
    echo "Setup contract check FAILED."
    exit 1
fi
echo "Setup contract check passed."
