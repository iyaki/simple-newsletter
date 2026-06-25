<?php
declare(strict_types=1);
/** @mago-ignore literal-named-argument */


/**
 * Minimal SMTP mock for E2E tests - accepts AUTH and all messages
 * Usage: php smtp_mock.php [port]
 */

error_reporting(0);

$port = (int) ($argv[1] ?? 1025);
$errno = null;
$errstr = null;

$server = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Failed to start on port {$port}: {$errstr} ({$errno})\n");
    exit(1);
}

fwrite(STDOUT, "SMTP mock listening on port {$port}\n");
$messageCount = 0;

while (true) {
    $client = stream_socket_accept($server, -1);
    if (!$client) {
        continue;
    }
    
    stream_set_timeout($client, 30);
    fwrite($client, "220 localhost ESMTP Test Mock\r\n");
    
    $authenticated = false;
    $mailFrom = null;
    $rcptTo = [];
    
    while (!feof($client)) {
        $line = fgets($client);
        if ($line === false) {
            break;
        }
        
        $trimmed = trim($line);
        $upper = strtoupper($trimmed);
        
        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            fwrite($client, "250-localhost\r\n");
            fwrite($client, "250-SIZE 10485760\r\n");
            fwrite($client, "250-AUTH PLAIN LOGIN\r\n");
            fwrite($client, "250 OK\r\n");
        } elseif (str_starts_with($upper, 'AUTH PLAIN')) {
            // Accept any credentials
            fwrite($client, "235 2.7.0 Authentication successful\r\n");
            $authenticated = true;
        } elseif (str_starts_with($upper, 'AUTH LOGIN')) {
            // RESPOND TO LOGIN CHALLENGE
            fwrite($client, "334 VXNlcm5hbWU6\r\n"); // Base64 "Username:"
            $user = fgets($client);
            fwrite($client, "334 UGFzc3dvcmQ6\r\n"); // Base64 "Password:"
            $pass = fgets($client);
            fwrite($client, "235 2.7.0 Authentication successful\r\n");
            $authenticated = true;
        } elseif (str_starts_with($upper, 'MAIL FROM:')) {
            $mailFrom = substr($trimmed, 10);
            fwrite($client, "250 2.1.0 OK\r\n");
        } elseif (str_starts_with($upper, 'RCPT TO:')) {
            $rcptTo[] = substr($trimmed, 8);
            fwrite($client, "250 2.1.5 OK\r\n");
        } elseif ($upper === 'DATA') {
            fwrite($client, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
            $data = '';
            while (!feof($client)) {
                $dataLine = fgets($client);
                if ($dataLine === false) {
                    break;
                }
                if (trim($dataLine) === '.') {
                    break;
                }
                $data .= $dataLine;
            }
            $messageCount++;
            fwrite(STDOUT, "[{$messageCount}] Mail from: {$mailFrom}, To: " . implode(', ', $rcptTo) . "\n");
            fwrite($client, "250 2.0.0 OK: Message accepted\r\n");
        } elseif ($upper === 'RSET') {
            $mailFrom = null;
            $rcptTo = [];
            fwrite($client, "250 2.0.0 OK\r\n");
        } elseif ($upper === 'NOOP') {
            fwrite($client, "250 2.0.0 OK\r\n");
        } elseif ($upper === 'QUIT') {
            fwrite($client, "221 2.0.0 Bye\r\n");
            break;
        } elseif ($upper === 'STARTTLS') {
            fwrite($client, "502 5.5.1 TLS not available\r\n");
        } else {
            fwrite($client, "502 5.5.1 Command not implemented\r\n");
        }
    }
    
    fclose($client);
}