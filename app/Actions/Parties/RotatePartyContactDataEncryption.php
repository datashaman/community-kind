<?php

namespace App\Actions\Parties;

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\PlatformSecurityEventType;
use App\Models\Organisation;
use App\Models\PartyContactPoint;
use App\OrganisationContext;

class RotatePartyContactDataEncryption
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly RecordPlatformSecurityEvent $recordPlatformSecurityEvent,
    ) {}

    public function handle(Organisation $organisation): int
    {
        $this->organisationContext->ensureOwns($organisation->id);
        $currentVersion = $this->encrypter->currentVersion();
        $rotated = 0;
        $previousVersions = [];

        PartyContactPoint::query()
            ->where('data_key_version', '!=', $currentVersion)
            ->lazyById()
            ->each(function (PartyContactPoint $contactPoint) use (
                $currentVersion,
                &$rotated,
                &$previousVersions,
            ): void {
                $previousVersions[] = $contactPoint->data_key_version;
                $value = $contactPoint->encrypted_value;
                $contactPoint->encrypted_value = new ClassifiedValue($value->reveal());
                $contactPoint->forceFill(['data_key_version' => $currentVersion]);
                $contactPoint->save();
                $rotated++;
            });

        $this->recordPlatformSecurityEvent->handle(
            PlatformSecurityEventType::PartyContactDataKeyRotated,
            [
                'organisation_uuid' => $organisation->uuid,
                'record_count' => $rotated,
                'from_versions' => array_values(array_unique($previousVersions)),
                'to_version' => $currentVersion,
            ],
        );

        return $rotated;
    }
}
