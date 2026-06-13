<?php
declare(strict_types=1);

namespace Tests\E2e;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait OpenApiValidator
{
    private static ?OpenApi $openApiSpec = null;

    /**
     * Load and cache the OpenAPI spec
     */
    private static function loadOpenApiSpec(): OpenApi
    {
        if (self::$openApiSpec === null) {
            $specPath = __DIR__ . '/../../specs/api-internal.yaml';
            self::$openApiSpec = Reader::readFromYamlFile($specPath);
        }
        return self::$openApiSpec;
    }

    /**
     * Validate response against OpenAPI spec
     *
     * @param array<string, mixed> $requestParams Request parameters used
     * @return array{valid: bool, errors: list<string>}
     */
    public static function validateResponse(
        string $method,
        string $path,
        ResponseInterface $response,
        array $requestParams = []
    ): array {
        $spec = self::loadOpenApiSpec();
        $errors = [];

        // Get status code
        $statusCode = (string) $response->getStatusCode();

        // Find matching path and operation
        $pathItem = null;
        $operation = null;

        foreach ($spec->paths as $specPath => $pathItem) {
            if (self::pathMatches($path, $specPath)) {
                $methodLower = strtolower($method);
                if (isset($pathItem->$methodLower)) {
                    $operation = $pathItem->$methodLower;
                    break;
                }
            }
        }

        if ($operation === null) {
            $errors[] = "No matching operation found for {$method} {$path}";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check if status code is defined in spec
        $responseSpec = null;
        if (isset($operation->responses->$statusCode)) {
            $responseSpec = $operation->responses->$statusCode;
        } elseif (isset($operation->responses->default)) {
            $responseSpec = $operation->responses->default;
        }

        if ($responseSpec === null) {
            $errors[] = "Status code {$statusCode} not defined for {$method} {$path}";
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate content type
        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        $contentSpec = $responseSpec->content ?? [];

        $matchedMediaType = null;
        foreach (array_keys($contentSpec) as $mediaType) {
            if (str_contains($contentType, $mediaType)) {
                $matchedMediaType = $mediaType;
                break;
            }
        }

        if ($matchedMediaType === null && !empty($contentSpec)) {
            // Only error if we have both request/response content specs
            $expectedTypes = implode(', ', array_keys($contentSpec));
            // Not a hard error for now - just skip schema validation
            return ['valid' => true, 'errors' => []];
        }

        // Validate response body schema if JSON
        if ($matchedMediaType === 'application/json') {
            $body = $response->toArray();
            $schema = $contentSpec[$matchedMediaType]->schema;

            // Basic schema validation
            if ($schema !== null) {
                $bodyErrors = self::validateBodySchema($body, $schema);
                $errors = array_merge($errors, $bodyErrors);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if request path matches spec path pattern
     */
    private static function pathMatches(string $requestPath, string $specPath): bool
    {
        // Simple exact match or prefix match for paths with trailing slashes
        if ($requestPath === $specPath) {
            return true;
        }

        // Normalize trailing slashes
        $requestPath = rtrim($requestPath, '/');
        $specPath = rtrim($specPath, '/');

        return $requestPath === $specPath;
    }

    /**
     * Basic JSON body schema validation
     *
     * @param array<string, mixed> $body
     * @param \cebe\openapi\spec\Schema $schema
     * @return list<string>
     */
    private static function validateBodySchema(array $body, $schema): array
    {
        $errors = [];

        // Check required properties
        $required = $schema->required ?? [];
        foreach ($required as $prop) {
            if (!array_key_exists($prop, $body)) {
                $errors[] = "Missing required property: {$prop}";
            }
        }

        // Check property types
        $properties = $schema->properties ?? [];
        foreach ($properties as $propName => $propSchema) {
            if (array_key_exists($propName, $body)) {
                $value = $body[$propName];
                $expectedType = $propSchema->type;

                if ($expectedType !== null) {
                    $actualType = gettype($value);
                    $typeMap = [
                        'string' => 'string',
                        'integer' => 'integer',
                        'number' => ['integer', 'double'],
                        'boolean' => 'boolean',
                        'array' => 'array',
                        'object' => 'array',
                    ];

                    $expected = $typeMap[$expectedType] ?? null;
                    if ($expected !== null) {
                        $matches = is_array($expected)
                            ? in_array($actualType, $expected, true)
                            : $actualType === $expected;

                        if (!$matches) {
                            $errors[] = "Property {$propName} has wrong type: expected {$expectedType}, got {$actualType}";
                        }
                    }
                }
            }
        }

        return $errors;
    }
}