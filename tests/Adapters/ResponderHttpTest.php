<?php

declare(strict_types=1);






test('sendResponse with JsonResponse echoes body', function (): void {});

test('responseBuilderFromContentNegotiation with application/json returns builder that creates JsonResponse', function (): void {});

test('responseBuilderFromContentNegotiation with text/html returns builder that creates HtmlResponse', function (): void {});

test('responseBuilderFromContentNegotiation with unsupported type defaults to JSON', function (): void {});

test('responseBuilderFromContentNegotiation returns builder that creates RedirectResponse', function (): void {});

test('sendResponse with non-ok non-redirect sets 400 status', function (): void {});

test('content negotiation HTML builder fromEndUserException creates HtmlResponse', function (): void {});

test('redirect builder fromEndUserException creates RedirectResponse with ok=false', function (): void {});
