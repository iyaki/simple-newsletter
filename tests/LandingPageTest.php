<?php

declare(strict_types=1);

test('landing page escapes the reflected feed query parameter', function (): void {
    $_GET['feed'] = '"><script>alert(document.domain)</script>';
    \putenv('URI_SELF=http://localhost:8080');

    try {
        \ob_start();
        require __DIR__ . '/../public/index.php';
        $html = (string) \ob_get_clean();
    } finally {
        unset($_GET['feed']);
        if (\ob_get_level() > 0) {
            \ob_end_clean();
        }
    }

    expect($html)
        ->toContain('value="&quot;&gt;&lt;script&gt;alert(document.domain)&lt;/script&gt;"')
        ->and($html)->not->toContain('<script>alert(document.domain)</script>');
});

test('landing page escapes event-handler breakout payloads in the feed parameter', function (): void {
    $_GET['feed'] = '" onfocus=alert(1) autofocus x="';
    \putenv('URI_SELF=http://localhost:8080');

    try {
        \ob_start();
        require __DIR__ . '/../public/index.php';
        $html = (string) \ob_get_clean();
    } finally {
        unset($_GET['feed']);
        if (\ob_get_level() > 0) {
            \ob_end_clean();
        }
    }

    expect($html)
        ->toContain('value="&quot; onfocus=alert(1) autofocus x=&quot;"')
        ->and($html)->not->toContain('value="" onfocus=alert(1)');
});
