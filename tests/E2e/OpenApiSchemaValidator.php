<?php

declare(strict_types=1);

namespace Tests\E2e;

trait OpenApiSchemaValidator
{
    /**
     * Validate property type against expected type
     */
    private static function validatePropertyType(string $propName, mixed $value, ?string $expectedType): ?string
    {
        if ($expectedType === null) {
            return null;
        }

        $actualType = \gettype($value);
        $typeMap = [
            'string' => 'string',
            'integer' => 'integer',
            'number' => ['integer', 'double'],
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'array',
        ];

        $expected = $typeMap[$expectedType] ?? null;
        if ($expected === null) {
            return null;
        }

        $matches = \in_array($actualType, $expected, strict: true);
        if (! $matches) {
            return "Property {$propName} has wrong type: expected {$expectedType}, got {$actualType}";
        }

        return null;
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
            if (\array_key_exists($prop, $body)) {
                continue;
            }
            $errors[] = "Missing required property: {$prop}";
        }

        // Check property types
        $properties = $schema->properties ?? [];
        foreach ($properties as $propName => $propSchema) {
            if (! \array_key_exists($propName, $body)) {
                continue;
            }

            $value = $body[$propName];
            $error = self::validatePropertyType($propName, $value, $propSchema->type);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
