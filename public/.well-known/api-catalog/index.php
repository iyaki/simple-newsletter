<?php declare(strict_types=1);

\header('Content-Type: application/linkset+json');
\header('Vary: Accept');

echo <<<JSON
{
    "linkset": [
        {
            "anchor": "https://simple-newsletter.com/v1/subscriptions/",
            "service-desc": {
                "href": "https://simple-newsletter.com/api-spec.yaml",
                "type": "application/yaml"
            },
            "service-doc": {
                "href": "https://simple-newsletter.com/#docs",
                "type": "text/html"
            }
        }
    ]
}
JSON;
