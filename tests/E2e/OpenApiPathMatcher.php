<?php

declare(strict_types=1);

namespace Tests\E2e;

use cebe\openapi\spec\OpenApi;

/**
 * @internal
 */
trait OpenApiPathMatcher
{
    /**
     * Find matching operation for method and path
     */
    private static function findOperation(OpenApi $spec, string $method, string $path): ?object
    {
        $methodLower = \strtolower($method);
        foreach ($spec->paths as $specPath => $pathItem) {
            if (! self::pathMatches($path, $specPath)) {
                continue;
            }
            if ($pathItem->$methodLower !== null) {
                return $pathItem->$methodLower;
            }
        }
        return null;
    }

    /**
     * Find response spec for status code
     */
    private static function findResponseSpec(object $operation, string $statusCode): ?object
    {
        if ($operation->responses->$statusCode !== null) {
            return $operation->responses->$statusCode;
        }
        if ($operation->responses->default !== null) {
            return $operation->responses->default;
        }
        return null;
    }

    /**
     * Match content type against spec media types
     *
     * @param array<string, mixed> $contentSpec
     */
    private static function matchMediaType(string $contentType, array $contentSpec): ?string
    {
        foreach (\array_keys($contentSpec) as $mediaType) {
            if (\str_contains($contentType, $mediaType)) {
                return $mediaType;
            }
        }
        return null;
    }

    /**
     * Check if request path matches spec path pattern
     */
    public static function pathMatches(string $requestPath, string $specPath): bool
    {
        $requestPath = \rtrim($requestPath, characters: '/');
        $specPath = \rtrim($specPath, characters: '/');

        return $requestPath === $specPath;
    }
}
