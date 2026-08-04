#!/usr/bin/env bash
# Runs the e2e test suite against the app built from the `production` stage
# of ./Dockerfile (FrankenPHP + Caddy + opcache JIT + production.ini).
#
# Pre-requisites: docker (>= 23) with `docker compose`. The runner itself
# runs on the host; only the app container is Docker.
#
# Networking:
# - The app container reaches the host (feed + SMTP servers) via
#   `host.docker.internal` (extra_hosts: host-gateway in compose-e2e.yaml).
#   Tests must resolve `host.docker.internal` on the host, so we add it to
#   /etc/hosts if missing (Linux devcontainer only; no-op elsewhere).
# - Both the host and the container open the same SQLite file via a
#   single-file bind mount (./data/test-e2e.db).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
cd "$APP_DIR"

FEED_PID=""
SMTP_PID=""

cleanup() {
    set +e
    echo ""
    echo "=== Cleaning up ==="
    if [ -n "$SMTP_PID" ]; then kill "$SMTP_PID" 2>/dev/null || true; fi
    if [ -n "$FEED_PID" ]; then kill "$FEED_PID" 2>/dev/null || true; fi
    if command -v docker >/dev/null 2>&1; then
        docker compose -f compose-e2e.yaml down --volumes >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

# 1. Sanity: docker available
if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: docker is not available on PATH" >&2
    exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
    echo "ERROR: 'docker compose' is not available" >&2
    exit 1
fi

# 2. Initialize fresh test database
echo "=== 1. Initializing test database ==="
export NEWSLETTER_DB_PATH="$APP_DIR/data/test-e2e.db"
rm -f "$NEWSLETTER_DB_PATH"
php -r '
    $dbPath = getenv("NEWSLETTER_DB_PATH");
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (["00-setup.sql", "01-feeds.sql", "02-subscriptions.sql", "03-rate-limiting.sql", "99-optimizations.sql"] as $file) {
        $pdo->exec(file_get_contents("migrations/" . $file));
    }
    echo "   ✓ Database initialized\n";
'

# 3. Start feed server (valid.xml + invalid.txt on port 9995)
echo "=== 2. Starting feed server on :9995 ==="
FEED_DIR="/tmp/feedtest"
rm -rf "$FEED_DIR"
mkdir -p "$FEED_DIR"

cat > "$FEED_DIR/valid.xml" << 'XMLEOF'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
<title>Test Blog</title>
<link>https://example.com</link>
<item>
<title>First Post</title>
<link>https://example.com/post1</link>
</item>
</channel>
</rss>
XMLEOF

echo "not xml" > "$FEED_DIR/invalid.txt"

php -S 0.0.0.0:9995 -t "$FEED_DIR" > /tmp/feed-server-prod.log 2>&1 &
FEED_PID=$!

for i in {1..20}; do
    if curl -sf http://127.0.0.1:9995/valid.xml > /dev/null 2>&1; then
        echo "   ✓ Feed server ready"
        break
    fi
    sleep 1
done
if ! curl -sf http://127.0.0.1:9995/valid.xml > /dev/null 2>&1; then
    echo "ERROR: Feed server failed to start"
    cat /tmp/feed-server-prod.log
    exit 1
fi

# 4. Start SMTP mock on host :1025
echo "=== 3. Starting SMTP mock on :1025 ==="
php "$APP_DIR/scripts/smtp_mock.php" 1025 > /tmp/smtp-mock-prod.log 2>&1 &
SMTP_PID=$!
sleep 1
for i in {1..30}; do
    if php -r '($s=@fsockopen("127.0.0.1",1025,$e,$m,1)) && fclose($s) && exit(0) or exit(1);' > /dev/null 2>&1; then
        echo "   ✓ SMTP mock ready"
        break
    fi
    sleep 1
done

# 5. Ensure host.docker.internal resolves on the host (Linux devcontainers
#    don't add it by default). Add to /etc/hosts only if missing; skip if
#    we lack permission (rootless containers etc.).
if ! getent hosts host.docker.internal >/dev/null 2>&1; then
    if command -v sudo >/dev/null 2>&1 && sudo -n true >/dev/null 2>&1; then
        echo "=== 4. Adding host.docker.internal -> 127.0.0.1 to /etc/hosts ==="
        echo "127.0.0.1 host.docker.internal" | sudo tee -a /etc/hosts > /dev/null
    else
        echo "WARN: host.docker.internal does not resolve and sudo is unavailable;"
        echo "      the feed server inside the container will not be reachable."
    fi
fi

# 5. Detect this host's IP from the perspective of the docker daemon.
#    On plain Docker (CI runner, workstation), the default gateway from
#    `ip route` is exactly what `host-gateway` resolves to inside any
#    container, so passing it explicitly is identical to letting compose
#    fall back to `host-gateway`. On Docker-in-Docker (DinD inside the
#    devcontainer), `host-gateway` would point at DinD's child-network
#    gateway instead of the devcontainer where the feed and SMTP servers
#    live — so we MUST pass the devcontainer's IP explicitly via HOST_IP,
#    which compose substitutes into `extra_hosts`.
if [ -z "${HOST_IP:-}" ]; then
    # Prefer `ip route` (Linux), fall back to the gateway IP from
    # `docker network inspect bridge` (works on Linux hosts without iproute2
    # or inside Docker Desktop), and finally to the literal `host-gateway`
    # which Compose resolves at runtime.
    if command -v ip >/dev/null 2>&1; then
        HOST_IP="$(ip route 2>/dev/null | awk '/default/ {print $3; exit}')"
    fi
    if [ -z "${HOST_IP:-}" ] && command -v docker >/dev/null 2>&1; then
        HOST_IP="$(docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || true)"
    fi
    if [ -z "${HOST_IP:-}" ] || [ "${HOST_IP:-}" = "127.0.0.1" ] || [ "${HOST_IP:-}" = "::1" ]; then
        HOST_IP="host-gateway"
    fi
    export HOST_IP
    echo "=== 5. Detected HOST_IP=$HOST_IP (for compose extra_hosts) ==="
fi

# 6. Build and start the production container
echo "=== 6. Building & starting production container ==="
docker compose -f compose-e2e.yaml up -d --build

echo "=== 7. Waiting for app on :8082 ==="
READY=0
for i in {1..30}; do
    if curl -sf -o /dev/null http://localhost:8082/; then
        READY=1
        break
    fi
    sleep 1
done
if [ "$READY" -ne 1 ]; then
    echo "ERROR: app did not become ready on http://localhost:8082"
    docker compose -f compose-e2e.yaml logs --no-color --tail=80 prod
    exit 1
fi
echo "   ✓ App ready"

# 8. Run the e2e suite
echo ""
echo "=== 8. Running e2e suite ==="
echo "=========================================="

export E2E_BASE_URL="http://localhost:8082"
export E2E_FEED_HOST="host.docker.internal"

set +e
php -d output_buffering=off vendor/bin/pest --testsuite e2e --testdox
TEST_EXIT=$?
set -e

echo ""
echo "=========================================="
if [ "$TEST_EXIT" -eq 0 ]; then
    echo "✓ All E2E tests passed against the production container"
else
    echo "✗ E2E tests failed with exit code: $TEST_EXIT"
fi

exit "$TEST_EXIT"
