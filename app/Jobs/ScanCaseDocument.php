<?php

namespace App\Jobs;

use App\Actions\CaseDocuments\ReleaseCaseDocument;
use App\Enums\CaseDocumentState;
use App\Models\CaseDocument;
use App\Models\Organisation;
use App\OrganisationContext;
use App\Queue\Middleware\PauseForInstallationControl;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ScanCaseDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 150;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $organisationUuid,
        public string $documentId,
        public int $expectedGeneration,
        public int $expectedOrganisationGeneration = 0,
    ) {
        $this->onQueue('security');
    }

    /**
     * Execute the job.
     */
    public function handle(OrganisationContext $context, ReleaseCaseDocument $release): void
    {
        $organisation = Organisation::query()->where('uuid', $this->organisationUuid)->first();

        if ($organisation === null || $organisation->demo_generation !== $this->expectedOrganisationGeneration) {
            return;
        }

        $context->run($organisation, function () use ($release): void {
            $document = CaseDocument::query()->find($this->documentId);

            if ($document === null || $document->generation !== $this->expectedGeneration) {
                return;
            }

            $version = $document->versions()->where('generation', $this->expectedGeneration)->first();

            if ($version === null || ! in_array($version->state, [CaseDocumentState::Quarantined, CaseDocumentState::ScanFailed], true)) {
                return;
            }

            $release->handle($document, $this->expectedGeneration);
        });
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            new PauseForInstallationControl,
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(300)->releaseAfter(30),
        ];
    }

    public function uniqueId(): string
    {
        return $this->organisationUuid.':'.$this->expectedOrganisationGeneration.':'.$this->documentId.':'.$this->expectedGeneration;
    }
}
