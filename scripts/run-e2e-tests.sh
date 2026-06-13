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
# Check if dev server is running
if ! curl -s http://localhost:8080 > /dev/null 2>&1; then
    echo "Dev server not running. Please start it with: docker compose up"
    echo "Or alternatively: php -S localhost:8080 -t public"
    exit 1
fi

echo "Running e2e tests..."
vendor/bin/pest --testsuite e2e --colors=never
echo ""
echo "E2E tests completed!"