<?php

use App\Actions\Parties\FindPartiesByContact;
use App\Actions\Parties\RebuildPartyContactBlindIndexes;
use App\Actions\Parties\RotatePartyContactDataEncryption;
use App\Actions\Parties\StorePartyContact;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyContactType;
use App\Exceptions\ClassifiedDataUnavailable;
use App\Jobs\RebuildPartyContactBlindIndexes as RebuildPartyContactBlindIndexesJob;
use App\Jobs\RotatePartyContactDataEncryption as RotatePartyContactDataEncryptionJob;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\PlatformSecurityEvent;
use App\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    configurePartyContactKeys(
        dataCurrent: 'data-v1',
        dataKeys: ['data-v1' => testPartyContactKey()],
        indexCurrent: 'index-v1',
        indexKeys: ['index-v1' => testPartyContactKey()],
    );
});

it('stores randomized classified ciphertext while leaving Party names searchable', function () {
    $organisation = Organisation::factory()->active()->create();

    [$firstContact, $secondContact, $party] = app(OrganisationContext::class)->run(
        $organisation,
        function () {
            $firstParty = partyForCurrentOrganisation(['display_name' => 'Ada Example']);
            $secondParty = partyForCurrentOrganisation(['display_name' => 'Bea Example']);
            $store = app(StorePartyContact::class);

            return [
                $store->handle($firstParty, PartyContactType::Email, 'ada@example.test'),
                $store->handle($secondParty, PartyContactType::Email, 'ada@example.test'),
                Party::query()->where('display_name', 'Ada Example')->firstOrFail(),
            ];
        },
    );

    $firstCiphertext = DB::table('party_contact_points')->where('id', $firstContact->id)->value('encrypted_value');
    $secondCiphertext = DB::table('party_contact_points')->where('id', $secondContact->id)->value('encrypted_value');

    expect($firstCiphertext)
        ->toBeString()
        ->not->toContain('ada@example.test')
        ->not->toBe($secondCiphertext);
    expect($party->display_name)->toBe('Ada Example');
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app(OrganisationContext::class)->run($organisation, function () use ($firstContact): void {
        expect($firstContact->encrypted_value)
            ->toBeInstanceOf(ClassifiedValue::class)
            ->and($firstContact->encrypted_value->reveal())->toBe('ada@example.test');
    });
});

it('fails closed when ciphertext is swapped across records, Organisations, or fields', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();

    [$firstEmail, $firstTelephone] = app(OrganisationContext::class)->run($firstOrganisation, function () {
        $party = partyForCurrentOrganisation();
        $store = app(StorePartyContact::class);

        return [
            $store->handle($party, PartyContactType::Email, 'first@example.test'),
            $store->handle($party, PartyContactType::Telephone, '+27 82 555 0101'),
        ];
    });
    $secondEmail = app(OrganisationContext::class)->run($secondOrganisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'second@example.test');
    });
    $firstCiphertext = DB::table('party_contact_points')->where('id', $firstEmail->id)->value('encrypted_value');

    DB::table('party_contact_points')->where('id', $firstTelephone->id)->update(['encrypted_value' => $firstCiphertext]);
    DB::table('party_contact_points')->where('id', $secondEmail->id)->update(['encrypted_value' => $firstCiphertext]);

    app(OrganisationContext::class)->run(
        $firstOrganisation,
        fn () => expect(fn () => PartyContactPoint::query()->findOrFail($firstTelephone->id)->encrypted_value)
            ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.'),
    );
    app(OrganisationContext::class)->run(
        $secondOrganisation,
        fn () => expect(fn () => PartyContactPoint::query()->findOrFail($secondEmail->id)->encrypted_value)
            ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.'),
    );

    DB::table('party_contact_points')->where('id', $firstEmail->id)->update(['type' => PartyContactType::Telephone->value]);

    app(OrganisationContext::class)->run(
        $firstOrganisation,
        fn () => expect(fn () => PartyContactPoint::query()->findOrFail($firstEmail->id)->encrypted_value)
            ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.'),
    );
});

it('returns only exact tenant-local email and telephone matches', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();

    $firstParty = app(OrganisationContext::class)->run($firstOrganisation, function () {
        $party = partyForCurrentOrganisation();
        $store = app(StorePartyContact::class);
        $store->handle($party, PartyContactType::Email, 'Person@Example.Test');
        $store->handle($party, PartyContactType::Telephone, '+27 (82) 555-0101');

        return $party;
    });
    app(OrganisationContext::class)->run($secondOrganisation, function () {
        $party = partyForCurrentOrganisation();
        app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'person@example.test');
    });

    app(OrganisationContext::class)->run($firstOrganisation, function () use ($firstParty): void {
        $find = app(FindPartiesByContact::class);

        expect($find->handle(PartyContactType::Email, ' person@example.test ')->modelKeys())
            ->toBe([$firstParty->id]);
        expect($find->handle(PartyContactType::Telephone, '0027 82 555 0101')->modelKeys())
            ->toBe([$firstParty->id]);
        expect($find->handle(PartyContactType::Email, 'other@example.test'))->toBeEmpty();
        expect($find->handle(PartyContactType::Email, 'person@example.tests'))->toBeEmpty();
        expect($find->handle(PartyContactType::Telephone, '8255501'))->toBeEmpty();
    });
});

