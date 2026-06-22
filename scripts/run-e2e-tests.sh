# NOTE: E2E tests currently fail because they attempt to fetch real RSS feeds from
# URLs like https://example.com/feed.xml which don't exist. To pass E2E tests, either:
# 1. Start a local feed test server (see tests/Adapters/FeedTestServer.php for example)
# 2. Update tests to use real, internet-accessible feed URLs
# 3. Mock feed imports at the application level for test environments
# 
# For now, unit tests (vendor/bin/pest without --testsuite e2e) verify all core functionality.
#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
cd "$APP_DIR"

# Set test database path
export NEWSLETTER_DB_PATH="${APP_DIR}/data/test-e2e.db"

# Initialize test database
if [ -f "$NEWSLETTER_DB_PATH" ]; then
    rm "$NEWSLETTER_DB_PATH"
fi
# Apply migrations using PHP (sqlite3 CLI may not be available)
echo "Setting up test database..."
php -r "
\$dbPath = getenv('NEWSLETTER_DB_PATH');
if (file_exists(\$dbPath)) {
    unlink(\$dbPath);
}
\$pdo = new PDO(\"sqlite:{\$dbPath}\");
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['00-setup.sql', '01-feeds.sql', '02-subscriptions.sql', '03-rate-limiting.sql', '99-optimizations.sql'] as \$file) {
    \$pdo->exec(file_get_contents('/app/migrations/' . \$file));
}
"

# In CI or devcontainer, start the server ourselves with test env vars
if [ -n "$CI" ] || [ -n "$DEVCONTAINER" ]; then
    echo "CI/devcontainer environment detected - starting isolated test server..."
    export SECRET_KEY='test-e2e-secret-key-32chars!'
    export SERVER_NAME='http://localhost:8080'
    export URI_SELF='http://localhost:8080'
    export SMTP_HOST='127.0.0.1'
    export SMTP_PORT='1025'
    export SMTP_ALLOW_SELF_SIGNED='1'
    
    php -S localhost:8080 -t public > /tmp/php-server-e2e.log 2>&1 &
    SERVER_PID=$!
    for i in {1..30}; do
        if curl -s http://localhost:8080 > /dev/null 2>&1; then
            echo "Server is up"
            break
        fi
        sleep 1
    done
    if ! curl -s http://localhost:8080 > /dev/null 2>&1; then
        echo "ERROR: Server failed to start"
        cat /tmp/php-server-e2e.log
        exit 1
    fi
    trap "kill $SERVER_PID 2>/dev/null || true" EXIT
else
    # Check if dev server is running
    if ! curl -s http://localhost:8080 > /dev/null 2>&1; then
        echo "Dev server not running. Please start it with: docker compose up"
        echo "Or alternatively: php -S localhost:8080 -t public"
        exit 1
    fi
fi

echo "Running e2e tests..."
vendor/bin/pest --testsuite e2e --colors=never
echo ""
echo "E2E tests completed!"