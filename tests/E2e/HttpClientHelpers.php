<?php

declare(strict_types=1);

namespace Tests\E2e;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP client helpers for e2e tests
 */
class HttpClientHelpers
{
    private static ?HttpClientInterface $client = null;

    /**
     * @return HttpClientInterface
     */
    private static function client(): HttpClientInterface
    {
        if (self::$client === null) {
            self::$client = HttpClient::create([
                'base_uri' => 'http://localhost:8080',
            ]);
        }

        return self::$client;
    }

    /**
     * Perform a GET request
     *
     * @param array<string, string> $queryParams
     * @param array<string, string> $headers
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public static function get(string $path, array $queryParams = [], array $headers = []): ResponseInterface
    {
        return self::client()->request('GET', $path . self::buildQuery($queryParams), $headers);
    }

    /**
     * Perform a POST request
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public static function post(string $path, array $data = [], array $headers = []): ResponseInterface
    {
        return self::client()->request('POST', $path, ['json' => $data] + $headers);
    }

    /**
     * Safely get response content without throwing on error status
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    public static function getContentSafe(ResponseInterface $response): string
    {
        try {
            return $response->getContent();
        } catch (\Symfony\Component\HttpClient\Exception\ClientException) {
            try {
                $reflection = new \ReflectionClass($response);
                $property = $reflection->getProperty('body');
                // PHP 8.1+: setAccessible is no-op
                /** @var string $body */
                return $property->getValue($response);
            } catch (\Exception) {
                return '';
            }
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Safely get response as array without throwing on error status
     *
     * @return array<string, mixed>
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    public static function toArraySafe(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Build query string from parameters
     *
     * @param array<string, string> $params
     */
    private static function buildQuery(array $params): string
    {
        if ($params === []) {
            return '';
        }

        return '?' . \http_build_query($params);
    }
}
