<?php

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Actions\CaseConfidentiality\RevokeRestrictedAccess;
use App\Actions\CaseDocuments\ReleaseCaseDocument;
use App\Contracts\MalwareScanner;
use App\Data\CaseDocuments\MalwareScanResult;
use App\Enums\CaseClassification;
use App\Enums\CaseDocumentState;
use App\Enums\MalwareScanVerdict;
use App\Enums\OrganisationRole;
use App\Enums\RestrictedAccessPermission;
use App\Enums\TenantAuditEventType;
use App\Jobs\ScanCaseDocument;
use App\Models\CaseDocument;
use App\Models\CaseDocumentVersion;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

final class DeterministicMalwareScanner implements MalwareScanner
{
    /** @var list<MalwareScanResult> */
    public array $results = [];

    /** @var list<string> */
    public array $scannedBytes = [];

    public bool $healthy = true;

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function scan(mixed $stream): MalwareScanResult
    {
        $bytes = stream_get_contents($stream);
        $this->scannedBytes[] = is_string($bytes) ? $bytes : '';

        return array_shift($this->results) ?? new MalwareScanResult(MalwareScanVerdict::Clean, 'test-engine', 'test-signatures');
    }
}

/** @return array{organisation: Organisation, program: Program, case: ServiceCase, manager: User, membership: Membership} */
function caseDocumentFixture(CaseClassification $classification = CaseClassification::Confidential): array
{
    $organisation = Organisation::factory()->active()->create();
    $manager = User::factory()->create();
    $membership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);

    [$program, $case] = app(OrganisationContext::class)->run($organisation, function () use ($classification, $organisation): array {
        $program = Program::factory()->for($organisation)->create();
        $party = Party::factory()->for($organisation)->create();
        $intake = IntakeRequest::factory()->create(['program_id' => $program->id, 'party_id' => $party->id]);
        $case = ServiceCase::factory()->create([
            'intake_request_id' => $intake->id,
            'program_id' => $program->id,
            'party_id' => $party->id,
            'confidentiality' => $classification,
        ]);

        return [$program, $case];
    });
    app(OrganisationContext::class)->run($organisation, fn () => $membership->programs()->attach($program));

    return compact('organisation', 'program', 'case', 'manager', 'membership');
}

function runDocumentScan(Organisation $organisation, CaseDocument $document): void
{
    $job = new ScanCaseDocument($organisation->uuid, $document->id, $document->generation);
    $job->handle(app(OrganisationContext::class), app(ReleaseCaseDocument::class));
}

beforeEach(function (): void {
    Storage::fake('case_quarantine');
    Storage::fake('case_documents');
    Queue::fake();
    config()->set('case_documents.uploads_enabled', true);
    config()->set('case_documents.quarantine_disk', 'case_quarantine');
    config()->set('case_documents.released_disk', 'case_documents');
    $this->scanner = new DeterministicMalwareScanner;
    app()->instance(MalwareScanner::class, $this->scanner);
});

it('rejects unauthorized malformed deceptive and unavailable uploads before release', function () {
    $fixture = caseDocumentFixture();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->image('safe.png'),
            'classification' => 'confidential',
        ])->assertForbidden();

    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->createWithContent('invoice.php.pdf', "%PDF-1.4\nsynthetic"),
            'classification' => 'confidential',
        ])->assertSessionHasErrors('document');

    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->createWithContent('spoofed.pdf', '<html>not a PDF</html>'),
            'classification' => 'confidential',
        ])->assertSessionHasErrors('document');

    $this->scanner->healthy = false;
    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->image('safe.png'),
            'classification' => 'confidential',
        ])->assertSessionHasErrors(['document' => 'Document scanning is temporarily unavailable.']);

    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(CaseDocument::query()->count())->toBe(0));
    Storage::disk('case_quarantine')->assertDirectoryEmpty('/');
    Storage::disk('case_documents')->assertDirectoryEmpty('/');
});

