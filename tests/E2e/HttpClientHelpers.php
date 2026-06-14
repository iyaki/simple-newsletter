<?php

declare(strict_types=1);

namespace Tests\E2e;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait HttpClientHelpers
{
    private static HttpClientInterface $httpClient;
    private static string $baseUrl = 'http://localhost:8080';

    /**
     * Get or create the HTTP client instance with base_uri configured
     */
    public static function httpClient(): HttpClientInterface
    {
        if (self::$httpClient === null) {
            self::$httpClient = HttpClient::create(['base_uri' => self::$baseUrl]);
        }
        return self::$httpClient;
    }

    /**
     * Perform a GET request
     *
     * @param array<string, string> $queryParams
     * @param array<string, string> $headers
     */
    public static function get(string $path, array $queryParams = [], array $headers = []): ResponseInterface
    {
        $url = $path;
        if (\count($queryParams) > 0) {
            $url .= '?' . http_build_query($queryParams);
        }

        return self::httpClient()->request('GET', $url, [
            'headers' => $headers,
        ]);
    }

    /**
     * Perform a POST request
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function post(string $path, array $data = [], array $headers = []): ResponseInterface
    {
        return self::httpClient()->request('POST', $path, [
            'json' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Safely get response content without throwing on error status
     */
    public static function getContentSafe(ResponseInterface $response): string
    {
        try {
            return $response->getContent();
        } catch (\Symfony\Component\HttpClient\Exception\ClientException $e) {
            // For 4xx errors, try to get the body anyway
            try {
                $reflection = new \ReflectionClass($response);
                $property = $reflection->getProperty('body');
                $property->setAccessible(true);
                return (string) $property->getValue($response);
            } catch (\Exception $e2) {
                return '';
            }
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Safely get response as array without throwing on error status
     *
     * @return array<string, mixed>
     */
    public static function toArraySafe(ResponseInterface $response): array
    {
        $content = self::getContentSafe($response);
        if (\count($content) === 0) {
            return [];
        }

        $decoded = \json_decode($content, associative: true);
        return is_array($decoded) ? $decoded : [];
    }
}