it('produces different blind indexes for identical contacts in different Organisations', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();

    $firstContact = app(OrganisationContext::class)->run($firstOrganisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'shared@example.test');
    });
    $secondContact = app(OrganisationContext::class)->run($secondOrganisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'shared@example.test');
    });

    expect($firstContact->getRawOriginal('current_blind_index'))
        ->not->toBe($secondContact->getRawOriginal('current_blind_index'));
});

it('rejects cross-Organisation Party associations and keeps contact rows tenant scoped', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();

    $firstContact = app(OrganisationContext::class)->run($firstOrganisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'first@example.test');
    });
    $secondParty = app(OrganisationContext::class)->run(
        $secondOrganisation,
        fn () => partyForCurrentOrganisation(),
    );
    $rawContact = DB::table('party_contact_points')->where('id', $firstContact->id)->first();

    expect(fn () => DB::transaction(fn () => DB::table('party_contact_points')->insert([
        'id' => (string) Str::uuid7(),
        'organisation_id' => $firstOrganisation->id,
        'party_id' => $secondParty->id,
        'type' => $rawContact->type,
        'encrypted_value' => $rawContact->encrypted_value,
        'data_key_version' => $rawContact->data_key_version,
        'current_index_key_version' => $rawContact->current_index_key_version,
        'current_blind_index' => $rawContact->current_blind_index,
        'previous_index_key_version' => null,
        'previous_blind_index' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('party_contact_points')
        ->where('id', $firstContact->id)
        ->update([
            'organisation_id' => $secondOrganisation->id,
            'party_id' => $secondParty->id,
        ])))->toThrow(QueryException::class);

    app(OrganisationContext::class)->run($secondOrganisation, function () use ($firstContact): void {
        expect(PartyContactPoint::query()->find($firstContact->id))->toBeNull();
    });
    expect(fn () => PartyContactPoint::query()->find($firstContact->id))->toThrow(LogicException::class);
});

it('dual-reads and dual-writes while contact indexes rotate', function () {
    $organisation = Organisation::factory()->active()->create();

    $oldParty = app(OrganisationContext::class)->run($organisation, function () {
        $party = partyForCurrentOrganisation(['display_name' => 'Old Index']);
        app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'shared@example.test');

        return $party;
    });
    $indexV1Key = config('classified_data.contact_index.keys.index-v1');
    configurePartyContactKeys(
        dataCurrent: 'data-v1',
        dataKeys: config('classified_data.encryption.keys'),
        indexCurrent: 'index-v2',
        indexKeys: ['index-v1' => $indexV1Key, 'index-v2' => testPartyContactKey()],
        indexPrevious: 'index-v1',
    );

    $newParty = app(OrganisationContext::class)->run($organisation, function () {
        $party = partyForCurrentOrganisation(['display_name' => 'New Index']);
        $contact = app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'shared@example.test');

        expect($contact->current_index_key_version)->toBe('index-v2');
        expect($contact->previous_index_key_version)->toBe('index-v1');

        return $party;
    });
    $oldCiphertext = DB::table('party_contact_points')
        ->where('party_id', $oldParty->id)
        ->value('encrypted_value');

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $oldParty, $newParty): void {
        expect(app(FindPartiesByContact::class)
            ->handle(PartyContactType::Email, 'shared@example.test')->modelKeys())
            ->toBe([$newParty->id, $oldParty->id]);
        expect(app(RebuildPartyContactBlindIndexes::class)->handle($organisation))->toBe(1);
        expect(app(RebuildPartyContactBlindIndexes::class)->handle($organisation))->toBe(0);
    });
    expect(DB::table('party_contact_points')->where('party_id', $oldParty->id)->value('encrypted_value'))
        ->toBe($oldCiphertext);

    configurePartyContactKeys(
        dataCurrent: 'data-v1',
        dataKeys: config('classified_data.encryption.keys'),
        indexCurrent: 'index-v2',
        indexKeys: ['index-v2' => config('classified_data.contact_index.keys.index-v2')],
    );

    app(OrganisationContext::class)->run($organisation, function () use ($oldParty, $newParty): void {
        expect(app(FindPartiesByContact::class)
            ->handle(PartyContactType::Email, 'shared@example.test')->modelKeys())
            ->toBe([$newParty->id, $oldParty->id]);
    });
});

