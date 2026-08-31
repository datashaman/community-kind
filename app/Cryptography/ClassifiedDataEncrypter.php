<?php

namespace App\Cryptography;

use App\Data\Values\ClassifiedValue;
use App\Exceptions\ClassifiedDataUnavailable;
use Illuminate\Encryption\Encrypter;
use Throwable;

class ClassifiedDataEncrypter
{
    public function __construct(private readonly VersionedKeyRing $keyRing) {}

    public function currentVersion(): string
    {
        return $this->keyRing->currentVersion();
    }

    public function encrypt(
        #[\SensitiveParameter] ClassifiedValue $value,
        string $organisationUuid,
        string $recordUuid,
        string $field,
    ): string {
        try {
            $version = $this->currentVersion();
            $plaintext = json_encode([
                'context' => $this->context($organisationUuid, $recordUuid, $field, $version),
                'value' => $value->reveal(),
            ], JSON_THROW_ON_ERROR);
            $payload = $this->encrypter($version)->encryptString($plaintext);

            return json_encode(['version' => $version, 'payload' => $payload], JSON_THROW_ON_ERROR);
        } catch (ClassifiedDataUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ClassifiedDataUnavailable($exception);
        }
    }

    public function decrypt(
        #[\SensitiveParameter] string $envelope,
        string $organisationUuid,
        string $recordUuid,
        string $field,
    ): ClassifiedValue {
        try {
            $encoded = json_decode($envelope, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($encoded) || ! is_string($encoded['version'] ?? null) || ! is_string($encoded['payload'] ?? null)) {
                throw new ClassifiedDataUnavailable;
            }

            $version = $encoded['version'];
            $decrypted = $this->encrypter($version)->decryptString($encoded['payload']);
            $plaintext = json_decode($decrypted, true, flags: JSON_THROW_ON_ERROR);
            $expectedContext = $this->context($organisationUuid, $recordUuid, $field, $version);

            if (! is_array($plaintext)
                || ($plaintext['context'] ?? null) !== $expectedContext
                || ! is_string($plaintext['value'] ?? null)) {
                throw new ClassifiedDataUnavailable;
            }

            return new ClassifiedValue($plaintext['value']);
        } catch (ClassifiedDataUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ClassifiedDataUnavailable($exception);
        }
    }

    /** @return array{organisation: string, record: string, field: string, key_version: string} */
    private function context(string $organisationUuid, string $recordUuid, string $field, string $version): array
    {
        return [
            'organisation' => $organisationUuid,
            'record' => $recordUuid,
            'field' => $field,
            'key_version' => $version,
        ];
    }

    private function encrypter(string $version): Encrypter
    {
        return new Encrypter($this->keyRing->key($version), 'aes-256-gcm');
    }
}
