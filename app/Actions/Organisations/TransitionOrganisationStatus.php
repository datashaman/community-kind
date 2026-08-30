<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransitionOrganisationStatus
{
    public function __construct(
        private InvalidateOrganisationAccess $invalidateOrganisationAccess,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ) {}

    public function handle(Organisation $organisation, OrganisationStatus $toStatus, ?User $actor = null): Organisation
    {
        return DB::transaction(function () use ($organisation, $toStatus, $actor): Organisation {
            $organisation = Organisation::withTrashed()->lockForUpdate()->findOrFail($organisation->id);
            $fromStatus = $organisation->status;

            if (! in_array($toStatus, $fromStatus->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => __('The organisation cannot move from :from to :to.', [
                        'from' => $fromStatus->value,
                        'to' => $toStatus->value,
                    ]),
                ]);
            }

            if ($toStatus === OrganisationStatus::Deleted && $organisation->deletion_scheduled_for?->isFuture() !== false) {
                throw ValidationException::withMessages([
                    'status' => __('The organisation is still within its deletion recovery period.'),
                ]);
            }

            if ($toStatus === OrganisationStatus::Active && $organisation->accessHolds()
                ->whereNull('released_at')
                ->where('starts_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => __('Active Access Holds must be released before the organisation can be reactivated.'),
                ]);
            }

            $attributes = [
                'status' => $toStatus,
                'status_changed_at' => now(),
            ];

            if ($toStatus === OrganisationStatus::ScheduledForDeletion) {
                $attributes['deletion_scheduled_for'] = now()->addDays((int) config('organisation_lifecycle.deletion_recovery_days'));
            } elseif ($fromStatus === OrganisationStatus::ScheduledForDeletion) {
                $attributes['deletion_scheduled_for'] = null;
            }

            if ($toStatus === OrganisationStatus::Deleted) {
                $organisation->previousSlugs()->create([
                    'slug' => $organisation->slug,
                    'redirect_until' => now(),
                    'quarantined_until' => now()->addDays((int) config('organisation_lifecycle.slug_quarantine_days')),
                ]);
                $attributes['slug'] = 'deleted-'.$organisation->id.'-'.Str::lower((string) Str::ulid());
            }

            $organisation->update($attributes);
            $this->invalidateOrganisationAccess->handle($organisation);
            $this->recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::StatusChanged,
                $actor,
                $fromStatus,
                $toStatus,
                ['deletion_scheduled_for' => $organisation->deletion_scheduled_for?->toISOString()],
            );

            if (in_array($toStatus, [OrganisationStatus::ScheduledForDeletion, OrganisationStatus::Deleted], true)) {
                User::where('current_organisation_id', $organisation->id)
                    ->each(function (User $user) use ($organisation): void {
                        $user->update([
                            'current_organisation_id' => $user->fallbackOrganisation($organisation)?->id,
                        ]);
                    });
            }

            if ($toStatus === OrganisationStatus::Deleted) {
                $organisation->delete();
            }

            return $organisation;
        });
    }
}
