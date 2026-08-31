<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Intake\ReversePartyMerge;
use App\Actions\Intake\ReviewPartyDuplicate;
use App\Enums\DuplicateReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\ReviewPartyDuplicateRequest;
use App\Models\Organisation;
use App\Models\PartyDuplicateReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class PartyDuplicateReviewController extends Controller
{
    public function store(
        ReviewPartyDuplicateRequest $request,
        Organisation $currentOrganisation,
        string $duplicateReview,
        ReviewPartyDuplicate $reviewPartyDuplicate,
    ): RedirectResponse {
        $partyDuplicateReview = PartyDuplicateReview::query()->findOrFail($duplicateReview);
        Gate::authorize('reviewDuplicate', $partyDuplicateReview->intakeRequest);
        try {
            $reviewPartyDuplicate->handle(
                $partyDuplicateReview,
                DuplicateReviewDecision::from($request->string('decision')->toString()),
                $request->user(),
            );
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['duplicate' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(
        Request $request,
        Organisation $currentOrganisation,
        string $duplicateReview,
        ReversePartyMerge $reversePartyMerge,
    ): RedirectResponse {
        $partyDuplicateReview = PartyDuplicateReview::query()->findOrFail($duplicateReview);
        Gate::authorize('reviewDuplicate', $partyDuplicateReview->intakeRequest);

        try {
            $reversePartyMerge->handle($partyDuplicateReview, $request->user());
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['duplicate' => $exception->getMessage()]);
        }

        return back();
    }
}
