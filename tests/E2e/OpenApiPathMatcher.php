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
    /**
     * Find matching operation for method and path
     *
     * @param \cebe\openapi\spec\OpenApi $spec
     * @return object|null
     */
    private static function findOperation(OpenApi $spec, string $method, string $path): ?object
    {
        $methodLower = \strtolower($method);
        $methodLower = \strtolower($method);
        /** @var \cebe\openapi\spec\PathItem $pathItem */
        foreach ($spec->paths as $specPath => $pathItem) {
            if (! self::pathMatches($path, (string) $specPath)) {
                continue;
            }
            /** @var object|null $methodSpec */
            $methodSpec = $pathItem->$methodLower;
            if ($methodSpec !== null) {
                return $methodSpec;
            }
        }
        return null;
    }

    /**
     * Find response spec for status code
     */
    /**
     * Find response spec for status code
     *
     * @param object $operation
     * @return object|null
     */
    private static function findResponseSpec(object $operation, string $statusCode): ?object
    {
        /** @var \cebe\openapi\spec\Responses $responses */
        $responses = $operation->responses;
        /** @var object|null $responseSpec */
        $responseSpec = $responses->$statusCode;
        if ($responseSpec !== null) {
            return $responseSpec;
        }
        // Check for default response using array access
        $responsesArray = $responses->getResponse('default');
        if ($responsesArray !== null) {
            return $responsesArray;
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
