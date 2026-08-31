<?php

namespace App\Casts;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Exceptions\ClassifiedDataUnavailable;
use App\OrganisationContext;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<ClassifiedValue, mixed> */
class ClassifiedValueCast implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    public function __construct(private readonly string $fieldPrefix) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?ClassifiedValue
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new ClassifiedDataUnavailable;
        }

        [$organisationUuid, $recordUuid, $field] = $this->context($model, $attributes);

        return app(ClassifiedDataEncrypter::class)->decrypt(
            $value,
            $organisationUuid,
            $recordUuid,
            $field,
        );
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $classifiedValue = is_string($value) ? new ClassifiedValue($value) : $value;

        if (! $classifiedValue instanceof ClassifiedValue) {
            throw new ClassifiedDataUnavailable;
        }

        [$organisationUuid, $recordUuid, $field] = $this->context($model, $attributes);

        return app(ClassifiedDataEncrypter::class)->encrypt(
            $classifiedValue,
            $organisationUuid,
            $recordUuid,
            $field,
        );
    }

    /** @param array<string, mixed> $attributes
     * @return array{string, string, string}
     */
    private function context(Model $model, array $attributes): array
    {
        $organisationId = $attributes['organisation_id'] ?? null;
        $recordUuid = $attributes['id'] ?? null;
        $type = $attributes['type'] ?? null;

        if (! is_numeric($organisationId) || ! is_string($recordUuid) || ! is_string($type)) {
            throw new ClassifiedDataUnavailable;
        }

        $organisationContext = app(OrganisationContext::class);
        $organisationContext->ensureOwns((int) $organisationId);

        return [
            $organisationContext->organisation()->uuid,
            $recordUuid,
            "{$this->fieldPrefix}.{$type}",
        ];
    }
}
