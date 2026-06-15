<?php

declare(strict_types=1);

namespace Tests\E2e;

/**
 * @internal
 */
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

        /** @var array<string> $expectedArray */
        $expectedArray = \is_array($expected) ? $expected : [$expected];
        $matches = \in_array($actualType, $expectedArray, strict: true);
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
    private static function validateBodySchema(array $body, \cebe\openapi\spec\Schema $schema): array
    {
        $errors = [];

        /** @var list<string> */
        $required = $schema->required;
        foreach ($required as $prop) {
            if (\array_key_exists($prop, $body)) {
                continue;
            }
            $errors[] = "Missing required property: {$prop}";
        }

        /** @var array<string, \cebe\openapi\spec\Schema|\cebe\openapi\spec\Reference> */
        $properties = $schema->properties;
        foreach ($properties as $propName => $propSchema) {
            if (! \array_key_exists($propName, $body)) {
                continue;
            }

            $typeValue = $propSchema instanceof \cebe\openapi\spec\Schema ? $propSchema->type : null;
            $error = self::validatePropertyType($propName, $body[$propName] ?? null, $typeValue);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