it('re-encrypts old data keys idempotently and retains recovery access while the old key is present', function () {
    $organisation = Organisation::factory()->active()->create();

    $contact = app(OrganisationContext::class)->run($organisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'restore@example.test');
    });
    $oldCiphertext = DB::table('party_contact_points')->where('id', $contact->id)->value('encrypted_value');
    $dataV1Key = config('classified_data.encryption.keys.data-v1');
    configurePartyContactKeys(
        dataCurrent: 'data-v2',
        dataKeys: ['data-v1' => $dataV1Key, 'data-v2' => testPartyContactKey()],
        indexCurrent: 'index-v1',
        indexKeys: config('classified_data.contact_index.keys'),
    );

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $contact, $oldCiphertext): void {
        expect(PartyContactPoint::query()->findOrFail($contact->id)->encrypted_value->reveal())
            ->toBe('restore@example.test');
        expect(app(RotatePartyContactDataEncryption::class)->handle($organisation))->toBe(1);
        expect(app(RotatePartyContactDataEncryption::class)->handle($organisation))->toBe(0);

        $rotated = PartyContactPoint::query()->findOrFail($contact->id);

        expect($rotated->data_key_version)->toBe('data-v2');
        expect($rotated->encrypted_value->reveal())->toBe('restore@example.test');
        expect($rotated->getRawOriginal('encrypted_value'))->not->toBe($oldCiphertext);
    });
});

it('fails closed with missing or incorrect recovery keys without reporting classified content', function () {
    $organisation = Organisation::factory()->active()->create();

    $contact = app(OrganisationContext::class)->run($organisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'never-log@example.test');
    });
    $ciphertext = DB::table('party_contact_points')->where('id', $contact->id)->value('encrypted_value');
    configurePartyContactKeys(
        dataCurrent: 'data-v1',
        dataKeys: [],
        indexCurrent: 'index-v1',
        indexKeys: config('classified_data.contact_index.keys'),
    );
    Log::spy();

    app(OrganisationContext::class)->run(
        $organisation,
        fn () => expect(fn () => PartyContactPoint::query()->findOrFail($contact->id)->encrypted_value)
            ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.'),
    );
    configurePartyContactKeys(
        dataCurrent: 'data-v1',
        dataKeys: ['data-v1' => testPartyContactKey()],
        indexCurrent: 'index-v1',
        indexKeys: config('classified_data.contact_index.keys'),
    );
    app(OrganisationContext::class)->run(
        $organisation,
        fn () => expect(fn () => PartyContactPoint::query()->findOrFail($contact->id)->encrypted_value)
            ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.'),
    );

    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
    expect((new ClassifiedDataUnavailable)->getMessage())
        ->not->toContain('never-log@example.test')
        ->not->toContain($ciphertext);
});

it('keeps classified values and blind indexes out of serialization, queues, search, and security telemetry', function () {
    $organisation = Organisation::factory()->active()->create();

    $contact = app(OrganisationContext::class)->run($organisation, function () {
        $party = partyForCurrentOrganisation();

        return app(StorePartyContact::class)->handle($party, PartyContactType::Email, 'private@example.test');
    });
    $ciphertext = $contact->getRawOriginal('encrypted_value');
    $blindIndex = $contact->getRawOriginal('current_blind_index');
    expect(json_encode($contact->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('private@example.test')
        ->not->toContain($ciphertext)
        ->not->toContain($blindIndex);
    $queuePayload = serialize([
        new RotatePartyContactDataEncryptionJob($organisation),
        new RebuildPartyContactBlindIndexesJob($organisation),
    ]);
    expect($queuePayload)
        ->not->toContain('private@example.test')
        ->not->toContain($ciphertext)
        ->not->toContain($blindIndex);
    expect(method_exists($contact, 'searchable'))->toBeFalse();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
        app(RebuildPartyContactBlindIndexes::class)->handle($organisation);
    });
    $telemetry = PlatformSecurityEvent::query()
        ->where('type', 'party_contact_index_key_rebuilt')
        ->latest('occurred_at')
        ->firstOrFail()
        ->metadata;

    expect(json_encode($telemetry, JSON_THROW_ON_ERROR))
        ->not->toContain('private@example.test')
        ->not->toContain($ciphertext)
        ->not->toContain($blindIndex);
    expect(array_keys($telemetry))->toBe([
        'organisation_uuid',
        'record_count',
        'current_version',
        'previous_version',
    ]);
});

/** @param array<string, string> $dataKeys
 * @param  array<string, string>  $indexKeys
 */
function configurePartyContactKeys(
    string $dataCurrent,
    array $dataKeys,
    string $indexCurrent,
    array $indexKeys,
    ?string $indexPrevious = null,
): void {
    config([
        'classified_data.encryption' => [
            'current_version' => $dataCurrent,
            'keys' => $dataKeys,
        ],
        'classified_data.contact_index' => [
            'current_version' => $indexCurrent,
            'previous_version' => $indexPrevious,
            'keys' => $indexKeys,
        ],
    ]);
}

function testPartyContactKey(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

/** @param array<string, mixed> $attributes */
function partyForCurrentOrganisation(array $attributes = []): Party
{
    return Party::factory()->create([
        'organisation_id' => app(OrganisationContext::class)->id(),
        ...$attributes,
    ]);
}
