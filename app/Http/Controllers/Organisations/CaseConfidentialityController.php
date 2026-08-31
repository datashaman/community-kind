<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Actions\CaseConfidentiality\ReclassifyServiceCase;
use App\Actions\CaseConfidentiality\RecordCaseRiskAssessment;
use App\Actions\CaseConfidentiality\RevokeRestrictedAccess;
use App\Enums\CaseClassification;
use App\Enums\RestrictedAccessPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\ReclassifyServiceCaseRequest;
use App\Http\Requests\Organisations\StoreRestrictedAccessGrantRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class CaseConfidentialityController extends Controller
{
    public function reclassify(
        ReclassifyServiceCaseRequest $request,
        Organisation $currentOrganisation,
        string $case,
        ReclassifyServiceCase $reclassify,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('manageAccess', $serviceCase);

        try {
            $reclassify->handle(
                $serviceCase,
                CaseClassification::from($request->string('classification')->toString()),
                $request->string('reason')->toString(),
                $request->user(),
            );
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['classification' => $exception->getMessage()]);
        }

        return back();
    }

    public function grant(
        StoreRestrictedAccessGrantRequest $request,
        Organisation $currentOrganisation,
        string $case,
        GrantRestrictedAccess $grantAccess,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('manageAccess', $serviceCase);
        $membership = Membership::query()
            ->where('organisation_id', $currentOrganisation->id)
            ->whereKey($request->integer('membership_id'))
            ->firstOrFail();

        try {
            $grantAccess->handle(
                $serviceCase,
                $membership,
                RestrictedAccessPermission::from($request->string('permission')->toString()),
                $request->string('reason')->toString(),
                $request->user(),
                $request->filled('expires_at') ? CarbonImmutable::parse($request->string('expires_at')->toString()) : null,
            );
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['membership_id' => $exception->getMessage()]);
        }

        return back();
    }

    public function risk(
        Request $request,
        Organisation $currentOrganisation,
        string $case,
        RecordCaseRiskAssessment $recordRisk,
    ): RedirectResponse {
        $validated = $request->validate(['content' => ['required', 'string', 'max:10000']]);
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('viewSensitive', $serviceCase);
        $recordRisk->handle($serviceCase, $validated['content'], $request->user());

        return back();
    }

    public function revoke(
        Request $request,
        Organisation $currentOrganisation,
        string $case,
        string $grant,
        RevokeRestrictedAccess $revokeAccess,
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('manageAccess', $serviceCase);
        $accessGrant = RestrictedAccessGrant::query()
            ->whereKey($grant)
            ->where(fn ($query) => $query->where('service_case_id', $serviceCase->id)->orWhere(function ($export) use ($serviceCase): void {
                $export->whereNull('service_case_id')->where('program_id', $serviceCase->program_id);
            }))
            ->firstOrFail();
        $revokeAccess->handle($accessGrant, $validated['reason'], $request->user());

        return back();
    }
}