it('re-encodes and rescans images before verified release and forced audited download', function () {
    $this->withoutExceptionHandling();
    $fixture = caseDocumentFixture();
    $upload = UploadedFile::fake()->image('support.png', 16, 12);
    file_put_contents($upload->getRealPath(), 'synthetic-metadata', FILE_APPEND);
    $originalBytes = file_get_contents($upload->getRealPath());

    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => $upload,
            'classification' => 'confidential',
        ])->assertRedirect();

    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->with('versions')->sole());
    expect($document->versions->sole()->state)->toBe(CaseDocumentState::Quarantined);
    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    Queue::assertPushed(ScanCaseDocument::class);

    runDocumentScan($fixture['organisation'], $document);

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($document, $originalBytes): void {
        $document->refresh()->load('currentVersion');
        expect($document->currentVersion->state)->toBe(CaseDocumentState::Clean)
            ->and($document->currentVersion->byte_size)->toBeGreaterThan(0)
            ->and(Storage::disk('case_documents')->get($document->currentVersion->object_key))->not->toBe($originalBytes)
            ->and($document->currentVersion->getRawOriginal('encrypted_sha256'))->not->toContain(hash('sha256', Storage::disk('case_documents')->get($document->currentVersion->object_key)))
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::CaseDocumentScanCompleted)->sole()->payload)->toBe([
                'document_id' => $document->id,
                'generation' => 1,
                'outcome' => 'clean',
            ]);
        expect(TenantAuditEvent::query()->pluck('payload')->toJson())
            ->not->toContain('support.png')
            ->not->toContain('test-engine')
            ->not->toContain('test-signatures');
    });
    expect($this->scanner->scannedBytes)->toHaveCount(2)
        ->and($this->scanner->scannedBytes[0])->not->toBe($this->scanner->scannedBytes[1]);
    Storage::disk('case_quarantine')->assertDirectoryEmpty('/');

    $download = $this->actingAs($fixture['manager'])
        ->get(route('cases.documents.download', [$fixture['organisation'], $fixture['case'], $document]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('cache-control', 'max-age=0, no-store, private');
    expect($download->headers->get('content-disposition'))->toStartWith('attachment; filename=');
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(TenantAuditEvent::query()->where('type', TenantAuditEventType::CaseDocumentDownloaded)->count())->toBe(1));
});

it('requires sensitive access and preserves the last clean generation when a replacement is rejected', function () {
    $fixture = caseDocumentFixture();

    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->image('restricted.png'),
            'classification' => 'highly_restricted',
        ])->assertForbidden();

    app(OrganisationContext::class)->run($fixture['organisation'], fn () => app(GrantRestrictedAccess::class)->handle(
        $fixture['case'], $fixture['membership'], RestrictedAccessPermission::SensitiveData, 'Document safeguarding.', $fixture['manager'],
    ));
    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
            'document' => UploadedFile::fake()->image('restricted.png'),
            'classification' => 'highly_restricted',
        ])->assertRedirect();
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());
    runDocumentScan($fixture['organisation'], $document);
    $firstVersionId = app(OrganisationContext::class)->run($fixture['organisation'], fn (): string => (string) $document->refresh()->current_version_id);

    $this->scanner->results = [new MalwareScanResult(MalwareScanVerdict::Rejected, 'test-engine', 'test-signatures', 'policy_rejected')];
    $this->actingAs($fixture['manager'])
        ->post(route('cases.documents.replace', [$fixture['organisation'], $fixture['case'], $document]), [
            'document' => UploadedFile::fake()->image('replacement.png'),
            'classification' => 'confidential',
        ])->assertRedirect();
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => $document->refresh());

    $this->actingAs($fixture['manager'])
        ->get(route('cases.documents.download', [$fixture['organisation'], $fixture['case'], $document]))
        ->assertOk();
    runDocumentScan($fixture['organisation'], $document);

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($document, $firstVersionId): void {
        $document->refresh();
        expect($document->current_version_id)->toBe($firstVersionId)
            ->and($document->encrypted_display_name->reveal())->toBe('restricted.png')
            ->and($document->classification)->toBe(CaseClassification::HighlyRestricted)
            ->and($document->versions()->where('generation', 2)->sole()->state)->toBe(CaseDocumentState::Rejected)
            ->and($document->versions()->where('generation', 2)->sole()->classification)->toBe(CaseClassification::HighlyRestricted)
            ->and($document->versions()->where('generation', 2)->sole()->quarantine_path)->toBeNull();
    });
    Storage::disk('case_quarantine')->assertDirectoryEmpty('/');

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $grant = RestrictedAccessGrant::query()->where('membership_id', $fixture['membership']->id)->sole();
        app(RevokeRestrictedAccess::class)->handle($grant, 'Document access ended.', $fixture['manager']);
    });
    $this->actingAs($fixture['manager'])
        ->get(route('cases.documents.download', [$fixture['organisation'], $fixture['case'], $document]))
        ->assertForbidden();
});

