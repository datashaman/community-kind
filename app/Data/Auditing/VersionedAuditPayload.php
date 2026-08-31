<?php

namespace App\Data\Auditing;

use InvalidArgumentException;

final class VersionedAuditPayload
{
    public const int CURRENT_VERSION = 1;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $schema
     */
    public static function validate(array $payload, array $schema): void
    {
        if (count($payload) !== count($schema)
            || array_diff_key($payload, $schema) !== []
            || array_diff_key($schema, $payload) !== []) {
            throw new InvalidArgumentException('Audit payload keys must exactly match the versioned allowlist.');
        }

        foreach ($schema as $key => $type) {
            if (! self::matches($payload[$key], $type)) {
                throw new InvalidArgumentException("Audit payload field [{$key}] does not match its allowlisted type.");
            }
        }
    }

    private static function matches(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value) && $value !== '',
            'nullable_string' => $value === null || (is_string($value) && $value !== ''),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'string_list' => is_array($value)
                && array_is_list($value)
                && collect($value)->every(fn (mixed $item): bool => is_string($item) && $item !== ''),
            default => false,
        };
    }
}
