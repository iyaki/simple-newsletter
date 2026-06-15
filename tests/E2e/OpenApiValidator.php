<?php

declare(strict_types=1);

namespace Tests\E2e;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait OpenApiValidator
{
    use OpenApiSchemaValidator;
    use OpenApiPathMatcher;

    private static ?OpenApi $openApiSpec = null;

    /**
     * Load and cache the OpenAPI spec
     */
    private static function loadOpenApiSpec(): ?OpenApi
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
        array $requestParams = [],
    ): array {
        $spec = self::loadOpenApiSpec();
        $errors = [];
        $statusCode = (string) $response->getStatusCode();

        $operation = self::findOperation($spec, $method, $path);
        if ($operation === null) {
            $errors[] = "No matching operation found for {$method} {$path}";
            return ['valid' => false, 'errors' => $errors];
        }

        $responseSpec = self::findResponseSpec($operation, $statusCode);
        if ($responseSpec === null) {
            $errors[] = "Status code {$statusCode} not defined for {$method} {$path}";
            return ['valid' => false, 'errors' => $errors];
        }

        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        $contentSpec = $responseSpec->content ?? [];
        $matchedMediaType = self::matchMediaType($contentType, $contentSpec);

        if ($matchedMediaType === null && \count($contentSpec) > 0) {
            return ['valid' => true, 'errors' => []];
        }

        if ($matchedMediaType === 'application/json') {
            $body = $response->toArray();
            $schema = $contentSpec[$matchedMediaType]->schema;
            if ($schema !== null) {
                $bodyErrors = self::validateBodySchema($body, $schema);
                $errors = array_merge($errors, $bodyErrors);
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
