#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."

echo "=== E2E Test Runner for DevContainer ==="
echo ""

# Kill any existing processes
pkill -9 php 2>/dev/null || true
pkill -9 -f smtp 2>/dev/null || true
sleep 2

# Initialize test database
echo "1. Setting up test database..."
export NEWSLETTER_DB_PATH="$PWD/data/test-e2e.db"
rm -f "$NEWSLETTER_DB_PATH"
php -r "
\$dbPath = getenv('NEWSLETTER_DB_PATH');
\$pdo = new PDO(\"sqlite:{\$dbPath}\");
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['00-setup.sql', '01-feeds.sql', '02-subscriptions.sql', '03-rate-limiting.sql', '99-optimizations.sql'] as \$file) {
    \$pdo->exec(file_get_contents('$PWD/migrations/' . \$file));
}
echo '   ✓ Database initialized\n';
"

# Setup test feeds
echo "2. Setting up test feed server..."
FEED_DIR="/tmp/feedtest"
rm -rf "$FEED_DIR"
mkdir -p "$FEED_DIR"

cat > "$FEED_DIR/valid.xml" << 'XMLEOF'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
<title>Test Blog</title>
<link>https://example.com</link>
<description>Test feed for E2E</description>
<item>
<title>First Post</title>
<link>https://example.com/post1</link>
<description>Test post content</description>
<pubDate>Sat, 20 Jun 2026 12:00:00 +0000</pubDate>
</item>
</channel>
</rss>
XMLEOF

echo "not xml" > "$FEED_DIR/invalid.txt"

php -S 127.0.0.1:9995 -t "$FEED_DIR" > /tmp/feed-server.log 2>&1 &
FEED_PID=$!

for i in {1..20}; do
    if curl -sf http://127.0.0.1:9995/valid.xml > /dev/null 2>&1; then
        echo "   ✓ Feed server ready on port 9995"
        break
    fi
    sleep 0.5
done

# Create SMTP mock server with proper AUTH LOGIN support
cat > /tmp/smtp-mock-with-auth.php << 'SMTPCODE'
<?php
declare(strict_types=1);
file_put_contents('/tmp/smtp-mock.log', "SMTP starting
");
$server = stream_socket_server("tcp://127.0.0.1:1025", $errno, $errstr);
if (!$server) { file_put_contents('/tmp/smtp-mock.log', "Failed: $errstr
", FILE_APPEND); exit(1); }
file_put_contents('/tmp/smtp-mock.log', "Listening
", FILE_APPEND);
while ($client = stream_socket_accept($server)) {
    file_put_contents('/tmp/smtp-mock.log', "Connect
", FILE_APPEND);
    fwrite($client, "220 localhost
");
    $authState = 0;
    while (!feof($client)) {
        $line = fgets($client);
        if ($line === false) break;
        $cmd = trim($line);
        $cmdUpper = strtoupper($cmd);
        file_put_contents('/tmp/smtp-mock.log', "> {$cmd}
", FILE_APPEND);
        if ($authState === 1) {
            fwrite($client, "334 UGFzc3dvcmQ6
");
            $authState = 2;
        } elseif ($authState === 2) {
            fwrite($client, "235 2.7.0 Authentication successful
");
            $authState = 3;
        } elseif (str_starts_with($cmdUpper, 'EHLO ') || str_starts_with($cmdUpper, 'HELO ')) {
            fwrite($client, "250-localhost
250 AUTH LOGIN
");
        } elseif (str_starts_with($cmdUpper, 'AUTH LOGIN')) {
            fwrite($client, "334 VXNlcm5hbWU6
");
            $authState = 1;
        } elseif (str_starts_with($cmdUpper, 'MAIL FROM:')) {
            fwrite($client, "250 2.1.0 OK
");
        } elseif (str_starts_with($cmdUpper, 'RCPT TO:')) {
            fwrite($client, "250 2.1.5 OK
");
        } elseif (str_starts_with($cmdUpper, 'DATA')) {
            fwrite($client, "354 End data with <CR><LF>.<CR><LF>
");
            while (($dataLine = fgets($client)) !== false && rtrim($dataLine) !== '.') {}
            fwrite($client, "250 2.0.0 OK
");
        } elseif (str_starts_with($cmdUpper, 'QUIT')) {
            fwrite($client, "221 2.0.0 Bye
");
            break;
        } elseif (str_starts_with($cmdUpper, 'RSET')) {
            fwrite($client, "250 2.0.0 OK
");
            $authState = 0;
        } elseif (str_starts_with($cmdUpper, 'NOOP')) {
            fwrite($client, "250 2.0.0 OK
");
        } else {
            fwrite($client, "502 5.5.1 Command not implemented
");
        }
    }
    fclose($client);
    file_put_contents('/tmp/smtp-mock.log', "Disconnect
", FILE_APPEND);
}
SMTPCODE

# Start SMTP mock
echo "3. Starting SMTP mock server..."
php /tmp/smtp-mock-with-auth.php > /tmp/smtp-mock-dbg.log 2>&1 &
SMTP_PID=$!
sleep 2

# Wait for SMTP using PHP
for i in {1..30}; do
    if php -r 'exit(fsockopen("127.0.0.1",1025) ? 0 : 1);' 2>/dev/null; then
        echo "   ✓ SMTP mock ready on port 1025"
        break
    fi
    sleep 0.5
done

# Set environment variables
export SECRET_KEY='test-e2e-secret-key-32chars!'
export SERVER_NAME='http://localhost:8082'
export URI_SELF='http://localhost:8082'
export SMTP_HOST='127.0.0.1'
export SMTP_PORT='1025'
export SMTP_ENCRYPTION=''
export SMTP_ALLOW_SELF_SIGNED='1'
export EMAIL_FROM='test@test.localhost'
export EMAIL_REPLY_TO='reply@test.localhost'

# Start HTTP server with env vars
echo "4. Starting test HTTP server on port 8082..."
(
export SECRET_KEY='test-e2e-secret-key-32chars!'
export SERVER_NAME='http://localhost:8082'
export URI_SELF='http://localhost:8082'
export SMTP_HOST='127.0.0.1'
export SMTP_PORT='1025'
export SMTP_ENCRYPTION=''
export SMTP_USER=''
export SMTP_PASSWORD=''
export SMTP_ALLOW_SELF_SIGNED='1'
export EMAIL_FROM='test@test.localhost'
export EMAIL_REPLY_TO='reply@test.localhost'
exec php -d auto_prepend_file="$(pwd)/libs/bootstrap.php" -S localhost:8082 -t public > /tmp/php-server-e2e.log 2>&1
) &
HTTP_PID=$!

for i in {1..30}; do
    if curl -sf http://localhost:8082 > /dev/null 2>&1; then
        echo "   ✓ HTTP server ready"
        break
    fi
    sleep 0.5
done

echo ""
echo "5. Running E2E tests..."
echo "=========================================="

E2E_BASE_URL='http://localhost:8082' vendor/bin/pest tests/E2e/ --testdox
TEST_EXIT=$?

# Cleanup
echo ""
echo "=========================================="
echo "6. Cleaning up..."
kill $FEED_PID $HTTP_PID $SMTP_PID 2>/dev/null || true
pkill -9 php 2>/dev/null || true
pkill -9 -f smtp 2>/dev/null || true

if [ $TEST_EXIT -eq 0 ]; then
    echo "✅ ALL E2E TESTS PASSED"
else
    echo "❌ Some tests FAILED"
fi

exit $TEST_EXIT
