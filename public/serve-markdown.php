<?php declare(strict_types=1);

// Serves the sibling index.md when the agent requests text/markdown; browsers fall through to HTML.
if (\str_contains(\strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/markdown')) {
    $content = \file_get_contents(\dirname($_SERVER['SCRIPT_FILENAME']) . '/index.md');
    if ($content === false) {
        throw new \RuntimeException("Missing markdown version of {$_SERVER['SCRIPT_NAME']}");
    }
    \header('Content-Type: text/markdown; charset=utf-8');
    \header('Vary: Accept');
    \header('x-markdown-tokens: ' . (int) (\strlen($content) / 4)); // ponytail: word/4 estimate, real tokenizer if it ever matters
    echo $content;
    exit;
}
