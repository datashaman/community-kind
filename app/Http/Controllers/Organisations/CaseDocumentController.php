<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseDocuments\DownloadCaseDocument;
use App\Actions\CaseDocuments\QuarantineCaseDocument;
use App\Authorization\CaseAccess;
use App\Contracts\MalwareScanner;
use App\Enums\CaseClassification;
use App\Enums\CaseDocumentState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreCaseDocumentRequest;
use App\Models\CaseDocument;
use App\Models\CaseDocumentVersion;
use App\Models\Organisation;
use App\Models\ServiceCase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseDocumentController extends Controller
{
    public function store(
        StoreCaseDocumentRequest $request,
        Organisation $currentOrganisation,
        string $case,
        QuarantineCaseDocument $quarantine,
        MalwareScanner $scanner,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('update', $serviceCase);
        $classification = CaseClassification::from($request->string('classification')->toString());

        if ($serviceCase->confidentiality === CaseClassification::HighlyRestricted) {
            $classification = CaseClassification::HighlyRestricted;
        }

        abort_if($classification === CaseClassification::HighlyRestricted && Gate::denies('viewSensitive', $serviceCase), 403);

        $this->withUploadLock($currentOrganisation, function () use ($classification, $currentOrganisation, $quarantine, $request, $scanner, $serviceCase): void {
            $this->ensureUploadAvailable($request, $currentOrganisation, $scanner);
            $quarantine->handle($serviceCase, $request->file('document'), $classification, $request->user());
        });

        return back();
    }

    public function replace(
        StoreCaseDocumentRequest $request,
        Organisation $currentOrganisation,
        string $case,
        string $document,
        QuarantineCaseDocument $quarantine,
        MalwareScanner $scanner,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('update', $serviceCase);
        $caseDocument = CaseDocument::query()->where('service_case_id', $serviceCase->id)->findOrFail($document);
        abort_unless(app(CaseAccess::class)->canViewDocument($request->user(), $caseDocument), 403);
        $classification = CaseClassification::from($request->string('classification')->toString());

        if ($serviceCase->confidentiality === CaseClassification::HighlyRestricted) {
            $classification = CaseClassification::HighlyRestricted;
        }

        abort_if($classification === CaseClassification::HighlyRestricted && Gate::denies('viewSensitive', $serviceCase), 403);

        $this->withUploadLock($currentOrganisation, function () use ($caseDocument, $classification, $currentOrganisation, $quarantine, $request, $scanner, $serviceCase): void {
            $this->ensureUploadAvailable($request, $currentOrganisation, $scanner);
            $quarantine->handle($serviceCase, $request->file('document'), $classification, $request->user(), $caseDocument);
        });

        return back();
    }

    public function download(
        Request $request,
        Organisation $currentOrganisation,
        string $case,
        string $document,
        DownloadCaseDocument $download,
    ): StreamedResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        $caseDocument = CaseDocument::query()->where('service_case_id', $serviceCase->id)->findOrFail($document);

        return $download->handle($caseDocument, $request->user());
    }

    private function ensureUploadAvailable(StoreCaseDocumentRequest $request, Organisation $organisation, MalwareScanner $scanner): void
    {
        if (! config('case_documents.uploads_enabled') || ! $scanner->isHealthy()) {
            throw ValidationException::withMessages(['document' => __('Document scanning is temporarily unavailable.')]);
        }

        $nonTerminal = CaseDocumentVersion::query()
            ->whereNotIn('state', [CaseDocumentState::Clean, CaseDocumentState::Rejected, CaseDocumentState::Deleted])
            ->count();

        if ($nonTerminal >= (int) config('case_documents.max_non_terminal_scans')) {
            throw ValidationException::withMessages(['document' => __('Too many documents are awaiting security checks.')]);
        }

        $bytes = (int) $request->file('document')->getSize();
        $key = 'case-document-bytes:'.$organisation->uuid;

        $usedBytes = RateLimiter::increment($key, 3600, $bytes);

        if ($usedBytes > (int) config('case_documents.max_organisation_bytes_per_hour')) {
            throw ValidationException::withMessages(['document' => __('The Organisation upload limit has been reached.')]);
        }
    }

    private function withUploadLock(Organisation $organisation, callable $callback): void
    {
        try {
            Cache::lock('case-document-upload:'.$organisation->uuid, 120)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['document' => __('Another document upload is being prepared. Please retry.')]);
        }
    }
}