it('does not allow a stale scan generation to release bytes', function () {
    $fixture = caseDocumentFixture();
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('first.png'),
        'classification' => 'confidential',
    ]);
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());
    $staleJob = new ScanCaseDocument($fixture['organisation']->uuid, $document->id, 1);

    $this->actingAs($fixture['manager'])->post(route('cases.documents.replace', [$fixture['organisation'], $fixture['case'], $document]), [
        'document' => UploadedFile::fake()->image('second.png'),
        'classification' => 'confidential',
    ]);
    $staleJob->handle(app(OrganisationContext::class), app(ReleaseCaseDocument::class));

    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(CaseDocumentVersion::query()->where('generation', 1)->sole()->state)->toBe(CaseDocumentState::Quarantined));
});

it('keeps quarantined bytes private when scanner health becomes stale before processing', function () {
    $fixture = caseDocumentFixture();
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('safe.png'),
        'classification' => 'confidential',
    ]);
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());
    $this->scanner->healthy = false;

    expect(fn () => runDocumentScan($fixture['organisation'], $document))
        ->toThrow(RuntimeException::class, 'unavailable or stale');

    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect($document->versions()->sole()->state)->toBe(CaseDocumentState::ScanFailed));
});

it('destroys images that exceed decoded dimension policy after the first clean scan', function () {
    $fixture = caseDocumentFixture();
    config()->set('case_documents.max_image_pixels', 1);
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('oversized.png', 2, 2),
        'classification' => 'confidential',
    ]);
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());

    runDocumentScan($fixture['organisation'], $document);

    Storage::disk('case_quarantine')->assertDirectoryEmpty('/');
    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    expect($this->scanner->scannedBytes)->toHaveCount(1);
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect($document->versions()->sole()->state)->toBe(CaseDocumentState::Rejected));
});

it('removes an unapproved object and fails the scan closed when its audit cannot be persisted', function () {
    $fixture = caseDocumentFixture();
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('safe.png'),
        'classification' => 'confidential',
    ]);
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());

    $this->mock(RecordTenantAuditEvent::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });
    expect(fn () => runDocumentScan($fixture['organisation'], $document))
        ->toThrow(RuntimeException::class, 'Audit unavailable.');

    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect($document->versions()->sole()->state)->toBe(CaseDocumentState::ScanFailed));
});

it('streams no clean bytes when the required download audit is unavailable', function () {
    $fixture = caseDocumentFixture();
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('safe.png'),
        'classification' => 'confidential',
    ]);
    $document = app(OrganisationContext::class)->run($fixture['organisation'], fn (): CaseDocument => CaseDocument::query()->sole());
    runDocumentScan($fixture['organisation'], $document);

    $this->mock(RecordTenantAuditEvent::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($fixture['manager'])->get(route('cases.documents.download', [$fixture['organisation'], $fixture['case'], $document])))
        ->toThrow(RuntimeException::class, 'Audit unavailable.');
});

it('reconciles expired and orphaned quarantine bytes without releasing them', function () {
    $fixture = caseDocumentFixture();
    $this->actingAs($fixture['manager'])->post(route('cases.documents.store', [$fixture['organisation'], $fixture['case']]), [
        'document' => UploadedFile::fake()->image('expiring.png'),
        'classification' => 'confidential',
    ]);
    $version = app(OrganisationContext::class)->run($fixture['organisation'], function (): CaseDocumentVersion {
        $version = CaseDocumentVersion::query()->sole();
        $version->forceFill(['expires_at' => now()->subMinute()])->save();

        return $version;
    });
    Storage::disk('case_quarantine')->put('orphan.upload', 'orphaned synthetic bytes');
    $this->travel(2)->hours();

    $this->artisan('case-documents:reconcile')->assertSuccessful();

    Storage::disk('case_quarantine')->assertDirectoryEmpty('/');
    Storage::disk('case_documents')->assertDirectoryEmpty('/');
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($version): void {
        expect($version->refresh()->state)->toBe(CaseDocumentState::Deleted)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::CaseDocumentScanCompleted)->sole()->payload['outcome'])->toBe('expired');
    });
});
