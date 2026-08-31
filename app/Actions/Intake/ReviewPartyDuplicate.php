<?php

namespace App\Actions\Intake;

use App\Enums\DuplicateReviewDecision;
use App\Models\IntakeRequest;
use App\Models\PartyDuplicateReview;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewPartyDuplicate
{
    public function __construct(private readonly OrganisationContext $organisationContext) {}

    public function handle(PartyDuplicateReview $review, DuplicateReviewDecision $decision, User $actor): PartyDuplicateReview
    {
        $this->organisationContext->ensureOwns($review->organisation_id);

        if (! in_array($decision, [DuplicateReviewDecision::Dismissed, DuplicateReviewDecision::Merged], true)) {
            throw new LogicException('A duplicate review must be dismissed or merged.');
        }

        return DB::transaction(function () use ($review, $decision, $actor): PartyDuplicateReview {
            $review = PartyDuplicateReview::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->decision !== DuplicateReviewDecision::Pending) {
                return $review;
            }

            $intake = IntakeRequest::query()->lockForUpdate()->findOrFail($review->intake_request_id);

            if ($intake->serviceCase()->exists()) {
                throw new LogicException('A duplicate decision cannot change the Party after a Case has been created.');
            }

            if ($decision === DuplicateReviewDecision::Merged && $intake->party_id !== $review->submitted_party_id) {
                throw new LogicException('The intake is already linked to a different canonical Party.');
            }

            $canonicalPartyId = $decision === DuplicateReviewDecision::Merged ? $review->candidate_party_id : null;
            $review->forceFill([
                'decision' => $decision,
                'canonical_party_id' => $canonicalPartyId,
                'decided_at' => now(),
                'decided_by_user_id' => $actor->id,
            ])->save();

            if ($canonicalPartyId !== null) {
                $intake->update(['party_id' => $canonicalPartyId]);
            }

            return $review->refresh();
        });
    }
}
