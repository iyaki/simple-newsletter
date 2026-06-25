#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
cd "$APP_DIR"

export SMTP_HOST='127.0.0.1'
export SMTP_PORT='1025'
export SMTP_ENCRYPTION=''
export SMTP_USER='test'
export SMTP_PASSWORD='test'
export EMAIL_FROM='noreply@example.com'
export EMAIL_REPLY_TO='dev@example.com'

cleanup() {
    [ -n "$SMTP_PID" ] && kill $SMTP_PID 2>/dev/null || true
    [ -n "$FEED_PID" ] && kill $FEED_PID 2>/dev/null || true
    [ -n "$SERVER_PID" ] && kill $SERVER_PID 2>/dev/null || true
}
trap cleanup EXIT

rm -f "${APP_DIR}/data/test-e2e.db"

echo "Starting SMTP mock..."
php "$APP_DIR/scripts/smtp_mock.php" 1025 > /tmp/smtp-mock.log 2>&1 &
SMTP_PID=$!
sleep 1

echo "Starting feed test server..."
FEED_DIR="/tmp/feedtest"
if [ ! -d "$FEED_DIR" ] || [ ! -f "$FEED_DIR/valid.xml" ]; then
    mkdir -p "$FEED_DIR"
    cat > "$FEED_DIR/valid.xml" << 'FEED_EOF'
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
FEED_EOF
fi
php -S 0.0.0.0:9995 -t "$FEED_DIR" > /tmp/feed-server.log 2>&1 &
FEED_PID=$!

echo "Starting PHP dev server on port 8082..."
php -S localhost:8082 -t "${APP_DIR}/public" > /tmp/php-server-e2e.log 2>&1 &
SERVER_PID=$!

echo "Waiting for services to be ready..."
for i in {1..30}; do
    SMTP_OK=0
    FEED_OK=0
    HTTP_OK=0
    
    php -r '($s=@fsockopen("127.0.0.1",1025,$e,$m,1)) && fclose($s) && exit(0) or exit(1);' && SMTP_OK=1
    curl -s http://127.0.0.1:9995/valid.xml > /dev/null 2>&1 && FEED_OK=1
    curl -s http://localhost:8082 > /dev/null 2>&1 && HTTP_OK=1
    
    [ $SMTP_OK -eq 1 ] && [ $FEED_OK -eq 1 ] && [ $HTTP_OK -eq 1 ] && break
    sleep 1
done

if [ $SMTP_OK -ne 1 ]; then
    echo "ERROR: SMTP mock failed to start"
    cat /tmp/smtp-mock.log
    exit 1
fi
if [ $FEED_OK -ne 1 ]; then
    echo "ERROR: Feed server failed to start"
    cat /tmp/feed-server.log
    exit 1
fi
if [ $HTTP_OK -ne 1 ]; then
    echo "ERROR: PHP server failed to start"
    cat /tmp/php-server-e2e.log
    exit 1
fi

echo ""
echo "All services ready (SMTP:1025, Feed:9995, HTTP:8082)"
echo ""
echo "Running e2e tests..."
echo ""
php -d output_buffering=off vendor/bin/pest --testsuite e2e
RESULT=$?

echo ""
if [ $RESULT -eq 0 ]; then
    echo "✓ All E2E tests passed!"
else
    echo "✗ E2E tests failed with exit code: $RESULT"
fi
exit $RESULT