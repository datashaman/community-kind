<?php

namespace App\Cryptography;

use App\Enums\PartyContactType;
use App\Exceptions\ClassifiedDataUnavailable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContactBlindIndexer
{
    public function __construct(private readonly VersionedKeyRing $keyRing) {}

    public function currentVersion(): string
    {
        return $this->keyRing->currentVersion();
    }

    public function previousVersion(): ?string
    {
        return $this->keyRing->previousVersion();
    }

    /** @return array<string, string> */
    public function indexesForWrite(string $organisationUuid, PartyContactType $type, #[\SensitiveParameter] string $value): array
    {
        $normalized = $this->normalize($type, $value);
        $indexes = [];

        foreach ($this->keyRing->writeVersions() as $version) {
            $indexes[$version] = $this->digest($version, $organisationUuid, $type, $normalized);
        }

        return $indexes;
    }

    /** @return array<string, string> */
    public function indexesForQuery(string $organisationUuid, PartyContactType $type, #[\SensitiveParameter] string $value): array
    {
        return $this->indexesForWrite($organisationUuid, $type, $value);
    }

    private function normalize(PartyContactType $type, #[\SensitiveParameter] string $value): string
    {
        return match ($type) {
            PartyContactType::Email => $this->normalizeEmail($value),
            PartyContactType::Telephone => $this->normalizeTelephone($value),
        };
    }

    private function normalizeEmail(#[\SensitiveParameter] string $value): string
    {
        $normalized = Str::lower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email contact is required.');
        }

        return $normalized;
    }

    private function normalizeTelephone(#[\SensitiveParameter] string $value): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($value));

        if (! is_string($normalized)) {
            throw new ClassifiedDataUnavailable;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (preg_match('/\A\+?[0-9]{7,15}\z/', $normalized) !== 1) {
            throw new InvalidArgumentException('A valid telephone contact is required.');
        }

        return $normalized;
    }

    private function digest(
        string $version,
        string $organisationUuid,
        PartyContactType $type,
        #[\SensitiveParameter] string $normalized,
    ): string {
        return hash_hmac(
            'sha256',
            implode('|', [$organisationUuid, $type->value, $normalized]),
            $this->keyRing->key($version),
        );
    }
}
