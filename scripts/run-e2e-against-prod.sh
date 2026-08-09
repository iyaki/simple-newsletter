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
#    Plain Docker (CI runner, workstation): `docker network inspect bridge`
#    returns exactly the gateway IP that `host-gateway` resolves to inside
#    any container started by the daemon. We can pass it explicitly or omit
#    HOST_IP entirely and let compose resolve `host-gateway`; both behave
#    the same. We pass it explicitly because DinD setups (DinD inside a
#    devcontainer) need a DIFFERENT IP — the devcontainer's, not DinD's
#    bridge gateway — and the user signals that by exporting HOST_IP
#    manually before invoking this script.
#
#    `ip route` is intentionally avoided: on some CI runners (e.g. GitHub
#    Actions ubuntu-latest) it returns an unrelated external gateway that
#    is NOT reachable from inside any container started by the runner's
#    docker daemon, so it can never be the right answer here.
if [ -z "${HOST_IP:-}" ]; then
    if command -v docker >/dev/null 2>&1; then
        BRIDGE_GW="$(docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || true)"
    fi
    if [ -n "${BRIDGE_GW:-}" ] && [ "${BRIDGE_GW:-}" != "127.0.0.1" ] && [ "${BRIDGE_GW:-}" != "::1" ]; then
        HOST_IP="$BRIDGE_GW"
    else
        HOST_IP="host-gateway"
    fi
    export HOST_IP
    echo "=== 5. Detected HOST_IP=$HOST_IP (for compose extra_hosts) ==="
fi
echo "=== 6. Building & starting production container ==="
docker compose -f compose-e2e.yaml up -d --build

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
SMOKE_EXIT=0

# 8b. Smoke-test the CLI delivery entrypoint inside the production container.
# Seeds a confirmed subscription + a feed due this hour, triggers the cron,
# and asserts the SMTP mock received the newsletter. This is the only e2e
# coverage of bin/send-newsletters.php — the HTTP suite never exercises it.
echo "=== 8b. Smoke-testing CLI delivery (bin/send-newsletters.php) ==="
export E2E_FEED_URI="http://host.docker.internal:9995/valid.xml"
export E2E_SUB_EMAIL="delivery-test@example.com"
# trigger_hour must equal the in-container current hour for getScheduled() to
# pick the feed up; the cron uses new \DateTimeImmutable() under the
# production timezone (America/Argentina/Buenos_Aires), so read it from there.
TRIGGER_HOUR="$(docker compose -f compose-e2e.yaml exec -T prod php -r 'echo (int)(new \DateTimeImmutable())->format("H");' 2>/dev/null | tr -dc '0-9')"
if [ -z "$TRIGGER_HOUR" ]; then
    echo "FAIL: could not read current hour from container" >&2
    SMOKE_EXIT=1
else
    TRIGGER_HOUR=$(( 10#$TRIGGER_HOUR ))
    export E2E_TRIGGER_HOUR="$TRIGGER_HOUR"
    php -r '
        $dbPath = getenv("NEWSLETTER_DB_PATH");
        $feedUri = getenv("E2E_FEED_URI");
        $subEmail = getenv("E2E_SUB_EMAIL");
        $triggerHour = (int) getenv("E2E_TRIGGER_HOUR");
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("DELETE FROM subscriptions");
        $pdo->exec("DELETE FROM feeds");
        $pdo->prepare("INSERT INTO feeds (uri, title, link, last_update, trigger_hour, last_sent_post_uri) VALUES (?, ?, ?, ?, ?, NULL)")
            ->execute([$feedUri, "Test Blog", "https://example.com", time(), $triggerHour]);
        $pdo->prepare("INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, 1)")
            ->execute([$feedUri, $subEmail]);
        echo "   ✓ Seeded confirmed subscription + feed due hour {$triggerHour}\n";
    '
    if [ $? -ne 0 ]; then
        echo "FAIL: DB seed failed" >&2
        SMOKE_EXIT=1
    else
        SMTP_BEFORE="$(wc -l < /tmp/smtp-mock-prod.log 2>/dev/null | tr -dc '0-9')"
        SMTP_BEFORE="${SMTP_BEFORE:-0}"
        # Run the cron inside the production container (the real delivery path).
        docker compose -f compose-e2e.yaml exec -T prod php /app/bin/send-newsletters.php >/tmp/cron-prod.log 2>&1
        # The script swallows exceptions and always exits 0, so assert by effect:
        # the SMTP mock must have logged a delivery to the subscriber.
        sleep 1
        if tail -n +"$((SMTP_BEFORE + 1))" /tmp/smtp-mock-prod.log 2>/dev/null | grep -q "To:.*$E2E_SUB_EMAIL"; then
            echo "   ✓ Newsletter delivered to SMTP mock"
        else
            echo "FAIL: cron ran but no email to $E2E_SUB_EMAIL in SMTP mock log" >&2
            echo "   --- cron output ---" >&2
            cat /tmp/cron-prod.log >&2
            echo "   --- smtp mock log (tail) ---" >&2
            tail -20 /tmp/smtp-mock-prod.log >&2
            SMOKE_EXIT=1
        fi
    fi
fi

# 8c. Assert clean PHP startup inside the production container — no failed
# extension loads, startup warnings, or notices. A regression like the
# opcache.so one (broken zend_extension in production.ini) is invisible to the
# HTTP suite because display_startup_errors=Off keeps it out of HTTP responses;
# this step surfaces it from the container logs and a direct CLI probe.
echo "=== 8c. Checking for PHP startup diagnostics ==="
STARTUP_ERR="$(docker compose -f compose-e2e.yaml exec -T prod php -r 'echo "php-ok";' 2>&1)"
if printf '%s\n' "$STARTUP_ERR" | grep -qE 'PHP (Warning|Notice|Parse error|Fatal error|Deprecated):|Failed loading (Zend )?extension'; then
    echo "FAIL: PHP emitted startup diagnostics:" >&2
    printf '%s\n' "$STARTUP_ERR" >&2
    SMOKE_EXIT=1
else
    echo "   ✓ No PHP startup diagnostics"
fi
# Also scan the container's own logs for startup warnings emitted by the
# FrankenPHP worker since boot.
if docker compose -f compose-e2e.yaml logs --no-color prod 2>&1 | grep -qE 'PHP (Warning|Notice|Parse error|Fatal error):|Failed loading (Zend )?extension'; then
    echo "FAIL: production container logs contain PHP startup diagnostics:" >&2
    docker compose -f compose-e2e.yaml logs --no-color prod 2>&1 | grep -E 'PHP (Warning|Notice|Parse error|Fatal error):|Failed loading (Zend )?extension' >&2
    SMOKE_EXIT=1
else
    echo "   ✓ Container logs clean of PHP startup diagnostics"
fi


echo ""
echo "=========================================="
FINAL_EXIT=0
if [ "$TEST_EXIT" -ne 0 ]; then
    echo "✗ E2E tests failed with exit code: $TEST_EXIT"
    FINAL_EXIT=1
else
    echo "✓ All E2E tests passed against the production container"
fi
if [ "$SMOKE_EXIT" -ne 0 ]; then
    echo "✗ Production smoke checks failed (CLI delivery and/or PHP startup diagnostics)" >&2
    FINAL_EXIT=1
else
    echo "✓ Production smoke checks passed (CLI delivery + clean PHP startup)"
fi

exit "$FINAL_EXIT"
