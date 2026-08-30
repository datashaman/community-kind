<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Models\Organisation;
use App\Models\OrganisationSlug;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChangeOrganisationSlug
{
    public function __construct(
        private InvalidateOrganisationAccess $invalidateOrganisationAccess,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ) {}

    public function handle(Organisation $organisation, string $slug, User $actor): Organisation
    {
        $slug = Str::slug($slug);

        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => __('The slug must contain at least one letter or number.')]);
        }

        return Cache::lock("organisation-slug:{$slug}", 10)->block(5, function () use ($organisation, $slug, $actor): Organisation {
            return DB::transaction(function () use ($organisation, $slug, $actor): Organisation {
                $organisation = Organisation::lockForUpdate()->findOrFail($organisation->id);

                if ($organisation->slug === $slug) {
                    return $organisation;
                }

                if (Organisation::where('slug', $slug)->whereKeyNot($organisation->id)->exists()) {
                    throw ValidationException::withMessages(['slug' => __('This organisation slug is unavailable.')]);
                }

                $previousUse = OrganisationSlug::where('slug', $slug)->lockForUpdate()->first();

                if ($previousUse?->quarantined_until?->isFuture()) {
                    throw ValidationException::withMessages(['slug' => __('This organisation slug is quarantined.')]);
                }

                $previousUse?->delete();
                $oldSlug = $organisation->slug;
                $redirectUntil = now()->addDays((int) config('organisation_lifecycle.slug_redirect_days'));

                $organisation->previousSlugs()->create([
                    'slug' => $oldSlug,
                    'redirect_until' => $redirectUntil,
                    'quarantined_until' => $redirectUntil->copy()->addDays((int) config('organisation_lifecycle.slug_quarantine_days')),
                ]);
                $organisation->update(['slug' => $slug]);

                $this->invalidateOrganisationAccess->handle($organisation);
                $this->recordOrganisationLifecycleEvent->handle(
                    $organisation,
                    OrganisationLifecycleEventType::SlugChanged,
                    $actor,
                    metadata: ['from' => $oldSlug, 'to' => $slug, 'redirect_until' => $redirectUntil->toISOString()],
                );

                return $organisation;
            });
        });
    }
}
