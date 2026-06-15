<?php

declare(strict_types=1);

namespace Tests\E2e;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
trait OpenApiValidator
{
    use OpenApiSchemaValidator;
    use OpenApiPathMatcher;

    private static ?OpenApi $openApiSpec = null;

    /**
     * Load and cache the OpenAPI spec
     *
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\IOException
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
     * @param array<string, mixed> $_requestParams Request parameters used
     * @return array{valid: bool, errors: list<string>}
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     */
    /**
     * Validate response against OpenAPI spec
     *
     * @param array<string, mixed> $_requestParams Request parameters used
     * @return array{valid: bool, errors: list<string>}
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\IOException
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     */
    public static function validateResponse(
        string $method,
        string $path,
        ResponseInterface $response,
        array $_requestParams = [],
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
        /** @var array<string, mixed> $contentSpec */
        $contentSpec = $responseSpec->content ?? [];
        $matchedMediaType = self::matchMediaType($contentType, $contentSpec);

        if ($matchedMediaType === null) {
            return ['valid' => true, 'errors' => []];
        }

        if ($matchedMediaType === 'application/json' && isset($contentSpec[$matchedMediaType])) {
            /** @var array<string, mixed> $body */
            $body = $response->toArray();
            $contentSpecEntry = $contentSpec[$matchedMediaType];
            if (! \is_object($contentSpecEntry) || ! isset($contentSpecEntry->schema)) {
                return ['valid' => true, 'errors' => []];
            }
            /** @var \cebe\openapi\spec\Schema $schemaObj */
            $schemaObj = $contentSpecEntry->schema;
            assert($schemaObj instanceof \cebe\openapi\spec\Schema);
            $bodyErrors = self::validateBodySchema($body, $schemaObj);
            $errors = array_merge($errors, $bodyErrors);
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
