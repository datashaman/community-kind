<?php

namespace App\Cryptography;

use App\Exceptions\ClassifiedDataUnavailable;

class VersionedKeyRing
{
    public function __construct(private readonly string $configurationKey) {}

    public function currentVersion(): string
    {
        return $this->requiredVersion('current_version');
    }

    public function previousVersion(): ?string
    {
        $version = config("{$this->configurationKey}.previous_version");

        if ($version === null || $version === '') {
            return null;
        }

        $version = $this->validateVersion($version);

        if ($version === $this->currentVersion()) {
            throw new ClassifiedDataUnavailable;
        }

        return $version;
    }

    /** @return list<string> */
    public function writeVersions(): array
    {
        $versions = [$this->currentVersion()];
        $previousVersion = $this->previousVersion();

        if ($previousVersion !== null) {
            $versions[] = $previousVersion;
        }

        return $versions;
    }

    public function key(string $version): string
    {
        $version = $this->validateVersion($version);
        $keys = config("{$this->configurationKey}.keys");

        if (! is_array($keys) || ! isset($keys[$version]) || ! is_string($keys[$version])) {
            throw new ClassifiedDataUnavailable;
        }

        $encodedKey = $keys[$version];

        if (! str_starts_with($encodedKey, 'base64:')) {
            throw new ClassifiedDataUnavailable;
        }

        $key = base64_decode(substr($encodedKey, 7), true);

        if (! is_string($key) || mb_strlen($key, '8bit') !== 32) {
            throw new ClassifiedDataUnavailable;
        }

        return $key;
    }

    private function requiredVersion(string $name): string
    {
        $version = config("{$this->configurationKey}.{$name}");

        return $this->validateVersion($version);
    }

    private function validateVersion(mixed $version): string
    {
        if (! is_string($version) || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $version) !== 1) {
            throw new ClassifiedDataUnavailable;
        }

        return $version;
    }
}
