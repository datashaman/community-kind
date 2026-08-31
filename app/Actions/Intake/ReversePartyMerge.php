<?php

namespace App\Actions\Intake;

use App\Enums\DuplicateReviewDecision;
use App\Models\IntakeRequest;
use App\Models\PartyDuplicateReview;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReversePartyMerge
{
    public function __construct(private readonly OrganisationContext $organisationContext) {}

    public function handle(PartyDuplicateReview $review, User $actor): PartyDuplicateReview
    {
        $this->organisationContext->ensureOwns($review->organisation_id);

        return DB::transaction(function () use ($review, $actor): PartyDuplicateReview {
            $review = PartyDuplicateReview::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->decision !== DuplicateReviewDecision::Merged || $review->reversed_at !== null) {
                throw new LogicException('Only an active logical Party merge can be reversed.');
            }

            $intake = IntakeRequest::query()->lockForUpdate()->findOrFail($review->intake_request_id);

            if ($intake->party_id !== $review->canonical_party_id) {
                throw new LogicException('The intake Party changed after the merge and cannot be reversed automatically.');
            }

            if ($intake->serviceCase()->exists()) {
                throw new LogicException('A duplicate decision cannot be reversed after a Case has been created.');
            }

            $intake->update(['party_id' => $review->submitted_party_id]);
            $review->forceFill([
                'reversed_at' => now(),
                'reversed_by_user_id' => $actor->id,
            ])->save();

            return $review->refresh();
        });
    }
}
